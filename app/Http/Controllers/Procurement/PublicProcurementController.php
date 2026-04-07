<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\DynamicForm;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\User;
use App\Mail\VendorApplicationReceived;
use App\Services\ProcurementSubmissionScreeningService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PublicProcurementController extends Controller
{
    /**
     * ===============================
     * PUBLIC PROCUREMENT LIST
     * ===============================
     */
    public function index()
    {
        $today = now()->toDateString();

        Procurement::where('status', 'published')
            ->whereNotNull('application_end_date')
            ->whereDate('application_end_date', '<', $today)
            ->update(['status' => 'closed']);

        $procurements = Procurement::where('status', 'published')
            ->where(function ($query) {
                $query->whereNull('visibility_type')
                    ->orWhere('visibility_type', 'public');
            })
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

        return view('public.procurements.index', compact('procurements'));
    }

    /**
     * ===============================
     * SHOW PROCUREMENT + FORM
     * ===============================
     */
    public function show(Procurement $procurement)
    {
        if ($procurement->visibility_type && $procurement->visibility_type !== 'public') {
            abort(404);
        }

        $procurement->autoCloseIfExpired();
        abort_if(!$procurement->isApplicationOpen(), 404);

        $form = DynamicForm::approved()
            ->where('procurement_id', $procurement->id)
            ->where('is_active', true)
            ->with('fields')
            ->first(); // allow null for public view

        if ($form) {
            $form->ensureGlobalFields();
            $form->load('fields');
        }

        return view('public.procurements.show', compact('procurement', 'form'));
    }

    /**
     * ===============================
     * SUBMIT PROCUREMENT APPLICATION
     * ===============================
     */
    public function submit(
        Request $request,
        Procurement $procurement,
        ProcurementSubmissionScreeningService $screeningService
    )
    {
        if ($procurement->visibility_type && $procurement->visibility_type !== 'public') {
            abort(404);
        }

        $procurement->autoCloseIfExpired();
        abort_if(!$procurement->isApplicationOpen(), 404);

        $form = DynamicForm::approved()
            ->where('procurement_id', $procurement->id)
            ->where('is_active', true)
            ->with('fields')
            ->firstOrFail();

        $form->ensureGlobalFields();
        $form->load('fields');

        /*
        |--------------------------------------------------------------------------
        | DYNAMIC VALIDATION (SELECT2 READY)
        |--------------------------------------------------------------------------
        */
        $rules = [];

        foreach ($form->fields as $field) {

            $key = $field->field_key;
            $required = $field->is_required ? 'required' : 'nullable';

            switch ($field->field_type) {

                case 'email':
                    $rules[$key] = "$required|email";
                    break;

                case 'file':
                    // NOTE: Laravel's "max" is in kilobytes. Keep public uploads small to reduce DoS risk.
                    $rules[$key] = "$required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip|max:20480"; // 20MB
                    break;

                case 'checkbox':       // Select2 multi-select
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

        $officialName = trim((string) $request->input('official_name'));
        $officialEmail = trim((string) $request->input('official_email'));
        if ($officialEmail === '') {
            return back()->withErrors([
                'official_email' => 'Official email is required to receive confirmation and access credentials.',
            ]);
        }

        $existingUser = User::whereRaw('LOWER(email) = ?', [Str::lower($officialEmail)])->first();
        $temporaryPassword = null;
        $vendorUser = null;

        if ($existingUser) {
            if ($existingUser->user_type !== 'vendor') {
                return back()->withErrors([
                    'official_email' => 'This email belongs to an internal account and cannot be used for procurement submissions.',
                ]);
            }

            if ($existingUser->is_blacklisted) {
                return back()->withErrors([
                    'official_email' => 'This vendor has been blacklisted and cannot submit procurement applications.',
                ]);
            }

            if ($existingUser->is_disabled) {
                return back()->withErrors([
                    'official_email' => 'This vendor account is disabled. Please contact the administrator.',
                ]);
            }

            $alreadySubmitted = FormSubmission::where('procurement_id', $procurement->id)
                ->where('submitted_by', $existingUser->id)
                ->exists();

            if ($alreadySubmitted) {
                return back()->withErrors([
                    'official_email' => 'You have already submitted an application for this procurement.',
                ]);
            }

            $vendorUser = $existingUser;
        } else {
            $temporaryPassword = Str::random(12);
            $vendorUser = User::create([
                'name' => $officialName ?: $officialEmail,
                'email' => $officialEmail,
                'password' => Hash::make($temporaryPassword),
                'user_type' => 'vendor',
                'must_change_password' => true,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | SAVE SUBMISSION + VALUES
        |--------------------------------------------------------------------------
        */
        $submission = null;
        DB::transaction(function () use ($request, $procurement, $form, $vendorUser, &$submission) {

            $submission = FormSubmission::create([
                'procurement_id' => $procurement->id,
                'form_id'        => $form->id,
                'submitted_by'   => $vendorUser?->id,
                'status'         => 'submitted',
                'submitted_at'   => now(),
            ]);

            foreach ($form->fields as $field) {

                $key = $field->field_key;
                $value = null;

                // FILE
                if ($field->field_type === 'file' && $request->hasFile($key)) {
                    $value = $request->file($key)
                        // Store submissions on the default (private) disk; access must be authorized.
                        ->store('procurement_submissions');
                }

                // MULTI SELECT (ARRAY FROM SELECT2)
                elseif (is_array($request->input($key))) {
                    $value = json_encode(array_values($request->input($key)));
                }

                // NORMAL INPUT
                else {
                    $value = $request->input($key);
                }

                FormSubmissionValue::create([
                    'submission_id' => $submission->id,
                    'field_key'     => $key,
                    'value'         => $value,
                ]);
            }
        });

        if ($vendorUser && $submission) {
            Mail::to($vendorUser->email)
                ->send(new VendorApplicationReceived($procurement, $submission, $vendorUser, $temporaryPassword));
        }

        if ($submission) {
            $screeningService->deferSubmissionScreening($submission->id);
        }

        return back()->with('success', 'Application submitted successfully. Your login credentials have been emailed to the official email address provided.');
    }
}
