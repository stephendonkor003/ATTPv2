<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\DynamicForm;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\Procurement;
use App\Models\VendorCategory;
use App\Services\ProcurementSubmissionScreeningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendorProcurementController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $today = now()->toDateString();

        Procurement::where('status', 'published')
            ->whereNotNull('application_end_date')
            ->whereDate('application_end_date', '<', $today)
            ->update(['status' => 'closed']);

        $procurements = collect();
        $hasActiveCategory = $user->vendor_category
            && VendorCategory::where('name', $user->vendor_category)
                ->where('is_active', true)
                ->exists();

        if ($hasActiveCategory) {
            $procurements = Procurement::where('status', 'published')
                ->where('visibility_type', 'vendor_group')
                ->whereJsonContains('vendor_categories', $user->vendor_category)
                ->where(function ($query) use ($today) {
                    $query->whereNull('application_start_date')
                        ->orWhereDate('application_start_date', '<=', $today);
                })
                ->where(function ($query) use ($today) {
                    $query->whereNull('application_end_date')
                        ->orWhereDate('application_end_date', '>=', $today);
                })
                ->latest()
                ->get();
        }

        return view('vendor.procurements.index', [
            'procurements' => $procurements,
            'vendorCategory' => $hasActiveCategory ? $user->vendor_category : null,
        ]);
    }

    public function show(Request $request, Procurement $procurement)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $procurement->autoCloseIfExpired();

        if (($procurement->visibility_type ?? 'public') !== 'vendor_group') {
            abort(404);
        }

        if (!$procurement->isApplicationOpen()) {
            abort(404);
        }

        $this->assertVendorCategoryAccess($user, $procurement);

        $form = DynamicForm::approved()
            ->where('procurement_id', $procurement->id)
            ->where('is_active', true)
            ->with('fields')
            ->first();

        if ($form) {
            $form->ensureGlobalFields();
            $form->load('fields');
        }

        $existingSubmission = FormSubmission::where('procurement_id', $procurement->id)
            ->where('submitted_by', $user->id)
            ->first();

        return view('vendor.procurements.show', [
            'procurement' => $procurement,
            'form' => $form,
            'existingSubmission' => $existingSubmission,
        ]);
    }

    public function submit(
        Request $request,
        Procurement $procurement,
        ProcurementSubmissionScreeningService $screeningService
    )
    {
        $user = $request->user();
        $this->assertVendor($user);

        $procurement->autoCloseIfExpired();

        if (($procurement->visibility_type ?? 'public') !== 'vendor_group') {
            abort(404);
        }

        if (!$procurement->isApplicationOpen()) {
            abort(403, 'This procurement is closed for applications.');
        }

        $this->assertVendorCategoryAccess($user, $procurement);

        $existingSubmission = FormSubmission::where('procurement_id', $procurement->id)
            ->where('submitted_by', $user->id)
            ->first();

        if ($existingSubmission) {
            return redirect()
                ->route('vendor.applications.edit', $existingSubmission)
                ->with('success', 'You already submitted this procurement. You can update your application here.');
        }

        $form = DynamicForm::approved()
            ->where('procurement_id', $procurement->id)
            ->where('is_active', true)
            ->with('fields')
            ->firstOrFail();

        $form->ensureGlobalFields();
        $form->load('fields');

        $fieldKeys = $form->fields->pluck('field_key')->all();
        if (in_array('official_name', $fieldKeys, true)) {
            $request->merge(['official_name' => $user->name ?? $user->email]);
        }
        if (in_array('official_email', $fieldKeys, true)) {
            $request->merge(['official_email' => $user->email]);
        }

        $rules = [];
        foreach ($form->fields as $field) {
            $key = $field->field_key;
            $required = $field->is_required ? 'required' : 'nullable';

            switch ($field->field_type) {
                case 'email':
                    $rules[$key] = "$required|email";
                    break;
                case 'file':
                    $rules[$key] = "$required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip|max:20480";
                    break;
                case 'checkbox':
                case 'multiselect':
                    $rules[$key] = "$required|array";
                    break;
                case 'number':
                    $rules[$key] = "$required|numeric";
                    break;
                case 'url':
                    $rules[$key] = "$required|url";
                    break;
                default:
                    $rules[$key] = $required;
            }
        }

        $validated = $request->validate($rules);

        $submission = null;

        DB::transaction(function () use ($request, $procurement, $form, $user, &$submission) {
            $submission = FormSubmission::create([
                'procurement_id' => $procurement->id,
                'form_id' => $form->id,
                'submitted_by' => $user->id,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            foreach ($form->fields as $field) {
                $key = $field->field_key;
                $value = null;

                if ($field->field_type === 'file' && $request->hasFile($key)) {
                    $value = $request->file($key)->store('procurement_submissions');
                } elseif (is_array($request->input($key))) {
                    $value = json_encode(array_values($request->input($key)));
                } else {
                    $value = $request->input($key);
                }

                FormSubmissionValue::create([
                    'submission_id' => $submission->id,
                    'field_key' => $key,
                    'value' => $value,
                ]);
            }
        });

        if ($submission) {
            $screeningService->deferSubmissionScreening($submission->id);
        }

        return redirect()
            ->route('vendor.submissions')
            ->with('success', 'Application submitted successfully.');
    }

    private function assertVendor($user): void
    {
        if (!$user || $user->user_type !== 'vendor') {
            abort(403, 'Access denied. Vendor portal only.');
        }

        if ($user->is_blacklisted) {
            abort(403, 'Your vendor account has been blacklisted. Please contact the administrator.');
        }

        if ($user->is_disabled) {
            abort(403, 'Your vendor account has been disabled. Please contact the administrator.');
        }
    }

    private function assertVendorCategoryAccess($user, Procurement $procurement): void
    {
        $categories = $procurement->vendor_categories ?? [];
        $hasActiveCategory = $user->vendor_category
            && VendorCategory::where('name', $user->vendor_category)
                ->where('is_active', true)
                ->exists();

        if (!$hasActiveCategory || empty($categories) || !in_array($user->vendor_category, $categories, true)) {
            abort(403, 'You are not authorized to apply for this procurement.');
        }
    }
}
