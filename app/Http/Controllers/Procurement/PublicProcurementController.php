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
use App\Services\ProcurementSubmissionScreeningAutomation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'documents',
            'thinkTankMember:id,name,logo_path',
        ]);

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

    public function downloadDocument(Procurement $procurement, ProcurementDocument $document)
    {
        if (($procurement->visibility_type ?? 'public') !== 'public') {
            abort(404);
        }

        $procurement->autoCloseIfExpired();
        abort_if(! $procurement->isApplicationOpen(), 404);

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
    ) {
        if ($procurement->visibility_type && $procurement->visibility_type !== 'public') {
            abort(404);
        }

        $procurement->autoCloseIfExpired();
        abort_if(! $procurement->isApplicationOpen(), 404);

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
            $configuration = (array) $field->validation_rules;
            $options = $field->optionValues();
            $maxLength = min(20000, max(1, (int) ($configuration['max_length'] ?? ($field->field_type === 'textarea' ? 20000 : 255))));

            switch ($field->field_type) {

                case 'email':
                    $rules[$key] = [$required, 'email:rfc', 'max:'.$maxLength];
                    break;

                case 'file':
                case 'image':
                    $defaultExtensions = $field->field_type === 'image'
                        ? ['jpg', 'jpeg', 'png', 'webp']
                        : ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'zip'];
                    $extensions = array_values(array_intersect(
                        (array) ($configuration['allowed_extensions'] ?? $defaultExtensions),
                        $defaultExtensions
                    ));
                    $maxKilobytes = min(20480, max(1024, (int) ($configuration['max_file_size_mb'] ?? 10) * 1024));
                    $rules[$key] = [
                        $required,
                        'file',
                        ...($field->field_type === 'image' ? ['image'] : []),
                        'mimes:'.implode(',', $extensions ?: $defaultExtensions),
                        'max:'.$maxKilobytes,
                    ];
                    break;

                case 'checkbox':
                case 'multiselect':
                    $rules[$key] = [$required, 'array', ...($field->is_required ? ['min:1'] : [])];
                    $rules[$key.'.*'] = ['string', Rule::in($options)];
                    break;

                case 'number':
                    $rules[$key] = [
                        $required,
                        'numeric',
                        ...(array_key_exists('min', $configuration) ? ['min:'.$configuration['min']] : []),
                        ...(array_key_exists('max', $configuration) ? ['max:'.$configuration['max']] : []),
                    ];
                    break;

                case 'url':
                    $rules[$key] = [$required, 'url:http,https', 'max:'.$maxLength];
                    break;

                case 'tel':
                    $rules[$key] = [$required, 'string', 'max:'.$maxLength];
                    break;

                case 'date':
                    $rules[$key] = [$required, 'date_format:Y-m-d'];
                    break;

                case 'time':
                    $rules[$key] = [$required, 'date_format:H:i'];
                    break;

                case 'datetime-local':
                    $rules[$key] = [$required, 'date_format:Y-m-d\\TH:i'];
                    break;

                case 'select':
                case 'radio':
                    $rules[$key] = [$required, 'string', Rule::in($options)];
                    break;

                case 'boolean':
                    $rules[$key] = [$required, 'accepted'];
                    break;

                case 'textarea':
                case 'text':
                    $rules[$key] = [$required, 'string', 'max:'.$maxLength];
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
                ->where('status', '!=', FormSubmission::STATUS_WITHDRAWN)
                ->exists();

            if ($alreadySubmitted) {
                return back()->withErrors([
                    'official_email' => 'You already have an active application. Sign in to the vendor portal to review, resubmit or withdraw it.',
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
                'form_id' => $form->id,
                'submitted_by' => $vendorUser?->id,
                'status' => FormSubmission::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'publication_version' => max(1, (int) $procurement->publication_version),
            ]);

            foreach ($form->fields as $field) {

                $key = $field->field_key;
                $value = null;

                // FILE
                if (in_array($field->field_type, ['file', 'image'], true) && $request->hasFile($key)) {
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
                    'field_key' => $key,
                    'value' => $value,
                ]);
            }
        });

        if ($submission) {
            $screeningAutomation->queueSubmission($submission->id);
        }

        if ($vendorUser && $submission) {
            Mail::to($vendorUser->email)
                ->queue(new VendorApplicationReceived($procurement, $submission, $vendorUser, $temporaryPassword));
        }

        return back()->with('success', 'Application submitted successfully. Your login credentials have been emailed to the official email address provided.');
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
