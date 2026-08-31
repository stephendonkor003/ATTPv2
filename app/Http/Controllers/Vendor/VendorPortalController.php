<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ConsortiumThinkTank;
use App\Models\EoiReportCommunicationRecipient;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\Procurement;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPurchaseOrder;
use App\Models\User;
use App\Models\VendorDocument;
use App\Models\VendorInformationRequest;
use App\Models\VendorMessage;
use App\Models\VendorReport;
use App\Notifications\VendorRequestCreatedNotification;
use App\Services\EvaluationReworkGuard;
use App\Services\ProcurementSubmissionScreeningAutomation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class VendorPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);
        [$dateFrom, $dateTo] = $this->dashboardPeriod($request);

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
        ] = $this->loadVendorOverview($user, $dateFrom, $dateTo);

        $purchaseOrders = $this->applyDateRange(
            ProcurementPurchaseOrder::with([
                'procurement',
                'lineItemEvidence',
                'purchaseRequest.items',
                'budgetCommitment.purchaseRequest.items',
            ])->where('vendor_id', $user->id),
            $dateFrom,
            $dateTo
        )->orderByDesc('issued_at')->latest()->get();

        $reports = $this->applyDateRange(
            VendorReport::with(['procurement', 'purchaseOrder'])
                ->where('user_id', $user->id),
            $dateFrom,
            $dateTo
        )->latest()->get();

        $documents = $this->applyDateRange(
            VendorDocument::where('user_id', $user->id),
            $dateFrom,
            $dateTo
        )->latest()->get();

        $invoices = $this->applyDateRange(
            ProcurementInvoice::with('procurement')
                ->where('vendor_id', $user->id),
            $dateFrom,
            $dateTo
        )->latest()->get();

        $disbursements = $this->applyDateRange(
            ProcurementDisbursement::with('procurement')
                ->where('vendor_id', $user->id),
            $dateFrom,
            $dateTo
        )->latest()->get();

        $paidDisbursements = $disbursements
            ->filter(fn (ProcurementDisbursement $disbursement) => $disbursement->paid_at
                && in_array(strtolower((string) $disbursement->status), ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES, true));

        $dashboardStats = [
            'applications' => $submissions->count(),
            'open_applications' => $openCount,
            'purchase_orders' => $purchaseOrders->count(),
            'reports' => $reports->count(),
            'documents' => $documents->count(),
            'invoice_amount' => (float) $invoices->sum(fn (ProcurementInvoice $invoice) => (float) $invoice->amount),
            'payments_received' => (float) $paidDisbursements->sum(fn (ProcurementDisbursement $payment) => (float) $payment->amount),
            'pending_reviews' => $purchaseOrders->where('status', 'issued')->count()
                + $reports->where('status', 'submitted')->count(),
        ];

        $statusChart = [
            'labels' => ['Applications', 'Purchase Orders', 'Reports', 'Documents', 'Payments'],
            'data' => [
                $submissions->count(),
                $purchaseOrders->count(),
                $reports->count(),
                $documents->count(),
                $paidDisbursements->count(),
            ],
        ];

        $chartMonths = $this->chartMonths($dateFrom, $dateTo);
        $cashflowChart = [
            'labels' => collect($chartMonths)->pluck('label')->all(),
            'invoices' => $this->monthlySeries(
                $invoices,
                $chartMonths,
                fn (ProcurementInvoice $invoice) => $invoice->invoice_month ?: $invoice->created_at,
                fn (ProcurementInvoice $invoice) => (float) $invoice->amount
            ),
            'payments' => $this->monthlySeries(
                $paidDisbursements,
                $chartMonths,
                fn (ProcurementDisbursement $payment) => $payment->paid_at ?: $payment->created_at,
                fn (ProcurementDisbursement $payment) => (float) $payment->amount
            ),
        ];

        $activityChart = [
            'labels' => collect($chartMonths)->pluck('label')->all(),
            'applications' => $this->monthlySeries(
                $submissions,
                $chartMonths,
                fn (FormSubmission $submission) => $submission->submitted_at ?: $submission->created_at
            ),
            'requests' => $this->monthlySeries(
                $purchaseOrders,
                $chartMonths,
                fn (ProcurementPurchaseOrder $purchaseOrder) => $purchaseOrder->issued_at ?: $purchaseOrder->created_at
            ),
            'reports' => $this->monthlySeries(
                $reports,
                $chartMonths,
                fn (VendorReport $report) => $report->created_at
            ),
            'documents' => $this->monthlySeries(
                $documents,
                $chartMonths,
                fn (VendorDocument $document) => $document->created_at
            ),
        ];

        $recentActivity = $this->recentActivity($submissions, $purchaseOrders, $reports, $documents, $paidDisbursements);
        $evaluationNotices = EoiReportCommunicationRecipient::query()
            ->where('user_id', $user->getKey())
            ->with(['communication.procurement' => fn ($query) => $query->withTrashed()])
            ->latest()
            ->limit(3)
            ->get();
        $thinkTankMember = ConsortiumThinkTank::with('consortium')
            ->where(function ($query) use ($user) {
                $query->where('vendor_user_id', $user->id)
                    ->orWhere('portal_user_id', $user->id);
            })
            ->first();

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
            'purchaseOrders' => $purchaseOrders,
            'reports' => $reports,
            'documents' => $documents,
            'invoices' => $invoices,
            'disbursements' => $disbursements,
            'dashboardStats' => $dashboardStats,
            'statusChart' => $statusChart,
            'cashflowChart' => $cashflowChart,
            'activityChart' => $activityChart,
            'recentActivity' => $recentActivity,
            'evaluationNotices' => $evaluationNotices,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'thinkTankMember' => $thinkTankMember,
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
            'payment_method_preference' => 'nullable|string|in:'.implode(',', $methods),
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

    public function updateApplication(
        Request $request,
        FormSubmission $submission,
        ProcurementSubmissionScreeningAutomation $screeningAutomation,
    ) {
        $user = $request->user();
        $this->assertVendor($user);
        $this->assertSubmissionOwnership($submission, $user->id);
        $this->assertSubmissionOpen($submission);

        $form = $submission->form;
        $form->ensureGlobalFields();
        $form->load('fields');
        $submission->load('values');

        $existingValues = $submission->values->keyBy('field_key');

        $isRecallResponse = $submission->status === FormSubmission::STATUS_REVISION_REQUESTED;
        $rules = [
            'vendor_response' => [
                $isRecallResponse ? 'required' : 'nullable',
                'string',
                'min:5',
                'max:2000',
            ],
        ];
        foreach ($form->fields as $field) {
            $key = $field->field_key;
            $required = $field->is_required ? 'required' : 'nullable';
            $configuration = (array) $field->validation_rules;
            $options = $field->optionValues();
            $maxLength = min(20000, max(1, (int) ($configuration['max_length'] ?? ($field->field_type === 'textarea' ? 20000 : 255))));

            if (in_array($field->field_type, ['file', 'image'], true) && $field->is_required && $existingValues->get($key)) {
                $required = 'nullable';
            }

            switch ($field->field_type) {
                case 'email':
                    $rules[$key] = [$required, 'email:rfc', 'max:'.$maxLength];
                    break;
                case 'file':
                case 'image':
                    $defaultExtensions = $field->field_type === 'image'
                        ? ['jpg', 'jpeg', 'png', 'webp']
                        : ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'zip'];
                    $extensions = array_values(array_intersect((array) ($configuration['allowed_extensions'] ?? $defaultExtensions), $defaultExtensions));
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

        DB::transaction(function () use (
            $request,
            $submission,
            $form,
            $existingValues,
            $validated,
            $screeningAutomation,
        ): void {
            foreach ($form->fields as $field) {
                $key = $field->field_key;
                $value = null;

                if (in_array($field->field_type, ['file', 'image'], true)) {
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

            $submission->update([
                'status' => FormSubmission::STATUS_SUBMITTED,
                'vendor_response' => trim((string) ($validated['vendor_response'] ?? '')) ?: null,
                'publication_version' => max(1, (int) $submission->procurement?->publication_version),
                'submitted_at' => now(),
                'resubmitted_at' => now(),
                'withdrawn_at' => null,
                'withdrawal_reason' => null,
            ]);

            $screeningAutomation->queueSubmission(
                $submission->id,
                checkedVia: 'auto',
                force: true,
            );
        });

        return redirect()
            ->route('vendor.submissions')
            ->with('success', $isRecallResponse ? 'Application response submitted successfully.' : 'Application updated and resubmitted successfully.');
    }

    public function withdrawApplication(
        Request $request,
        FormSubmission $submission,
        EvaluationReworkGuard $reworkGuard
    ) {
        $user = $request->user();
        $this->assertVendor($user);
        $this->assertSubmissionOwnership($submission, $user->id);
        $submission->load('procurement');
        $procurement = $submission->procurement;

        abort_if($submission->isWithdrawn(), 422, 'This application has already been withdrawn.');
        abort_unless($procurement && in_array($procurement->status, ['published', 'recalled'], true), 403, 'This application can no longer be withdrawn.');
        abort_if($procurement->awarded_submission_id, 403, 'An awarded procurement application cannot be withdrawn.');

        $data = $request->validate([
            'withdrawal_reason' => 'required|string|min:5|max:1000',
        ]);

        DB::transaction(function () use ($submission, $data, $reworkGuard): void {
            $lockedProcurement = Procurement::query()
                ->withTrashed()
                ->whereKey($submission->procurement_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedSubmission = FormSubmission::query()
                ->whereKey($submission->getKey())
                ->where('procurement_id', $lockedProcurement->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($lockedSubmission->isWithdrawn(), 422, 'This application has already been withdrawn.');
            abort_unless(
                in_array($lockedProcurement->status, ['published', 'recalled'], true),
                403,
                'This application can no longer be withdrawn.'
            );
            abort_if(
                filled($lockedProcurement->awarded_submission_id),
                403,
                'An awarded procurement application cannot be withdrawn.'
            );
            $reworkGuard->assertApplicantStatusCanChange($lockedSubmission);

            $lockedSubmission->update([
                'status' => FormSubmission::STATUS_WITHDRAWN,
                'withdrawn_at' => now(),
                'withdrawal_reason' => trim($data['withdrawal_reason']),
                'vendor_response' => null,
            ]);
        });

        return redirect()
            ->route('vendor.submissions')
            ->with('success', 'Application withdrawn. If the opportunity is open, you may submit a new application from the public procurement page.');
    }

    public function storeMessage(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|min:5',
            'procurement_id' => [
                'nullable',
                Rule::exists('procurements', 'id')->whereNull('deleted_at'),
            ],
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
            'procurement_id' => [
                'nullable',
                Rule::exists('procurements', 'id')->whereNull('deleted_at'),
            ],
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

    private function loadVendorOverview(User $user, ?Carbon $dateFrom = null, ?Carbon $dateTo = null): array
    {
        $submissions = $this->applyDateRange(
            FormSubmission::with('procurement')
                ->where('submitted_by', $user->id),
            $dateFrom,
            $dateTo,
            'submitted_at'
        )
            ->latest('submitted_at')
            ->get();

        $submissions->transform(function (FormSubmission $submission) {
            $procurement = $submission->procurement;

            if ($procurement) {
                $procurement->autoCloseIfExpired();
            }

            $submission->is_open = $procurement?->isApplicationOpen() ?? false;
            $submission->is_recalled = $procurement?->status === 'recalled';
            $submission->can_withdraw = ! $submission->isWithdrawn()
                && in_array($procurement?->status, ['published', 'recalled'], true)
                && ! $procurement?->awarded_submission_id;
            $submission->can_apply_again = $submission->isWithdrawn() && ($procurement?->isApplicationOpen() ?? false);
            $submission->application_end_date = $procurement?->application_end_date?->toDateString();
            $submission->procurement_reference = $procurement?->reference_no;

            return $submission;
        });

        $statusCounts = $submissions->groupBy('status')->map->count();
        $openCount = $submissions->where('is_open', true)->count();
        $closedCount = $submissions->count() - $openCount;

        $messages = $this->applyDateRange(
            VendorMessage::with('procurement')
                ->where('user_id', $user->id),
            $dateFrom,
            $dateTo
        )
            ->latest()
            ->get();

        $informationRequests = $this->applyDateRange(
            VendorInformationRequest::with('procurement')
                ->where('user_id', $user->id),
            $dateFrom,
            $dateTo
        )
            ->latest()
            ->get();

        $vendorProcurements = $submissions->pluck('procurement')
            ->filter()
            ->unique('id')
            ->values();

        $notifications = $this->applyDateRange(
            $user->notifications(),
            $dateFrom,
            $dateTo
        )
            ->latest()
            ->take(5)
            ->get();

        $awardedProcurements = $this->applyDateRange(
            Procurement::where('status', 'awarded')
                ->where('awarded_vendor_id', $user->id),
            $dateFrom,
            $dateTo,
            'awarded_at'
        )
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

    private function dashboardPeriod(Request $request): array
    {
        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        return [
            ! empty($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : null,
            ! empty($validated['date_to']) ? Carbon::parse($validated['date_to'])->endOfDay() : null,
        ];
    }

    private function applyDateRange($query, ?Carbon $dateFrom, ?Carbon $dateTo, string $column = 'created_at')
    {
        if ($dateFrom) {
            $query->where($column, '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->where($column, '<=', $dateTo);
        }

        return $query;
    }

    private function chartMonths(?Carbon $dateFrom, ?Carbon $dateTo): array
    {
        $end = ($dateTo ?: now())->copy()->startOfMonth();
        $start = ($dateFrom ?: $end->copy()->subMonths(5))->copy()->startOfMonth();

        if ($start->diffInMonths($end) > 17) {
            $start = $end->copy()->subMonths(17);
        }

        $months = [];
        $cursor = $start->copy();

        while ($cursor <= $end) {
            $months[] = [
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('M Y'),
            ];
            $cursor->addMonth();
        }

        return $months;
    }

    private function monthlySeries($records, array $months, callable $dateResolver, ?callable $valueResolver = null): array
    {
        return collect($months)->map(function (array $month) use ($records, $dateResolver, $valueResolver) {
            return round((float) $records->sum(function ($record) use ($month, $dateResolver, $valueResolver) {
                $date = $dateResolver($record);

                if (! $date || $date->format('Y-m') !== $month['key']) {
                    return 0;
                }

                return $valueResolver ? $valueResolver($record) : 1;
            }), 2);
        })->all();
    }

    private function recentActivity($submissions, $purchaseOrders, $reports, $documents, $paidDisbursements)
    {
        return collect()
            ->merge($submissions->map(fn (FormSubmission $submission) => [
                'type' => 'Application',
                'title' => $submission->procurement?->title ?: 'Procurement application',
                'detail' => ucfirst($submission->status ?? 'pending'),
                'date' => $submission->submitted_at ?: $submission->created_at,
                'icon' => 'feather-file-text',
            ]))
            ->merge($purchaseOrders->map(fn (ProcurementPurchaseOrder $purchaseOrder) => [
                'type' => 'Purchase Order',
                'title' => $purchaseOrder->po_title ?: 'Purchase Order',
                'detail' => $purchaseOrder->reference_no,
                'date' => $purchaseOrder->issued_at ?: $purchaseOrder->created_at,
                'icon' => 'feather-file-text',
            ]))
            ->merge($reports->map(fn (VendorReport $report) => [
                'type' => 'Report',
                'title' => $report->title,
                'detail' => $report->reference_no,
                'date' => $report->created_at,
                'icon' => 'feather-clipboard',
            ]))
            ->merge($documents->map(fn (VendorDocument $document) => [
                'type' => 'Document',
                'title' => $document->title,
                'detail' => $document->file_name,
                'date' => $document->created_at,
                'icon' => 'feather-folder',
            ]))
            ->merge($paidDisbursements->map(fn (ProcurementDisbursement $payment) => [
                'type' => 'Payment',
                'title' => $payment->reference_no,
                'detail' => $payment->currency.' '.number_format((float) $payment->amount, 2),
                'date' => $payment->paid_at ?: $payment->created_at,
                'icon' => 'feather-dollar-sign',
            ]))
            ->filter(fn ($item) => $item['date'])
            ->sortByDesc(fn ($item) => $item['date']->timestamp)
            ->take(8)
            ->values();
    }

    private function assertSubmissionOwnership(FormSubmission $submission, string $userId): void
    {
        if ($submission->submitted_by !== $userId) {
            abort(403, 'You do not have access to this application.');
        }
    }

    private function assertSubmissionOpen(FormSubmission $submission): void
    {
        if ($submission->isWithdrawn()) {
            abort(403, 'This application was withdrawn and cannot be edited. Submit a new application if the opportunity is open.');
        }

        $submission->load('procurement');
        $procurement = $submission->procurement;

        if ($procurement) {
            $procurement->autoCloseIfExpired();
        }

        if (! $procurement || ! $procurement->isApplicationOpen()) {
            abort(403, 'This application is closed for updates.');
        }
    }

    private function assertProcurementOwnership(string $userId, ?string $procurementId): void
    {
        if (! $procurementId) {
            return;
        }

        $owns = FormSubmission::where('submitted_by', $userId)
            ->where('procurement_id', $procurementId)
            ->exists();

        if (! $owns) {
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

        if (! $request) {
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
