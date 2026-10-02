<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\DynamicForm;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\DynamicProcurementFormResolver;
use App\Services\DynamicProcurementSubmissionFileService;
use App\Services\DynamicProcurementSubmissionValidation;
use App\Services\ProcurementSubmissionScreeningAutomation;
use App\Services\ThinkTankVendorDirectoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VendorProcurementController extends Controller
{
    public function __construct(
        private readonly ThinkTankVendorDirectoryService $vendorDirectory,
        private readonly DynamicProcurementFormResolver $formResolver,
        private readonly DynamicProcurementSubmissionFileService $submissionFiles,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $today = now()->toDateString();
        $activeTenantIds = DB::table('attp_think_tank_vendor_user')
            ->where('vendor_user_id', $user->getKey())
            ->where('status', 'active')
            ->pluck('think_tank_member_id')
            ->all();

        $procurements = Procurement::where('status', 'published')
            ->where('visibility_type', 'vendor_group')
            ->where(function ($scope) use ($activeTenantIds): void {
                $scope->where(function ($legacy): void {
                    $legacy->whereNull('procurement_owner_type')
                        ->orWhere('procurement_owner_type', '<>', 'think_tank');
                })->orWhere(function ($tenant) use ($activeTenantIds): void {
                    $tenant->where('procurement_owner_type', 'think_tank')
                        ->whereIn('think_tank_member_id', $activeTenantIds);
                });
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
            ->get()
            ->filter(fn (Procurement $procurement): bool => $this->vendorDirectory
                ->vendorCanAccess($user, $procurement))
            ->values();

        $tenantCategories = $user->thinkTankVendorCategories()
            ->where('attp_think_tank_vendor_categories.is_active', true)
            ->pluck('attp_think_tank_vendor_categories.name')
            ->all();
        $audienceLabel = $tenantCategories !== []
            ? implode(', ', $tenantCategories)
            : ($user->vendor_category ?: null);

        return view('vendor.procurements.index', [
            'procurements' => $procurements,
            'vendorCategory' => $audienceLabel,
        ]);
    }

    public function show(Request $request, Procurement $procurement)
    {
        $user = $request->user();
        $this->assertVendor($user);

        if (($procurement->visibility_type ?? 'public') !== 'vendor_group') {
            abort(404);
        }

        $this->assertVendorCategoryAccess($user, $procurement);
        $procurement->autoCloseIfExpired();

        if (! $procurement->isApplicationOpen()) {
            abort(404);
        }

        $procurement->load(['documents' => fn ($query) => $query->bidderFacing()]);

        $form = $this->formResolver->activeFor($procurement);

        if ($form) {
            $form->ensureGlobalFields();
            $form->load('fields');
        }

        $existingSubmission = FormSubmission::where('procurement_id', $procurement->id)
            ->where('submitted_by', $user->id)
            ->where('status', '!=', FormSubmission::STATUS_WITHDRAWN)
            ->first();

        return view('vendor.procurements.show', [
            'procurement' => $procurement,
            'form' => $form,
            'existingSubmission' => $existingSubmission,
        ]);
    }

    public function downloadDocument(
        Request $request,
        Procurement $procurement,
        ProcurementDocument $document
    ) {
        $user = $request->user();
        $this->assertVendor($user);

        abort_if(($procurement->visibility_type ?? 'public') !== 'vendor_group', 404);
        $this->assertVendorCategoryAccess($user, $procurement);
        $procurement->autoCloseIfExpired();
        abort_if(! $procurement->isApplicationOpen(), 404);

        abort_unless((string) $document->procurement_id === (string) $procurement->id, 404);
        abort_unless($document->audience === ProcurementDocument::AUDIENCE_BIDDER, 404);

        $path = (string) $document->file_path;
        $expectedPrefix = "procurements/{$procurement->id}/documents/";
        abort_unless($path !== '' && str_starts_with($path, $expectedPrefix), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404, 'Procurement document file not found.');

        return $disk->download(
            $path,
            basename($document->original_name ?: $path),
            [
                'Content-Type' => $document->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]
        );
    }

    public function submit(
        Request $request,
        Procurement $procurement,
        ProcurementSubmissionScreeningAutomation $screeningAutomation,
        DynamicProcurementSubmissionValidation $submissionValidation,
    ) {
        $user = $request->user();
        $this->assertVendor($user);

        if (($procurement->visibility_type ?? 'public') !== 'vendor_group') {
            abort(404);
        }

        $this->assertVendorCategoryAccess($user, $procurement);
        $procurement->autoCloseIfExpired();

        if (! $procurement->isApplicationOpen()) {
            abort(403, 'This procurement is closed for applications.');
        }

        $existingSubmission = FormSubmission::where('procurement_id', $procurement->id)
            ->where('submitted_by', $user->id)
            ->where('status', '!=', FormSubmission::STATUS_WITHDRAWN)
            ->first();

        if ($existingSubmission) {
            return redirect()
                ->route('vendor.applications.edit', $existingSubmission)
                ->with('success', 'You already submitted this procurement. You can update your application here.');
        }

        $form = $this->formResolver->activeFor($procurement, true);

        $form->ensureGlobalFields();
        $form->load('fields');

        $fieldKeys = $form->fields->pluck('field_key')->all();
        if (in_array('official_name', $fieldKeys, true)) {
            $request->merge(['official_name' => $user->name ?? $user->email]);
        }
        if (in_array('official_email', $fieldKeys, true)) {
            $request->merge(['official_email' => $user->email]);
        }

        $submissionValidation->assertUploadEnvelope($request, $form);
        $request->validate($submissionValidation->rules($form));

        $submission = null;
        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $procurement, $form, $user, $submissionValidation, &$submission, &$storedPaths) {
                $lockedProcurement = Procurement::query()
                    ->whereKey($procurement->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless(($lockedProcurement->visibility_type ?? 'public') === 'vendor_group', 404);
                abort_unless(
                    $this->vendorDirectory->vendorCanAccess($user, $lockedProcurement),
                    404,
                );
                abort_unless($lockedProcurement->isApplicationOpen(), 409, 'This procurement is no longer open for applications.');
                $lockedForm = $this->formResolver->activeFor($lockedProcurement, true);
                abort_unless(
                    (string) $lockedForm->id === (string) $form->id,
                    409,
                    'The application form changed while it was open. Reload it before submitting.',
                );
                $lockedForm->load('fields');
                $lockedFieldKeys = $lockedForm->fields->pluck('field_key')->all();
                if (in_array('official_name', $lockedFieldKeys, true)) {
                    $request->merge(['official_name' => $user->name ?? $user->email]);
                }
                if (in_array('official_email', $lockedFieldKeys, true)) {
                    $request->merge(['official_email' => $user->email]);
                }
                $submissionValidation->assertUploadEnvelope($request, $lockedForm);
                $request->validate($submissionValidation->rules($lockedForm));
                User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $duplicate = FormSubmission::query()
                    ->where('procurement_id', $lockedProcurement->id)
                    ->where('submitted_by', $user->id)
                    ->where('status', '!=', FormSubmission::STATUS_WITHDRAWN)
                    ->lockForUpdate()
                    ->first();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'application' => ['An active application already exists. Open it from your applications workspace.'],
                    ]);
                }
                $submission = FormSubmission::create([
                    'procurement_id' => $lockedProcurement->id,
                    'form_id' => $lockedForm->id,
                    'submitted_by' => $user->id,
                    'status' => FormSubmission::STATUS_SUBMITTED,
                    'submitted_at' => now(),
                    'publication_version' => max(1, (int) $lockedProcurement->publication_version),
                ]);

                foreach ($lockedForm->fields as $field) {
                    $key = $field->field_key;
                    $value = null;

                    if (in_array($field->field_type, ['file', 'image'], true) && $request->hasFile($key)) {
                        $value = $this->submissionFiles->store($request->file($key));
                        $storedPaths[] = $value;
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
        } catch (\Throwable $exception) {
            $this->submissionFiles->deleteMany($storedPaths);
            throw $exception;
        }

        if ($submission) {
            $screeningAutomation->queueSubmission($submission->id);
        }

        return redirect()
            ->route('vendor.submissions')
            ->with('success', 'Application submitted successfully.');
    }

    private function assertVendor($user): void
    {
        if (! $user || $user->user_type !== 'vendor') {
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
        if (! $this->vendorDirectory->vendorCanAccess($user, $procurement)) {
            // Do not reveal whether a restricted opportunity exists to a
            // vendor outside its tenant/category/direct audience.
            abort(404);
        }
    }
}
