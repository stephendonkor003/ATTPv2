<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Mail\VendorApplicationReceived;
use App\Models\DynamicForm;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\User;
use App\Services\AccountSetupInvitationService;
use App\Services\DynamicProcurementFormResolver;
use App\Services\DynamicProcurementSubmissionFileService;
use App\Services\DynamicProcurementSubmissionValidation;
use App\Services\ProcurementSubmissionScreeningAutomation;
use App\Services\UserEmailMutationLock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicProcurementController extends Controller
{
    public function __construct(
        private readonly DynamicProcurementFormResolver $formResolver,
        private readonly DynamicProcurementSubmissionFileService $submissionFiles,
        private readonly UserEmailMutationLock $emailLock,
    ) {}

    /**
     * ===============================
     * PUBLIC PROCUREMENT LIST
     * ===============================
     */
    public function index()
    {
        $today = now()->toDateString();

        $procurements = Procurement::where('status', 'published')
            ->with([
                'thinkTankMember:id,name,logo_path',
                'activeForm.fields',
            ])
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
        abort_if(! $procurement->isApplicationOpen(), 404);
        $procurement->load([
            'documents' => fn ($query) => $query->bidderFacing(),
            'thinkTankMember:id,name,logo_path',
        ]);

        $form = $this->formResolver->activeFor($procurement); // allow null for public view

        if ($form) {
            $form->ensureGlobalFields();
            $form->load('fields');
        }

        return view('public.procurements.show', compact('procurement', 'form'));
    }

    public function downloadDocument(Procurement $procurement, ProcurementDocument $document)
    {
        if (($procurement->visibility_type ?? 'public') !== 'public') {
            abort(404);
        }

        $procurement->autoCloseIfExpired();
        abort_if(! $procurement->isApplicationOpen(), 404);
        abort_unless($document->audience === ProcurementDocument::AUDIENCE_BIDDER, 404);

        return $this->documentDownloadResponse($procurement, $document);
    }

    /**
     * ===============================
     * SUBMIT PROCUREMENT APPLICATION
     * ===============================
     */
    public function submit(
        Request $request,
        Procurement $procurement,
        ProcurementSubmissionScreeningAutomation $screeningAutomation,
        DynamicProcurementSubmissionValidation $submissionValidation,
    ) {
        if ($procurement->visibility_type && $procurement->visibility_type !== 'public') {
            abort(404);
        }

        $procurement->autoCloseIfExpired();
        abort_if(! $procurement->isApplicationOpen(), 404);

        $form = $this->formResolver->activeFor($procurement, true);

        $form->ensureGlobalFields();
        $form->load('fields');

        $submissionValidation->assertUploadEnvelope($request, $form);
        $request->validate($submissionValidation->rules($form));

        $officialName = Str::squish((string) $request->input('official_name'));
        $officialEmail = Str::lower(trim((string) $request->input('official_email')));
        if ($officialEmail === '') {
            return back()->withErrors([
                'official_email' => 'Official email is required to receive confirmation and access credentials.',
            ]);
        }
        $authenticatedUser = $request->user();
        if ($authenticatedUser?->user_type === 'vendor'
            && ! hash_equals(Str::lower(trim((string) $authenticatedUser->email)), $officialEmail)) {
            throw ValidationException::withMessages([
                'official_email' => ['Sign in with the vendor account that owns this email before applying.'],
            ]);
        }

        $newVendorAccount = false;
        $vendorUser = null;

        /*
        |--------------------------------------------------------------------------
        | SAVE SUBMISSION + VALUES
        |--------------------------------------------------------------------------
        */
        $submission = null;
        $storedPaths = [];
        try {
            $this->emailLock->run($officialEmail, function () use (
                $request,
                $procurement,
                $form,
                $officialName,
                $officialEmail,
                $submissionValidation,
                &$newVendorAccount,
                &$vendorUser,
                &$submission,
                &$storedPaths,
            ): void {
                DB::transaction(function () use (
                    $request,
                    $procurement,
                    $form,
                    $officialName,
                    $officialEmail,
                    $submissionValidation,
                    &$newVendorAccount,
                    &$vendorUser,
                    &$submission,
                    &$storedPaths,
                ): void {
                    $lockedProcurement = Procurement::query()
                        ->whereKey($procurement->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    abort_unless(($lockedProcurement->visibility_type ?? 'public') === 'public', 404);
                    abort_unless($lockedProcurement->isApplicationOpen(), 409, 'This procurement is no longer open for applications.');
                    $lockedForm = $this->formResolver->activeFor($lockedProcurement, true);
                    abort_unless(
                        (string) $lockedForm->id === (string) $form->id,
                        409,
                        'The application form changed while it was open. Reload it before submitting.',
                    );
                    $lockedForm->load('fields');
                    $submissionValidation->assertUploadEnvelope($request, $lockedForm);
                    $request->validate($submissionValidation->rules($lockedForm));

                    $identityMatches = User::query()
                        ->whereRaw('LOWER(TRIM(email)) = ?', [$officialEmail])
                        ->orderBy('id')
                        ->limit(2)
                        ->lockForUpdate()
                        ->get();
                    if ($identityMatches->count() > 1) {
                        throw ValidationException::withMessages([
                            'official_email' => ['This email identity requires administrator reconciliation before it can be used.'],
                        ]);
                    }

                    $vendorUser = $identityMatches->first();
                    if ($vendorUser && $vendorUser->user_type !== 'vendor') {
                        throw ValidationException::withMessages([
                            'official_email' => ['This email belongs to an internal account and cannot be used for procurement submissions.'],
                        ]);
                    }
                    $authenticatedUser = $request->user();
                    if ($vendorUser && (! $authenticatedUser
                        || $authenticatedUser->user_type !== 'vendor'
                        || (string) $authenticatedUser->id !== (string) $vendorUser->id)) {
                        throw ValidationException::withMessages([
                            'official_email' => ['This email already has an account. Sign in to that vendor account before applying.'],
                        ]);
                    }
                    if ($vendorUser?->is_blacklisted) {
                        throw ValidationException::withMessages([
                            'official_email' => ['This vendor has been blacklisted and cannot submit procurement applications.'],
                        ]);
                    }
                    if ($vendorUser?->is_disabled) {
                        throw ValidationException::withMessages([
                            'official_email' => ['This vendor account is disabled. Please contact the administrator.'],
                        ]);
                    }

                    if (! $vendorUser) {
                        $vendorUser = User::query()->create([
                            'name' => $officialName ?: $officialEmail,
                            'email' => $officialEmail,
                            'password' => app(AccountSetupInvitationService::class)->unknownPasswordHash(),
                            'user_type' => 'vendor',
                            'must_change_password' => true,
                        ]);
                        $newVendorAccount = true;
                    } else {
                        $request->merge([
                            'official_name' => $vendorUser->name ?: $vendorUser->email,
                            'official_email' => $vendorUser->email,
                        ]);
                    }

                    $alreadySubmitted = FormSubmission::query()
                        ->where('procurement_id', $lockedProcurement->id)
                        ->where('submitted_by', $vendorUser->id)
                        ->where('status', '!=', FormSubmission::STATUS_WITHDRAWN)
                        ->lockForUpdate()
                        ->first();
                    if ($alreadySubmitted) {
                        throw ValidationException::withMessages([
                            'official_email' => ['You already have an active application. Sign in to the vendor portal to review, resubmit or withdraw it.'],
                        ]);
                    }

                    $submission = FormSubmission::create([
                        'procurement_id' => $lockedProcurement->id,
                        'form_id' => $lockedForm->id,
                        'submitted_by' => $vendorUser->id,
                        'status' => FormSubmission::STATUS_SUBMITTED,
                        'submitted_at' => now(),
                        'publication_version' => max(1, (int) $lockedProcurement->publication_version),
                    ]);

                    foreach ($lockedForm->fields as $field) {

                        $key = $field->field_key;
                        $value = null;

                        // FILE
                        if (in_array($field->field_type, ['file', 'image'], true) && $request->hasFile($key)) {
                            $value = $this->submissionFiles->store($request->file($key));
                            $storedPaths[] = $value;
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
                            'field_key' => $key,
                            'value' => $value,
                        ]);
                    }
                });
            });
        } catch (\Throwable $exception) {
            $this->submissionFiles->deleteMany($storedPaths);
            throw $exception;
        }

        if ($submission) {
            $screeningAutomation->queueSubmission($submission->id);
        }

        $invitationSent = ! $newVendorAccount
            || app(AccountSetupInvitationService::class)->send(
                $vendorUser,
                AccountSetupInvitationService::PURPOSE_VENDOR,
            );

        $confirmationDispatched = false;
        if ($vendorUser && $submission) {
            try {
                Mail::to($vendorUser->email)
                    ->queue(new VendorApplicationReceived($procurement, $submission, $vendorUser));
                $confirmationDispatched = true;
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $message = $confirmationDispatched
            ? 'Application submitted successfully. A confirmation email was dispatched.'
            : 'Application submitted successfully, but the confirmation email could not be dispatched. Your application remains saved.';
        if ($newVendorAccount) {
            $message .= $invitationSent
                ? ' A separate secure account setup link was sent to the official email address.'
                : ' The secure account setup link could not be delivered; use Forgot password or contact support before signing in.';
        }

        return back()->with('success', $message);
    }

    private function documentDownloadResponse(Procurement $procurement, ProcurementDocument $document)
    {
        abort_unless((string) $document->procurement_id === (string) $procurement->id, 404);

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
                'Cache-Control' => 'public, max-age=300',
            ]
        );
    }
}
