<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\DynamicForm;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\Procurement;
use App\Models\User;
use App\Models\VendorInformationRequest;
use App\Models\VendorMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use App\Notifications\VendorRequestCreatedNotification;

class VendorPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        [
            $submissions,
            $statusCounts,
            $openCount,
            $closedCount,
            $messages,
            $informationRequests,
            $vendorProcurements,
            $notifications,
            $awardedProcurements,
        ] = $this->loadVendorOverview($user);

        return view('vendor.dashboard', [
            'submissions' => $submissions,
            'statusCounts' => $statusCounts,
            'openCount' => $openCount,
            'closedCount' => $closedCount,
            'messages' => $messages,
            'informationRequests' => $informationRequests,
            'vendorProcurements' => $vendorProcurements,
            'notifications' => $notifications,
            'awardedProcurements' => $awardedProcurements,
        ]);
    }

    public function clarifications(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        [
            $submissions,
            $statusCounts,
            $openCount,
            $closedCount,
            $messages,
            $informationRequests,
            $vendorProcurements,
            $notifications,
            $awardedProcurements,
        ] = $this->loadVendorOverview($user);

        return view('vendor.clarifications', [
            'submissions' => $submissions,
            'statusCounts' => $statusCounts,
            'openCount' => $openCount,
            'closedCount' => $closedCount,
            'messages' => $messages,
            'informationRequests' => $informationRequests,
            'vendorProcurements' => $vendorProcurements,
            'notifications' => $notifications,
            'awardedProcurements' => $awardedProcurements,
        ]);
    }

    public function submissions(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        [
            $submissions,
            $statusCounts,
            $openCount,
            $closedCount,
            $messages,
            $informationRequests,
            $vendorProcurements,
            $notifications,
            $awardedProcurements,
        ] = $this->loadVendorOverview($user);

        return view('vendor.submissions.index', [
            'submissions' => $submissions,
            'statusCounts' => $statusCounts,
            'openCount' => $openCount,
            'closedCount' => $closedCount,
        ]);
    }

    public function paymentDetails(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $paymentMethods = $this->paymentMethods();

        return view('vendor.payment-details', [
            'user' => $user,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function updatePaymentDetails(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $methods = $this->paymentMethods();

        $data = $request->validate([
            'payment_method_preference' => 'nullable|string|in:' . implode(',', $methods),
            'payment_bank_name' => 'nullable|string|max:255',
            'payment_account_name' => 'nullable|string|max:255',
            'payment_account_number' => 'nullable|string|max:255',
            'payment_swift_code' => 'nullable|string|max:255',
            'payment_iban' => 'nullable|string|max:255',
            'payment_mobile_provider' => 'nullable|string|max:255',
            'payment_mobile_number' => 'nullable|string|max:255',
            'payment_tax_id' => 'nullable|string|max:255',
            'payment_address' => 'nullable|string|max:255',
        ]);

        $user->update($data);

        return back()->with('success', 'Payment details updated successfully.');
    }

    public function editApplication(Request $request, FormSubmission $submission)
    {
        $user = $request->user();
        $this->assertVendor($user);
        $this->assertSubmissionOwnership($submission, $user->id);
        $this->assertSubmissionOpen($submission);

        $form = $submission->form;
        $form->ensureGlobalFields();
        $form->load('fields');
        $submission->load('values', 'procurement');

        $values = $submission->values->keyBy('field_key');

        return view('vendor.applications.edit', [
            'submission' => $submission,
            'form' => $form,
            'values' => $values,
        ]);
    }

    public function updateApplication(Request $request, FormSubmission $submission)
    {
        $user = $request->user();
        $this->assertVendor($user);
        $this->assertSubmissionOwnership($submission, $user->id);
        $this->assertSubmissionOpen($submission);

        $form = $submission->form;
        $form->ensureGlobalFields();
        $form->load('fields');
        $submission->load('values');

        $existingValues = $submission->values->keyBy('field_key');

        $rules = [];
        foreach ($form->fields as $field) {
            $key = $field->field_key;
            $required = $field->is_required ? 'required' : 'nullable';

            if ($field->field_type === 'file' && $field->is_required && $existingValues->get($key)) {
                $required = 'nullable';
            }

            switch ($field->field_type) {
                case 'email':
                    $rules[$key] = "{$required}|email";
                    break;
                case 'file':
                    $rules[$key] = "{$required}|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip|max:20480";
                    break;
                case 'checkbox':
                case 'multiselect':
                    $rules[$key] = "{$required}|array";
                    break;
                case 'number':
                    $rules[$key] = "{$required}|numeric";
                    break;
                case 'url':
                    $rules[$key] = "{$required}|url";
                    break;
                default:
                    $rules[$key] = $required;
            }
        }

        $validated = $request->validate($rules);

        foreach ($form->fields as $field) {
            $key = $field->field_key;
            $value = null;

            if ($field->field_type === 'file') {
                if ($request->hasFile($key)) {
                    $value = $request->file($key)->store('procurement_submissions');
                } else {
                    $value = $existingValues->get($key)?->value;
                }
            } elseif (is_array($request->input($key))) {
                $value = json_encode(array_values($request->input($key)));
            } else {
                $value = $request->input($key);
            }

            FormSubmissionValue::updateOrCreate(
                [
                    'submission_id' => $submission->id,
                    'field_key' => $key,
                ],
                [
                    'value' => $value,
                ]
            );
        }

        $submission->screening()->delete();
        $submission->touch();

        return redirect()
            ->route('vendor.dashboard')
            ->with('success', 'Application updated successfully.');
    }

    public function storeMessage(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:5',
            'procurement_id' => 'nullable|exists:procurements,id',
        ]);

        $this->assertProcurementOwnership($user->id, $data['procurement_id'] ?? null);

        $message = VendorMessage::create([
            'user_id' => $user->id,
            'procurement_id' => $data['procurement_id'] ?? null,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'status' => 'open',
        ]);

        $this->notifyVendorRequestAdmins('message', $message);

        return back()->with('success', 'Message sent successfully.');
    }

    public function storeInformationRequest(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $data = $request->validate([
            'request_topic' => 'required|string|max:255',
            'details' => 'required|string|min:5',
            'procurement_id' => 'nullable|exists:procurements,id',
        ]);

        $this->assertProcurementOwnership($user->id, $data['procurement_id'] ?? null);

        $infoRequest = VendorInformationRequest::create([
            'user_id' => $user->id,
            'procurement_id' => $data['procurement_id'] ?? null,
            'request_topic' => $data['request_topic'],
            'details' => $data['details'],
            'status' => 'open',
        ]);

        $this->notifyVendorRequestAdmins('information', $infoRequest);

        return back()->with('success', 'Information request sent successfully.');
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

    private function loadVendorOverview(User $user): array
    {
        $submissions = FormSubmission::with('procurement')
            ->where('submitted_by', $user->id)
            ->latest('submitted_at')
            ->get();

        $submissions->transform(function (FormSubmission $submission) {
            $procurement = $submission->procurement;

            if ($procurement) {
                $procurement->autoCloseIfExpired();
            }

            $submission->is_open = $procurement?->isApplicationOpen() ?? false;
            $submission->application_end_date = $procurement?->application_end_date?->toDateString();
            $submission->procurement_reference = $procurement?->reference_no;

            return $submission;
        });

        $statusCounts = $submissions->groupBy('status')->map->count();
        $openCount = $submissions->where('is_open', true)->count();
        $closedCount = $submissions->count() - $openCount;

        $messages = VendorMessage::with('procurement')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $informationRequests = VendorInformationRequest::with('procurement')
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $vendorProcurements = $submissions->pluck('procurement')
            ->filter()
            ->unique('id')
            ->values();

        $notifications = $user->notifications()
            ->latest()
            ->take(5)
            ->get();

        $awardedProcurements = Procurement::where('status', 'awarded')
            ->where('awarded_vendor_id', $user->id)
            ->orderByDesc('awarded_at')
            ->get();

        return [
            $submissions,
            $statusCounts,
            $openCount,
            $closedCount,
            $messages,
            $informationRequests,
            $vendorProcurements,
            $notifications,
            $awardedProcurements,
        ];
    }

    private function assertSubmissionOwnership(FormSubmission $submission, string $userId): void
    {
        if ($submission->submitted_by !== $userId) {
            abort(403, 'You do not have access to this application.');
        }
    }

    private function assertSubmissionOpen(FormSubmission $submission): void
    {
        $submission->load('procurement');
        $procurement = $submission->procurement;

        if ($procurement) {
            $procurement->autoCloseIfExpired();
        }

        if (!$procurement || !$procurement->isApplicationOpen()) {
            abort(403, 'This application is closed for updates.');
        }
    }

    private function assertProcurementOwnership(string $userId, ?string $procurementId): void
    {
        if (!$procurementId) {
            return;
        }

        $owns = FormSubmission::where('submitted_by', $userId)
            ->where('procurement_id', $procurementId)
            ->exists();

        if (!$owns) {
            abort(403, 'You do not have access to that procurement.');
        }
    }

    private function notifyVendorRequestAdmins(string $type, $request): void
    {
        $permission = 'vendor.requests.manage';
        $recipients = User::where(function ($query) use ($permission) {
            $query->whereHas('permissions', function ($perm) use ($permission) {
                $perm->where('name', $permission);
            })->orWhereHas('role.permissions', function ($perm) use ($permission) {
                $perm->where('name', $permission);
            });
        })->get();

        if ($recipients->isEmpty()) {
            return;
        }

        if (!$request) {
            return;
        }

        try {
            Notification::send($recipients, new VendorRequestCreatedNotification($type, $request));
        } catch (\Throwable $exception) {
            logger()->error('Vendor request notification failed.', [
                'type' => $type,
                'request_id' => $request->id ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function paymentMethods(): array
    {
        return [
            'Bank Transfer',
            'Cheque',
            'Cash',
            'Mobile Money',
            'Card Payment',
            'Wire Transfer',
            'ACH',
            'RTGS',
            'SWIFT',
            'Other',
        ];
    }
}
