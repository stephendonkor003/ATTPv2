<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Mail\VendorDisbursementReceipt;
use App\Models\ConsortiumThinkTank;
use App\Models\ProcurementAuditLog;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementDisbursementSubmissionBatch;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ProcurementPurchaseOrderItemEvidence;
use App\Models\PurchaseRequestItem;
use App\Services\ProcurementDisbursementHandoffNotificationService;
use App\Services\ProcurementSubmissionIdempotencyService;
use App\Services\SignedDisbursementDocumentService;
use App\Services\ThinkTankFinanceApiService;
use App\Services\ThinkTankProcurementBudgetGuard;
use App\Support\ExactMoney;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcurementDisbursementController extends Controller
{
    use GovernanceScope;

    public function __construct(
        private readonly ThinkTankProcurementBudgetGuard $thinkTankBudgetGuard,
        private readonly ProcurementSubmissionIdempotencyService $submissionIdempotency,
    ) {
        $this->middleware(['auth', 'not.funding.partner', 'permission:finance.purchase_requests.view']);
    }

    public function index()
    {
        $currentUser = auth()->user();
        $isPortfolioLeader = $this->userHasAssignedPortfolioScope($currentUser);
        $scopedNodeIds = $this->scopedNodeIds();
        if (! $isPortfolioLeader && $scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to disbursements.');
        }

        $baseQuery = ProcurementDisbursement::query()
            ->when($isPortfolioLeader, function ($query) use ($currentUser) {
                $this->applyAssignedPortfolioScopeToDisbursements($query, $currentUser);
            })
            ->when(! $isPortfolioLeader && $scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            });

        $disbursementSummary = $this->buildDisbursementSummary($baseQuery);

        $latestDisbursement = (clone $baseQuery)
            ->orderByDesc('paid_at')
            ->orderByDesc('created_at')
            ->first();

        $disbursements = (clone $baseQuery)->with([
            'purchaseOrder.purchaseRequest.programFunding.program',
            'purchaseOrder.budgetCommitment.programFunding.program',
            'purchaseOrder.budgetCommitment.purchaseRequest.programFunding.program',
            'purchaseRequestItem.resourceCategory',
            'purchaseRequestItem.resource',
            'purchaseRequestItem.deliverable.procurement',
            'deliverable.procurement',
            'vendor',
            'procurement',
            'thinkTankMember',
            'consortium',
            'fundAllocation',
            'consortiumDisbursementRequest',
        ])
            ->orderByDesc('paid_at')
            ->paginate(12);

        $canEditDisbursements = $this->canEditDisbursements();
        $canHandleProcurementProcessing = $this->canHandleProcurementProcessing();

        return view('procurement.disbursements.index', compact(
            'disbursements',
            'canEditDisbursements',
            'canHandleProcurementProcessing',
            'disbursementSummary',
            'latestDisbursement'
        ));
    }

    private function buildDisbursementSummary(Builder $baseQuery): array
    {
        $paidQuery = (clone $baseQuery)->recognizedPayment();
        $recognizedPaidAmount = (float) (clone $paidQuery)->sum('amount');
        $pendingQuery = (clone $baseQuery)
            ->where(function (Builder $query) {
                $query
                    ->whereNull('paid_at')
                    ->orWhereNull('status')
                    ->orWhereNotIn('status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES);
            })
            ->where(function (Builder $query) {
                $query
                    ->whereNull('status')
                    ->orWhereNotIn('status', ProcurementPurchaseOrder::NON_PAYING_DISBURSEMENT_STATUSES);
            });

        $summaryCurrencyDisbursements = (clone $baseQuery)
            ->with([
                'purchaseOrder.purchaseRequest.programFunding.program',
                'purchaseOrder.budgetCommitment.programFunding.program',
                'purchaseOrder.budgetCommitment.purchaseRequest.programFunding.program',
            ])
            ->get(['id', 'purchase_order_id', 'currency']);

        return [
            'currency' => $this->summaryCurrencyFor($summaryCurrencyDisbursements),
            'total_receipts' => (clone $baseQuery)->count(),
            'total_paid_amount' => $recognizedPaidAmount,
            'this_month_paid_amount' => (float) (clone $paidQuery)
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'pending_amount' => (float) (clone $pendingQuery)->sum('amount'),
            'paid_purchase_orders' => (clone $paidQuery)
                ->whereNotNull('purchase_order_id')
                ->distinct()
                ->count('purchase_order_id'),
            'paid_line_items' => (clone $paidQuery)
                ->whereNotNull('purchase_request_item_id')
                ->distinct()
                ->count('purchase_request_item_id'),
        ];
    }

    public function create(Request $request)
    {
        $purchaseOrderId = $request->get('purchase_order_id');
        $paymentMethods = $this->paymentMethods();

        $currentUser = auth()->user();
        $isPortfolioLeader = $this->userHasAssignedPortfolioScope($currentUser);
        $scopedNodeIds = $this->scopedNodeIds();

        $purchaseOrders = ProcurementPurchaseOrder::with([
            'procurement', 'vendor', 'disbursements.purchaseRequestItem', 'thinkTankMember',
            'consortium', 'subActivity', 'governanceNode',
            'deliverables.procurement',
            'lineItemEvidence',
            'budgetCommitment.programFunding.program',
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
            'purchaseRequest.programFunding.program',
            'purchaseRequest.items.deliverable.procurement',
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'budgetCommitment.purchaseRequest.programFunding.program',
            'budgetCommitment.purchaseRequest.items.deliverable.procurement',
        ])
            ->when($isPortfolioLeader, function ($query) use ($currentUser) {
                $this->applyAssignedPortfolioScopeToPurchaseOrders($query, $currentUser);
            })
            ->when(! $isPortfolioLeader && $scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (ProcurementPurchaseOrder $order) => $this->purchaseOrderHasPayableLineItems($order))
            ->values();

        $purchaseOrder = $purchaseOrderId
            ? $purchaseOrders->firstWhere('id', $purchaseOrderId)
            : null;

        // If coming from a PO without a payable line, still show it (store will reject invalid payment)
        if ($purchaseOrderId && !$purchaseOrder) {
            $po = ProcurementPurchaseOrder::with([
                'procurement', 'vendor', 'disbursements.purchaseRequestItem', 'thinkTankMember',
                'consortium', 'subActivity', 'governanceNode',
                'deliverables.procurement',
                'lineItemEvidence',
                'budgetCommitment.programFunding.program',
                'purchaseRequest.items.resourceCategory',
                'purchaseRequest.items.resource',
                'purchaseRequest.programFunding.program',
                'purchaseRequest.items.deliverable.procurement',
                'budgetCommitment.purchaseRequest.items.resourceCategory',
                'budgetCommitment.purchaseRequest.items.resource',
                'budgetCommitment.purchaseRequest.programFunding.program',
                'budgetCommitment.purchaseRequest.items.deliverable.procurement',
            ])->find($purchaseOrderId);

            if ($po) {
                $this->assertPurchaseOrderInScope($po);
                $purchaseOrders->prepend($po);
                $purchaseOrder = $po;
            }
        }

        $purchaseOrdersData = $purchaseOrders->mapWithKeys(function (ProcurementPurchaseOrder $order) {
            $deliverables = $this->eligibleDeliverablesForPurchaseOrder($order);
            $sourcePurchaseRequest = $order->purchaseRequest ?: $order->budgetCommitment?->purchaseRequest;
            $orderCurrency = $order->resolved_currency;
            $evidenceByItem = $order->lineItemEvidence->keyBy(fn (ProcurementPurchaseOrderItemEvidence $evidence) => (string) $evidence->purchase_request_item_id);
            $lineItemPaymentSummaries = $this->lineItemPaymentSummariesForPurchaseOrder($order);
            $lineItemSummary = $order->lineItemSummary();
            $poAmount = round((float) $lineItemSummary['total_amount'], 2);
            $paidAmount = round($order->paidAmount(), 2);
            $balanceAmount = round(max($poAmount - $paidAmount, 0), 2);
            $lineItems = $sourcePurchaseRequest?->items?->map(function ($item) use ($evidenceByItem, $lineItemPaymentSummaries, $order) {
                $lineAmount = $order->lineItemPayableAmount($item);
                $evidence = $evidenceByItem->get((string) $item->id);
                $paymentSummary = $lineItemPaymentSummaries->get((string) $item->id, [
                    'paid_amount' => 0.0,
                    'remaining_amount' => $lineAmount,
                ]);

                return [
                    'id' => (string) $item->id,
                    'category' => $item->resourceCategory?->name ?: 'N/A',
                    'resource' => $item->resource?->name ?: 'N/A',
                    'description' => $item->observations ?: $item->object_type ?: '',
                    'budget_code' => $item->budget_code,
                    'unit_price' => $order->lineItemDeliveredUnitPrice($item),
                    'ordered_quantity' => $order->lineItemOrderedQuantity($item),
                    'delivered_quantity' => $order->lineItemDeliveredQuantity($item),
                    'amount' => $lineAmount,
                    'paid_amount' => $paymentSummary['paid_amount'],
                    'remaining_amount' => $paymentSummary['remaining_amount'],
                    'deliverable_id' => $item->deliverable_id ? (string) $item->deliverable_id : null,
                    'deliverable_title' => $item->milestone ?: $item->deliverable?->title,
                    'evidence' => $evidence ? [
                        'is_met' => (bool) $evidence->is_met,
                        'deliverable_date' => $evidence->deliverable_date?->format('Y-m-d'),
                        'notes' => $evidence->notes,
                        'documents' => collect($evidence->documents ?? [])
                            ->map(function ($document, $index) use ($order, $evidence) {
                                $name = $document['name'] ?? 'Document';
                                $mimeType = (string) ($document['mime_type'] ?? '');
                                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION)
                                    ?: pathinfo((string) ($document['path'] ?? ''), PATHINFO_EXTENSION));
                                $normalizedMimeType = strtolower($mimeType);
                                if ($extension === '' && str_contains($normalizedMimeType, 'wordprocessingml')) {
                                    $extension = 'docx';
                                } elseif ($extension === '' && str_contains($normalizedMimeType, 'msword')) {
                                    $extension = 'doc';
                                } elseif ($extension === '' && ! empty($document['path'])) {
                                    $extension = $this->detectWordDocumentExtension((string) $document['path']);
                                }
                                $assistantPreview = auth()->user()?->isAdministrativeAssistant();
                                $previewUrl = $assistantPreview
                                    ? route('administrative-assistant.evidence.documents.download', [$order, $evidence->purchase_request_item_id, $evidence, $index])
                                    : route('procurement.purchase-orders.line-item-evidence.document', [$order, $evidence, $index]);
                                $wordPreviewUrl = ! $assistantPreview && in_array($extension, ['doc', 'docx'], true)
                                    ? route('procurement.purchase-orders.line-item-evidence.document-preview', [$order, $evidence, $index])
                                    : null;
                                $publicPreviewUrl = URL::temporarySignedRoute(
                                    'procurement.purchase-orders.line-item-evidence.public-preview',
                                    now()->addMinutes(45),
                                    [$order, $evidence, $index]
                                );

                                return [
                                    'name' => $name,
                                    'display_name' => $document['display_name'] ?? null,
                                    'mime_type' => $mimeType,
                                    'extension' => $extension,
                                    'url' => $previewUrl,
                                    'preview_url' => $previewUrl,
                                    'word_preview_url' => $wordPreviewUrl,
                                    'docx_preview_url' => $wordPreviewUrl,
                                    'download_url' => $previewUrl . '?download=1',
                                    'public_preview_url' => $publicPreviewUrl,
                                    'office_preview_url' => in_array($extension, ['doc', 'docx'], true)
                                        ? 'https://view.officeapps.live.com/op/embed.aspx?src=' . rawurlencode($publicPreviewUrl)
                                        : null,
                                ];
                            })
                            ->values()
                            ->all(),
                    ] : null,
                ];
            })->values() ?? collect();

            return [
                $order->id => [
                    'reference_no'         => $order->reference_no,
                    'po_title'             => $order->po_title,
                    'procurement_title'    => $order->procurement?->title ?? ($order->thinkTankMember?->name ?? 'Fund Transfer'),
                    'vendor_name'          => $order->vendor?->name,
                    'vendor_email'         => $order->vendor?->email,
                    'vendor_contact_name'  => $order->vendor_contact_name,
                    'vendor_contact_phone' => $order->vendor_contact_phone,
                    'amount'               => $poAmount,
                    'currency'             => $orderCurrency,
                    'paid_amount'          => $paidAmount,
                    'balance_amount'       => $balanceAmount,
                    'remaining'            => $balanceAmount,
                    'paid'                 => $paidAmount,
                    'payment_terms'        => $order->payment_terms,
                    'delivery_terms'       => $order->delivery_terms,
                    'expected_delivery'    => $order->expected_delivery_date?->format('M d, Y'),
                    'valid_until'          => $order->valid_until?->format('M d, Y'),
                    'sub_activity'         => $order->subActivity?->name,
                    'governance_node'      => $order->governanceNode?->name,
                    'status'               => $order->status,
                    'po_type'              => $order->po_type,
                    'incoterm'             => $order->incoterm,
                    'contract_reference'   => $order->contract_reference,
                    'supplier_reference'   => $order->supplier_reference,
                    'deliverables'         => $deliverables->map(fn ($deliverable) => [
                        'id'              => (string) $deliverable->id,
                        'title'           => $deliverable->title,
                        'type'            => $deliverable->type,
                        'status'          => $deliverable->status,
                        'amount'          => (float) ($deliverable->amount ?? 0),
                        'currency'        => $orderCurrency,
                        'procurement_ref' => $deliverable->procurement?->reference_no,
                    ])->values()->all(),
                    'line_items'           => $lineItems->all(),
                ],
            ];
        })->toArray();

        return view('procurement.disbursements.create', [
            'purchaseOrder'      => $purchaseOrder,
            'purchaseOrders'     => $purchaseOrders,
            'purchaseOrdersData' => $purchaseOrdersData,
            'paymentMethods'     => $paymentMethods,
            'statusOptions'      => $this->disbursementStatusOptions(),
            'idempotencyKey'     => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request)
    {
        return $this->persistDisbursement($request);
    }

    private function persistDisbursement(Request $request)
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'purchase_order_id'  => 'required|exists:procurement_purchase_orders,id',
            'payments' => ['required', 'array', 'min:1', 'max:50'],
            'payments.*.reference_no' => ['nullable', 'string', 'max:100'],
            'payments.*.purchase_request_item_id' => ['required', 'exists:myb_purchase_request_items,id'],
            'payments.*.amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.ExactMoney::DATABASE_MAX],
            'payments.*.payment_method' => ['required', 'string', 'max:100'],
            'payments.*.transfer_reference' => ['nullable', 'string', 'max:255'],
            'payments.*.status' => ['nullable', 'string', 'in:' . implode(',', array_keys($this->disbursementStatusOptions()))],
            'payments.*.paid_at' => ['required', 'date', 'before_or_equal:today'],
            'payments.*.notes' => ['nullable', 'string', 'max:2000'],
            'payments.*.signed_document_names' => ['nullable', 'array', 'max:20'],
            'payments.*.signed_document_names.*' => ['nullable', 'string', 'max:255'],
            'payments.*.signed_document_meta' => ['nullable', 'array', 'max:20'],
            'payments.*.signed_document_meta.*' => ['nullable', 'string', 'max:5000'],
            'payments.*.signed_documents' => ['required', 'array', 'min:1', 'max:20'],
            'payments.*.signed_documents.*' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'max:20480'],
            'item_evidence' => ['nullable', 'array'],
            'item_evidence.*.is_met' => ['nullable', 'boolean'],
            'item_evidence.*.deliverable_date' => ['nullable', 'date'],
            'item_evidence.*.notes' => ['nullable', 'string', 'max:3000'],
            'item_evidence.*.document_names' => ['nullable', 'array', 'max:20'],
            'item_evidence.*.document_names.*' => ['nullable', 'string', 'max:255'],
            'item_evidence.*.documents' => ['nullable', 'array', 'max:20'],
            'item_evidence.*.documents.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'max:20480'],
        ], [
            'payments.*.signed_documents.required' => 'Upload at least one signed payment document for each payment row.',
            'payments.*.signed_documents.*.mimes' => 'Signed payment documents must be a PDF, Office document, image, or ZIP file.',
            'payments.*.paid_at.before_or_equal' => 'A payment date cannot be in the future.',
            'item_evidence.*.documents.*.mimes' => 'Line item evidence must be a PDF, Office document, image, or ZIP file.',
        ]);

        $purchaseOrder = ProcurementPurchaseOrder::with([
            'procurement',
            'vendor',
            'subActivity',
            'disbursements.purchaseRequestItem',
            'thinkTankMember',
            'consortium',
            'deliverables.procurement',
            'lineItemEvidence',
            'budgetCommitment.programFunding.program',
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
            'purchaseRequest.programFunding.program',
            'purchaseRequest.items.deliverable.procurement',
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'budgetCommitment.purchaseRequest.programFunding.program',
            'budgetCommitment.purchaseRequest.items.deliverable.procurement',
        ])
            ->findOrFail($data['purchase_order_id']);
        $this->assertPurchaseOrderInScope($purchaseOrder);
        $this->assertNotGovernedThinkTankFundingReceiptMutation($purchaseOrder);

        $submissionFingerprint = $this->submissionIdempotency->fingerprint(
            'disbursement.create',
            $request->user()?->id,
            [
                'purchase_order_id' => (string) $purchaseOrder->id,
                'validated' => collect($data)->except('idempotency_key')->all(),
                'files' => $request->allFiles(),
            ],
        );
        $existingBatch = $this->disbursementBatchReplay(
            (string) $data['idempotency_key'],
            $submissionFingerprint,
            'create',
            $purchaseOrder,
            $request->user()?->id,
        );
        if ($existingBatch) {
            $paymentIds = collect($existingBatch->result_payment_ids ?? [])->map(fn ($id): string => (string) $id);
            $request->attributes->set('assistant_published_ids', $paymentIds->all());

            return redirect()
                ->route('procurement.disbursements.index')
                ->with('success', 'This disbursement submission was already processed; no duplicate payment was created.');
        }

        $paymentRows = $this->validatedPaymentRows($purchaseOrder, $data['payments']);

        if ($request->user()?->isAdministrativeAssistant()) {
            return app(\App\Services\AssistantSubmissionService::class)->capture($request, 'disbursement', $data, $purchaseOrder->governance_node_id, [
                'Purchase order' => $purchaseOrder->reference_no, 'Vendor' => $purchaseOrder->vendor?->name,
                'Currency' => $purchaseOrder->resolved_currency, 'Total amount' => collect($paymentRows)->sum('amount'),
                'Line items' => collect($paymentRows)->map(fn ($row) => [
                    'Deliverable' => PurchaseRequestItem::find($row['purchase_request_item_id'])?->milestone,
                    'Amount' => $row['amount'], 'Payment method' => $row['payment_method'],
                    'Payment date' => $row['paid_at'], 'Reference' => $row['reference_no'],
                    'Transfer reference' => $row['transfer_reference'], 'Status' => $row['status'], 'Notes' => $row['notes'],
                ])->all(),
                'Deliverable evidence' => collect($data['item_evidence'] ?? [])->map(fn ($evidence, $itemId) => [
                    'Deliverable' => PurchaseRequestItem::find($itemId)?->milestone,
                    'Marked received' => (bool) ($evidence['is_met'] ?? false),
                    'Deliverable date' => $evidence['deliverable_date'] ?? '',
                    'Notes' => $evidence['notes'] ?? '',
                ])->values()->all(),
            ]);
        }

        $disbursements = collect();
        $replayed = false;

        try {
            DB::transaction(function () use (
                $purchaseOrder,
                $paymentRows,
                $request,
                $submissionFingerprint,
                $data,
                &$disbursements,
                &$replayed,
            ) {
            $disbursements = collect();
            [$batch, $batchReplay] = $this->claimDisbursementBatch(
                (string) $data['idempotency_key'],
                $submissionFingerprint,
                'create',
                $purchaseOrder,
                $request->user()?->id,
            );
            if ($batchReplay) {
                $replayed = true;
                $disbursements = $this->paymentsForBatch($batch);

                return;
            }
            $purchaseOrder = $this->thinkTankBudgetGuard->lockPaymentBoundary(
                $purchaseOrder,
                $paymentRows,
            );
            $this->assertNotGovernedThinkTankFundingReceiptMutation($purchaseOrder);
            $lockedDisbursements = $purchaseOrder->disbursements()
                ->lockForUpdate()
                ->get();
            $purchaseOrder->setRelation('disbursements', $lockedDisbursements);
            $paymentRows = $this->validatedPaymentRows($purchaseOrder, $paymentRows);
            $this->storeLineItemEvidence($request, $purchaseOrder);

            foreach ($paymentRows as $paymentRow) {
                $disbursement = $this->createDisbursementForPaymentRow($purchaseOrder, $paymentRow);
                $this->storeSignedPaymentDocuments($request, $disbursement, (string) ($paymentRow['input_key'] ?? $paymentRow['index']));
                $disbursements->push($disbursement);
            }

            $this->syncPurchaseOrderStatus($purchaseOrder);

            ProcurementAuditLog::create([
                'user_id' => auth()->id(),
                'action' => $disbursements->count() === 1 ? 'Created disbursement' : 'Created disbursement batch',
                'procurement_id' => $purchaseOrder->procurement_id,
                'metadata' => [
                    'purchase_order_id' => $purchaseOrder->id,
                    'disbursement_ids' => $disbursements->pluck('id')->all(),
                    'line_item_ids' => $disbursements->pluck('purchase_request_item_id')->all(),
                    'amount' => round($disbursements->sum(fn (ProcurementDisbursement $row) => (float) $row->amount), 2),
                ],
                'created_at' => now(),
            ]);
            $batch->update([
                'result_payment_ids' => $disbursements->pluck('id')->map(fn ($id): string => (string) $id)->all(),
                'status' => 'completed',
            ]);
            }, 3);
        } catch (QueryException $exception) {
            $batch = $this->disbursementBatchReplay(
                (string) $data['idempotency_key'],
                $submissionFingerprint,
                'create',
                $purchaseOrder,
                $request->user()?->id,
            );
            if (! $batch) {
                throw $exception;
            }
            $replayed = true;
            $disbursements = $this->paymentsForBatch($batch);
        }

        $request->attributes->set('assistant_published_ids', $disbursements->pluck('id')->all());
        if (! $replayed) {
            DB::afterCommit(function () use ($disbursements) {
            $handoffNotifier = app(ProcurementDisbursementHandoffNotificationService::class);
            foreach ($disbursements as $row) {
                // Delivery failures cannot undo a committed financial record or make
                // the approval appear unsuccessful (which could prompt resubmission).
                try {
                    $this->sendReceipt($row->fresh());
                } catch (\Throwable $exception) {
                    \Log::warning('Disbursement receipt failed after posting.', ['disbursement_id' => $row->id, 'exception' => $exception::class]);
                }
                try {
                    $handoffNotifier->notify($row->fresh());
                } catch (\Throwable $exception) {
                    \Log::warning('Disbursement handoff failed after posting.', ['disbursement_id' => $row->id, 'exception' => $exception::class]);
                }
            }
            });
        }

        $message = $replayed
            ? 'This disbursement submission was already processed; no duplicate payment was created.'
            : ($disbursements->count() === 1
            ? 'Disbursement recorded successfully.'
            : $disbursements->count() . ' disbursements recorded successfully.');

        return redirect()
            ->route('procurement.disbursements.index')
            ->with('success', $message);
    }

    public function show(ProcurementDisbursement $disbursement)
    {
        $this->assertDisbursementInScope($disbursement);
        $disbursement->load([
            'purchaseOrder.procurement',
            'purchaseOrder.vendor',
            'purchaseOrder.subActivity',
            'purchaseOrder.governanceNode',
            'purchaseOrder.disbursements.deliverable',
            'purchaseOrder.disbursements.purchaseRequestItem',
            'purchaseOrder.deliverables.procurement',
            'purchaseOrder.lineItemEvidence',
            'purchaseOrder.purchaseRequest.programFunding.program',
            'purchaseOrder.purchaseRequest.governanceNode',
            'purchaseOrder.purchaseRequest.subActivity',
            'purchaseOrder.purchaseRequest.creator',
            'purchaseOrder.purchaseRequest.items.resourceCategory',
            'purchaseOrder.purchaseRequest.items.resource',
            'purchaseOrder.purchaseRequest.items.deliverable.procurement',
            'purchaseOrder.budgetCommitment.purchaseRequest.programFunding.program',
            'purchaseOrder.budgetCommitment.purchaseRequest.governanceNode',
            'purchaseOrder.budgetCommitment.purchaseRequest.subActivity',
            'purchaseOrder.budgetCommitment.purchaseRequest.creator',
            'purchaseOrder.budgetCommitment.purchaseRequest.items.resourceCategory',
            'purchaseOrder.budgetCommitment.purchaseRequest.items.resource',
            'purchaseOrder.budgetCommitment.purchaseRequest.items.deliverable.procurement',
            'purchaseOrder.budgetCommitment',
            'purchaseRequestItem.resourceCategory',
            'purchaseRequestItem.resource',
            'purchaseRequestItem.deliverable.procurement',
            'deliverable.procurement',
            'vendor',
            'procurement',
            'subActivity',
            'governanceNode',
        ]);
        $canEditDisbursements = $this->canEditDisbursements();
        $canHandleProcurementProcessing = $this->canHandleProcurementProcessing();

        return view('procurement.disbursements.show', compact('disbursement', 'canEditDisbursements', 'canHandleProcurementProcessing'));
    }

    public function edit(ProcurementDisbursement $disbursement)
    {
        $this->authorizeDisbursementEdit();
        $this->assertDisbursementInScope($disbursement);

        $disbursement->load([
            'purchaseOrder.procurement',
            'purchaseOrder.vendor',
            'purchaseOrder.disbursements.purchaseRequestItem.resourceCategory',
            'purchaseOrder.disbursements.purchaseRequestItem.resource',
            'purchaseOrder.disbursements.purchaseRequestItem.deliverable.procurement',
            'purchaseOrder.disbursements.deliverable',
            'purchaseOrder.deliverables.procurement',
            'purchaseOrder.purchaseRequest.items.resourceCategory',
            'purchaseOrder.purchaseRequest.items.resource',
            'purchaseOrder.purchaseRequest.items.deliverable.procurement',
            'purchaseOrder.budgetCommitment.purchaseRequest.items.resourceCategory',
            'purchaseOrder.budgetCommitment.purchaseRequest.items.resource',
            'purchaseOrder.budgetCommitment.purchaseRequest.items.deliverable.procurement',
            'purchaseRequestItem.resourceCategory',
            'purchaseRequestItem.resource',
            'deliverable.procurement',
            'vendor',
            'procurement',
            'subActivity',
        ]);

        $purchaseOrder = $disbursement->purchaseOrder;
        if (! $purchaseOrder) {
            abort(404, 'The purchase order for this disbursement could not be found.');
        }

        $this->assertPurchaseOrderInScope($purchaseOrder);
        $this->assertNotGovernedThinkTankFundingReceiptMutation($purchaseOrder);

        $lineItems = $this->sourceLineItemsForPurchaseOrder($purchaseOrder);
        $editableDisbursements = $purchaseOrder->disbursements
            ->sortBy(fn (ProcurementDisbursement $row) => $row->paid_at?->timestamp ?? $row->created_at?->timestamp ?? 0)
            ->values();
        $excludedDisbursementIds = $editableDisbursements
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
        $lineItemPaymentSummaries = $this->lineItemPaymentSummariesForPurchaseOrderExcludingIds($purchaseOrder, $excludedDisbursementIds);
        $lineItemsData = $this->lineItemsDataForEditor($purchaseOrder, $lineItemPaymentSummaries);
        $paymentRows = $this->paymentRowsForEditView($editableDisbursements);
        $paymentMethods = $this->paymentMethods();
        $statusOptions = $this->disbursementStatusOptions();
        $idempotencyKey = (string) Str::uuid();
        $paidExcludingEditable = $this->purchaseOrderPaidAmountExcludingIds($purchaseOrder, $excludedDisbursementIds);
        $editablePoBalance = round(max((float) ($purchaseOrder->amount ?? 0) - $paidExcludingEditable, 0), 2);

        return view('procurement.disbursements.edit', compact(
            'disbursement',
            'purchaseOrder',
            'lineItems',
            'editableDisbursements',
            'lineItemPaymentSummaries',
            'lineItemsData',
            'paymentRows',
            'paymentMethods',
            'statusOptions',
            'paidExcludingEditable',
            'editablePoBalance'
            , 'idempotencyKey'
        ));
    }

    public function update(Request $request, ProcurementDisbursement $disbursement)
    {
        $this->authorizeDisbursementEdit();
        $this->assertDisbursementInScope($disbursement);

        $disbursement->load([
            'purchaseOrder.disbursements.purchaseRequestItem',
            'purchaseOrder.deliverables.procurement',
            'purchaseOrder.purchaseRequest.items.resourceCategory',
            'purchaseOrder.purchaseRequest.items.resource',
            'purchaseOrder.purchaseRequest.items.deliverable.procurement',
            'purchaseOrder.budgetCommitment.purchaseRequest.items.resourceCategory',
            'purchaseOrder.budgetCommitment.purchaseRequest.items.resource',
            'purchaseOrder.budgetCommitment.purchaseRequest.items.deliverable.procurement',
            'purchaseRequestItem.resourceCategory',
            'purchaseRequestItem.resource',
            'deliverable.procurement',
        ]);

        $purchaseOrder = $disbursement->purchaseOrder;
        if (! $purchaseOrder) {
            abort(404, 'The purchase order for this disbursement could not be found.');
        }

        $this->assertPurchaseOrderInScope($purchaseOrder);
        $this->assertNotGovernedThinkTankFundingReceiptMutation($purchaseOrder);

        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'payments' => ['nullable', 'array', 'max:50'],
            'payments.*.id' => ['nullable', 'exists:procurement_disbursements,id'],
            'payments.*.reference_no' => ['nullable', 'string', 'max:100'],
            'payments.*.purchase_request_item_id' => ['required', 'exists:myb_purchase_request_items,id'],
            'payments.*.amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.ExactMoney::DATABASE_MAX],
            'payments.*.payment_method' => ['required', 'string', 'max:100'],
            'payments.*.transfer_reference' => ['nullable', 'string', 'max:255'],
            'payments.*.status' => ['required', 'string', 'in:' . implode(',', array_keys($this->disbursementStatusOptions()))],
            'payments.*.paid_at' => ['required', 'date', 'before_or_equal:today'],
            'payments.*.notes' => ['nullable', 'string', 'max:2000'],
            'payments.*.signed_document_names' => ['nullable', 'array', 'max:20'],
            'payments.*.signed_document_names.*' => ['nullable', 'string', 'max:255'],
            'payments.*.signed_document_meta' => ['nullable', 'array', 'max:20'],
            'payments.*.signed_document_meta.*' => ['nullable', 'string', 'max:5000'],
            'payments.*.signed_documents' => ['nullable', 'array', 'max:20'],
            'payments.*.signed_documents.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'max:20480'],
            'delete_payment_ids' => ['nullable', 'array'],
            'delete_payment_ids.*' => ['nullable', 'exists:procurement_disbursements,id'],
        ], [
            'payments.*.signed_documents.*.mimes' => 'Signed payment documents must be a PDF, Office document, image, or ZIP file.',
            'payments.*.paid_at.before_or_equal' => 'A payment date cannot be in the future.',
        ]);

        $submissionFingerprint = $this->submissionIdempotency->fingerprint(
            'disbursement.update',
            $request->user()?->id,
            [
                'purchase_order_id' => (string) $purchaseOrder->id,
                'context_disbursement_id' => (string) $disbursement->id,
                'validated' => collect($data)->except('idempotency_key')->all(),
                'files' => $request->allFiles(),
            ],
        );
        $existingBatch = $this->disbursementBatchReplay(
            (string) $data['idempotency_key'],
            $submissionFingerprint,
            'update',
            $purchaseOrder,
            $request->user()?->id,
        );
        if ($existingBatch) {
            return redirect()
                ->route('procurement.disbursements.index')
                ->with('success', 'This disbursement update was already processed; no duplicate changes were applied.');
        }

        $editableDisbursements = $purchaseOrder->disbursements->values();
        $editableIds = $editableDisbursements
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();
        $deleteIds = collect($data['delete_payment_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($deleteIds->diff($editableIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'delete_payment_ids' => 'One or more payment rows cannot be removed from this purchase order.',
            ]);
        }

        $paymentInput = $data['payments'] ?? [];
        if (empty($paymentInput) && $deleteIds->isEmpty()) {
            throw ValidationException::withMessages([
                'payments' => 'Add at least one payment line or remove an existing payment.',
            ]);
        }

        $paymentRows = $this->validatedPaymentRows($purchaseOrder, $paymentInput, $editableIds, $editableIds);
        $activePaymentIds = collect($paymentRows)
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id);

        if ($activePaymentIds->intersect($deleteIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'payments' => 'A payment row cannot be removed and updated at the same time.',
            ]);
        }

        $updatedDisbursements = collect();
        $createdDisbursements = collect();
        $replayed = false;

        try {
            DB::transaction(function () use (
            $purchaseOrder,
            $deleteIds,
            $paymentRows,
            $activePaymentIds,
            $request,
            $submissionFingerprint,
            $data,
            &$updatedDisbursements,
            &$createdDisbursements,
            &$replayed,
        ) {
            $updatedDisbursements = collect();
            $createdDisbursements = collect();
            [$batch, $batchReplay] = $this->claimDisbursementBatch(
                (string) $data['idempotency_key'],
                $submissionFingerprint,
                'update',
                $purchaseOrder,
                $request->user()?->id,
            );
            if ($batchReplay) {
                $replayed = true;

                return;
            }
            $purchaseOrder = $this->thinkTankBudgetGuard->lockPaymentBoundary(
                $purchaseOrder,
                $paymentRows,
                $activePaymentIds->all(),
                $deleteIds->all(),
            );
            $this->assertNotGovernedThinkTankFundingReceiptMutation($purchaseOrder);
            $editableDisbursements = $purchaseOrder->disbursements()
                ->lockForUpdate()
                ->get();
            $purchaseOrder->setRelation('disbursements', $editableDisbursements);
            $before = $editableDisbursements
                ->map(fn (ProcurementDisbursement $row) => $row->only([
                    'id',
                    'reference_no',
                    'purchase_request_item_id',
                    'deliverable_id',
                    'amount',
                    'payment_method',
                    'transfer_reference',
                    'status',
                    'paid_at',
                    'notes',
                ]))
                ->values()
                ->all();
            $replacedPaymentIds = $activePaymentIds
                ->merge($deleteIds)
                ->unique()
                ->values()
                ->all();
            $paymentRows = $this->validatedPaymentRows(
                $purchaseOrder,
                $paymentRows,
                $replacedPaymentIds,
                $editableDisbursements->pluck('id')->map(fn ($id): string => (string) $id)->all(),
            );
            $this->assertRecognizedPaymentMutationsUseReversal(
                $editableDisbursements,
                $paymentRows,
                $deleteIds,
            );
            $editableById = $editableDisbursements->keyBy(fn (ProcurementDisbursement $row) => (string) $row->id);

            foreach ($deleteIds as $deleteId) {
                $editableById->get((string) $deleteId)?->update([
                    'status' => 'void',
                ]);
            }

            foreach ($paymentRows as $paymentRow) {
                $existing = filled($paymentRow['id'] ?? null)
                    ? $editableById->get((string) $paymentRow['id'])
                    : null;

                $payload = $this->disbursementPayloadForPaymentRow($purchaseOrder, $paymentRow, $existing);

                if ($existing) {
                    $existing->update($payload);
                    $this->storeSignedPaymentDocuments($request, $existing, (string) ($paymentRow['input_key'] ?? $paymentRow['index']));
                    $updatedDisbursements->push($existing->fresh());
                } else {
                    $newDisbursement = $this->createDisbursementForPaymentRow($purchaseOrder, $paymentRow, $payload);
                    $this->storeSignedPaymentDocuments($request, $newDisbursement, (string) ($paymentRow['input_key'] ?? $paymentRow['index']));
                    $createdDisbursements->push($newDisbursement);
                }
            }

            $this->syncPurchaseOrderStatus($purchaseOrder);

            $after = $purchaseOrder->disbursements()
                ->get([
                    'id',
                    'reference_no',
                    'purchase_request_item_id',
                    'deliverable_id',
                    'amount',
                    'payment_method',
                    'transfer_reference',
                    'status',
                    'paid_at',
                    'notes',
                ])
                ->map(fn (ProcurementDisbursement $row) => $row->toArray())
                ->values()
                ->all();

            ProcurementAuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'Updated disbursement batch',
                'procurement_id' => $purchaseOrder->procurement_id,
                'metadata' => [
                    'purchase_order_id' => $purchaseOrder->id,
                    'voided_disbursement_ids' => $deleteIds->all(),
                    'updated_disbursement_ids' => $updatedDisbursements->pluck('id')->all(),
                    'created_disbursement_ids' => $createdDisbursements->pluck('id')->all(),
                    'before' => $before,
                    'after' => $after,
                ],
                'created_at' => now(),
            ]);
            $batch->update([
                'result_payment_ids' => collect($after)->pluck('id')->map(fn ($id): string => (string) $id)->all(),
                'status' => 'completed',
            ]);
            }, 3);
        } catch (QueryException $exception) {
            $batch = $this->disbursementBatchReplay(
                (string) $data['idempotency_key'],
                $submissionFingerprint,
                'update',
                $purchaseOrder,
                $request->user()?->id,
            );
            if (! $batch) {
                throw $exception;
            }
            $replayed = true;
        }

        if (! $replayed) {
            $createdDisbursements->each(fn (ProcurementDisbursement $row) => $this->sendReceipt($row->fresh()));
            $handoffNotifier = app(ProcurementDisbursementHandoffNotificationService::class);
            $createdDisbursements
                ->merge($updatedDisbursements->filter(fn (ProcurementDisbursement $row) => ! $row->procurement_notified_at))
                ->each(fn (ProcurementDisbursement $row) => $handoffNotifier->notify($row->fresh()));
        }

        $freshDisbursement = ProcurementDisbursement::find($disbursement->id);
        $redirectRoute = $freshDisbursement
            ? route('procurement.disbursements.show', $freshDisbursement)
            : route('procurement.disbursements.index');

        return redirect($redirectRoute)
            ->with('success', $replayed
                ? 'This disbursement update was already processed; no duplicate changes were applied.'
                : 'Disbursement payment lines updated.');
    }

    public function storeProcurementProcessing(Request $request, ProcurementDisbursement $disbursement)
    {
        $this->assertDisbursementInScope($disbursement);

        if (! $this->canHandleProcurementProcessing()) {
            abort(403, 'Only procurement officers or administrators can complete this processing step.');
        }

        $data = $request->validate([
            'goods_receipt_reference' => ['required', 'string', 'max:255'],
            'sap_52_series_reference' => ['required', 'string', 'max:255'],
            'procurement_processing_notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $before = $disbursement->only([
            'procurement_processing_status',
            'goods_receipt_reference',
            'sap_52_series_reference',
            'procurement_processing_notes',
        ]);

        $disbursement->update([
            'procurement_processing_status' => ProcurementDisbursement::PROCUREMENT_STATUS_COMPLETED,
            'goods_receipt_reference' => $data['goods_receipt_reference'],
            'goods_receipt_generated_at' => now(),
            'goods_receipt_generated_by' => auth()->id(),
            'sap_52_series_reference' => $data['sap_52_series_reference'],
            'sap_52_series_entered_at' => now(),
            'sap_52_series_entered_by' => auth()->id(),
            'procurement_processing_notes' => $data['procurement_processing_notes'] ?? null,
        ]);

        ProcurementAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'Recorded goods receipt and SAP 52 series',
            'procurement_id' => $disbursement->procurement_id,
            'metadata' => [
                'purchase_order_id' => $disbursement->purchase_order_id,
                'disbursement_id' => $disbursement->id,
                'receipt_reference' => $disbursement->reference_no,
                'before' => $before,
                'after' => $disbursement->fresh()->only([
                    'procurement_processing_status',
                    'goods_receipt_reference',
                    'sap_52_series_reference',
                    'procurement_processing_notes',
                ]),
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Goods receipt and SAP 52 series reference recorded.');
    }

    public function downloadSignedDocument(Request $request, ProcurementDisbursement $disbursement, int $document)
    {
        $this->assertDisbursementInScope($disbursement);

        return app(SignedDisbursementDocumentService::class)
            ->response($disbursement, $document, $request->boolean('download'));
    }

    public function downloadSignedDocumentPdf(Request $request, ProcurementDisbursement $disbursement, int $document)
    {
        $this->assertDisbursementInScope($disbursement);

        return app(SignedDisbursementDocumentService::class)
            ->response($disbursement, $document, $request->boolean('download'), true);
    }

    public function destroy(ProcurementDisbursement $disbursement)
    {
        $this->authorizeDisbursementRevert();
        $this->assertDisbursementInScope($disbursement);

        $disbursement->load('purchaseOrder');

        $purchaseOrder = $disbursement->purchaseOrder;
        if ($purchaseOrder) {
            $this->assertPurchaseOrderInScope($purchaseOrder);
            if ($this->isGovernedThinkTankFundingReceipt($purchaseOrder)) {
                throw ValidationException::withMessages([
                    'disbursement' => 'Secretariat-to-Think-Tank transfers cannot be reversed through procurement payments. Use the governed funding-transfer correction workflow.',
                ]);
            }
        }

        DB::transaction(function () use ($disbursement, $purchaseOrder) {
            if ($purchaseOrder) {
                $purchaseOrder = $this->thinkTankBudgetGuard->lockPaymentBoundary(
                    $purchaseOrder,
                    [],
                    [],
                    [(string) $disbursement->id],
                );
                if ($this->isGovernedThinkTankFundingReceipt($purchaseOrder)) {
                    throw ValidationException::withMessages([
                        'disbursement' => 'Secretariat-to-Think-Tank transfers cannot be reversed through procurement payments. Use the governed funding-transfer correction workflow.',
                    ]);
                }
            }
            $disbursement = ProcurementDisbursement::query()
                ->whereKey($disbursement->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($purchaseOrder
                && (string) $disbursement->purchase_order_id !== (string) $purchaseOrder->id) {
                throw ValidationException::withMessages([
                    'disbursement' => 'The payment no longer belongs to the selected purchase order.',
                ]);
            }
            if (strtolower((string) $disbursement->status) === 'reversed') {
                throw ValidationException::withMessages([
                    'disbursement' => 'This payment has already been reversed.',
                ]);
            }

            $metadata = [
                'purchase_order_id' => $purchaseOrder?->id ?: $disbursement->purchase_order_id,
                'disbursement_id' => $disbursement->id,
                'reference_no' => $disbursement->reference_no,
                'purchase_request_item_id' => $disbursement->purchase_request_item_id,
                'deliverable_id' => $disbursement->deliverable_id,
                'amount' => $disbursement->amount,
                'currency' => $disbursement->resolved_currency,
                'status' => $disbursement->status,
                'paid_at' => $disbursement->paid_at?->toDateTimeString(),
            ];

            $procurementId = $disbursement->procurement_id ?: $purchaseOrder?->procurement_id;

            $disbursement->update([
                'status' => 'reversed',
            ]);

            if ($purchaseOrder) {
                $this->syncPurchaseOrderStatus($purchaseOrder);
            }

            ProcurementAuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'Reverted disbursement payment',
                'procurement_id' => $procurementId,
                'metadata' => [
                    'before' => $metadata,
                    'after' => [
                        'disbursement_id' => $disbursement->id,
                        'reference_no' => $disbursement->reference_no,
                        'status' => 'reversed',
                    ],
                ],
                'created_at' => now(),
            ]);
        }, 3);

        return redirect()
            ->route('procurement.disbursements.index')
            ->with('success', 'Payment reverted. The receipt remains on record and no longer counts as paid.');
    }

    public function pdf(ProcurementDisbursement $disbursement)
    {
        $this->assertDisbursementInScope($disbursement);
        $disbursement->load([
            'purchaseOrder',
            'purchaseRequestItem.resourceCategory',
            'purchaseRequestItem.resource',
            'purchaseRequestItem.deliverable.procurement',
            'deliverable.procurement',
            'vendor',
            'procurement',
            'subActivity',
        ]);

        $pdf = Pdf::loadView('procurement.disbursements.pdf', [
            'disbursement' => $disbursement,
        ]);

        return $pdf->stream('receipt-' . ($disbursement->reference_no ?? 'payment') . '.pdf');
    }

    public function download(ProcurementDisbursement $disbursement)
    {
        $this->assertDisbursementInScope($disbursement);
        $disbursement->load([
            'purchaseOrder',
            'purchaseRequestItem.resourceCategory',
            'purchaseRequestItem.resource',
            'purchaseRequestItem.deliverable.procurement',
            'deliverable.procurement',
            'vendor',
            'procurement',
            'subActivity',
        ]);

        $pdf = Pdf::loadView('procurement.disbursements.pdf', [
            'disbursement' => $disbursement,
        ]);

        return $pdf->download('receipt-' . ($disbursement->reference_no ?? 'payment') . '.pdf');
    }

    private function syncPurchaseOrderStatus(ProcurementPurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->refresh();
        $purchaseOrder->loadMissing('invoice');
        $remaining = $purchaseOrder->remainingAmount();
        $totalPaid = $purchaseOrder->paidAmount();

        $status = $this->purchaseOrderStatusAfterPaymentSync(
            (string) $purchaseOrder->status,
            $totalPaid,
            $remaining,
        );

        $purchaseOrder->update([
            'status' => $status,
        ]);

        if ($remaining <= 0 && $totalPaid > 0) {
            $this->ensureInvoiceForPaidPurchaseOrder($purchaseOrder);
        } elseif ($purchaseOrder->invoice && $purchaseOrder->invoice->status === 'paid') {
            $purchaseOrder->invoice->update([
                'status' => 'approved',
            ]);
        }
    }

    private function ensureInvoiceForPaidPurchaseOrder(ProcurementPurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing([
            'invoice.deliverables',
            'disbursements',
            'deliverables',
            'purchaseRequest.items.deliverable',
            'budgetCommitment.purchaseRequest.items.deliverable',
        ]);

        $latestDisbursement = $purchaseOrder->disbursements
            ->sortByDesc(fn (ProcurementDisbursement $disbursement) => $disbursement->paid_at?->timestamp ?? 0)
            ->first();

        $paidAt = $latestDisbursement?->paid_at ?: now();

        if ($purchaseOrder->invoice) {
            $purchaseOrder->invoice->update([
                'status' => 'paid',
                'approved_by' => $purchaseOrder->invoice->approved_by ?: auth()->id(),
                'approved_at' => $purchaseOrder->invoice->approved_at ?: now(),
            ]);

            $this->syncInvoiceDeliverables($purchaseOrder->invoice, $purchaseOrder);

            return;
        }

        $invoice = ProcurementInvoice::create([
            'procurement_id' => $purchaseOrder->procurement_id,
            'vendor_id' => $purchaseOrder->vendor_id,
            'sub_activity_id' => $purchaseOrder->sub_activity_id,
            'governance_node_id' => $purchaseOrder->governance_node_id,
            'invoice_month' => $paidAt->copy()->startOfMonth()->toDateString(),
            'reference_no' => ProcurementInvoice::generateReference(),
            'amount' => $purchaseOrder->amount,
            'currency' => $purchaseOrder->resolved_currency,
            'status' => 'paid',
            'created_by' => $purchaseOrder->created_by ?: auth()->id(),
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'notes' => 'Auto-generated from fully paid purchase order ' . ($purchaseOrder->reference_no ?? $purchaseOrder->id),
        ]);

        $purchaseOrder->update(['invoice_id' => $invoice->id]);
        $this->syncInvoiceDeliverables($invoice, $purchaseOrder);
    }

    private function syncInvoiceDeliverables(ProcurementInvoice $invoice, ProcurementPurchaseOrder $purchaseOrder): void
    {
        $deliverableIds = $purchaseOrder->disbursements
            ->pluck('deliverable_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($deliverableIds->isEmpty()) {
            $deliverableIds = $this->eligibleDeliverablesForPurchaseOrder($purchaseOrder)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values();
        }

        if ($deliverableIds->isNotEmpty()) {
            $invoice->deliverables()->syncWithoutDetaching($deliverableIds->all());
        }
    }

    private function eligibleDeliverablesForPurchaseOrder(ProcurementPurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->loadMissing([
            'deliverables.procurement',
            'purchaseRequest.items.deliverable.procurement',
            'budgetCommitment.purchaseRequest.items.deliverable.procurement',
        ]);

        $sourcePurchaseRequest = $purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest;
        $itemDeliverables = $sourcePurchaseRequest?->items?->pluck('deliverable')->filter() ?? collect();

        return $purchaseOrder->deliverables
            ->merge($itemDeliverables)
            ->filter()
            ->unique('id')
            ->values();
    }

    private function sourceLineItemsForPurchaseOrder(ProcurementPurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->loadMissing([
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
            'purchaseRequest.items.deliverable.procurement',
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'budgetCommitment.purchaseRequest.items.deliverable.procurement',
        ]);

        return $purchaseOrder->sourcePurchaseRequest()?->items ?? collect();
    }

    private function lineItemForPurchaseOrder(ProcurementPurchaseOrder $purchaseOrder, ?string $itemId): ?PurchaseRequestItem
    {
        if (! $itemId) {
            return null;
        }

        return $this->sourceLineItemsForPurchaseOrder($purchaseOrder)
            ->first(fn (PurchaseRequestItem $item) => (string) $item->id === (string) $itemId);
    }

    private function legacyLineItemForDisbursement(
        ProcurementPurchaseOrder $purchaseOrder,
        ProcurementDisbursement $disbursement
    ): ?PurchaseRequestItem {
        if ($disbursement->purchase_request_item_id) {
            return $this->lineItemForPurchaseOrder($purchaseOrder, $disbursement->purchase_request_item_id);
        }

        $lineItems = $this->sourceLineItemsForPurchaseOrder($purchaseOrder);
        if ($disbursement->deliverable_id) {
            $matched = $lineItems->first(fn (PurchaseRequestItem $item) => (string) ($item->deliverable_id ?? '') === (string) $disbursement->deliverable_id);
            if ($matched) {
                return $matched;
            }
        }

        return $lineItems->count() === 1 ? $lineItems->first() : null;
    }

    private function purchaseOrderHasPayableLineItems(ProcurementPurchaseOrder $purchaseOrder): bool
    {
        if ($purchaseOrder->remainingAmount() <= 0) {
            return false;
        }

        return $this->lineItemPaymentSummariesForPurchaseOrder($purchaseOrder)
            ->contains(fn (array $summary) => (float) $summary['remaining_amount'] > 0);
    }

    private function lineItemPaymentSummariesForPurchaseOrder(
        ProcurementPurchaseOrder $purchaseOrder,
        ?ProcurementDisbursement $excludeDisbursement = null
    ) {
        $excludeIds = $excludeDisbursement ? [(string) $excludeDisbursement->id] : [];

        return $this->lineItemPaymentSummariesForPurchaseOrderExcludingIds($purchaseOrder, $excludeIds);
    }

    private function lineItemPaymentSummariesForPurchaseOrderExcludingIds(
        ProcurementPurchaseOrder $purchaseOrder,
        array $excludeDisbursementIds = []
    ) {
        $paidAmountsByItem = $this->paidAmountsByLineItemForPurchaseOrderExcludingIds($purchaseOrder, $excludeDisbursementIds);

        return $this->sourceLineItemsForPurchaseOrder($purchaseOrder)
            ->mapWithKeys(function (PurchaseRequestItem $item) use ($paidAmountsByItem, $purchaseOrder) {
                $lineAmount = $purchaseOrder->lineItemPayableAmount($item);
                $paidAmount = round(min($lineAmount, (float) $paidAmountsByItem->get((string) $item->id, 0)), 2);

                return [
                    (string) $item->id => [
                        'paid_amount' => $paidAmount,
                        'remaining_amount' => round(max($lineAmount - $paidAmount, 0), 2),
                    ],
                ];
            });
    }

    private function paidAmountsByLineItemForPurchaseOrder(
        ProcurementPurchaseOrder $purchaseOrder,
        ?ProcurementDisbursement $excludeDisbursement = null
    ) {
        $excludeIds = $excludeDisbursement ? [(string) $excludeDisbursement->id] : [];

        return $this->paidAmountsByLineItemForPurchaseOrderExcludingIds($purchaseOrder, $excludeIds);
    }

    private function paidAmountsByLineItemForPurchaseOrderExcludingIds(
        ProcurementPurchaseOrder $purchaseOrder,
        array $excludeDisbursementIds = []
    ) {
        $purchaseOrder->loadMissing('disbursements');
        $excludeDisbursementIds = collect($excludeDisbursementIds)
            ->map(fn ($id) => (string) $id)
            ->all();

        return $purchaseOrder->disbursements
            ->reject(fn (ProcurementDisbursement $disbursement) => in_array((string) $disbursement->id, $excludeDisbursementIds, true))
            ->filter(fn (ProcurementDisbursement $disbursement) => $disbursement->purchase_request_item_id
                && $this->disbursementCountsAsPaid($disbursement))
            ->groupBy(fn (ProcurementDisbursement $disbursement) => (string) $disbursement->purchase_request_item_id)
            ->map(fn ($receipts) => round($receipts->sum(fn (ProcurementDisbursement $disbursement) => (float) $disbursement->amount), 2));
    }

    private function lineItemRemainingAmount(
        ProcurementPurchaseOrder $purchaseOrder,
        PurchaseRequestItem $lineItem,
        ?ProcurementDisbursement $excludeDisbursement = null
    ): float {
        $lineAmount = $purchaseOrder->lineItemPayableAmount($lineItem);
        $paidAmount = round((float) $this->paidAmountsByLineItemForPurchaseOrder($purchaseOrder, $excludeDisbursement)
            ->get((string) $lineItem->id, 0), 2);

        return round(max($lineAmount - min($lineAmount, $paidAmount), 0), 2);
    }

    private function purchaseOrderPaidAmountExcluding(
        ProcurementPurchaseOrder $purchaseOrder,
        ?ProcurementDisbursement $excludeDisbursement = null
    ): float {
        $excludeIds = $excludeDisbursement ? [(string) $excludeDisbursement->id] : [];

        return $this->purchaseOrderPaidAmountExcludingIds($purchaseOrder, $excludeIds);
    }

    private function purchaseOrderPaidAmountExcludingIds(
        ProcurementPurchaseOrder $purchaseOrder,
        array $excludeDisbursementIds = []
    ): float {
        $purchaseOrder->loadMissing('disbursements');
        $excludeDisbursementIds = collect($excludeDisbursementIds)
            ->map(fn ($id) => (string) $id)
            ->all();

        return round($purchaseOrder->disbursements
            ->reject(fn (ProcurementDisbursement $disbursement) => in_array((string) $disbursement->id, $excludeDisbursementIds, true))
            ->filter(fn (ProcurementDisbursement $disbursement) => $this->disbursementCountsAsPaid($disbursement))
            ->sum(fn (ProcurementDisbursement $disbursement) => (float) $disbursement->amount), 2);
    }

    private function editableDisbursementMaxAmount(
        ProcurementPurchaseOrder $purchaseOrder,
        ProcurementDisbursement $disbursement,
        PurchaseRequestItem $lineItem,
        ?string $newStatus
    ): float {
        $lineAmount = $purchaseOrder->lineItemPayableAmount($lineItem);

        if (! $this->statusCountsAgainstPurchaseOrder($newStatus)) {
            return $lineAmount;
        }

        $lineEditableBalance = $this->lineItemRemainingAmount($purchaseOrder, $lineItem, $disbursement);
        $poEditableBalance = round(max((float) ($purchaseOrder->amount ?? 0) - $this->purchaseOrderPaidAmountExcluding($purchaseOrder, $disbursement), 0), 2);

        return round(max(min($lineEditableBalance, $poEditableBalance), 0), 2);
    }

    private function lineItemsDataForEditor(ProcurementPurchaseOrder $purchaseOrder, $lineItemPaymentSummaries): array
    {
        return $this->sourceLineItemsForPurchaseOrder($purchaseOrder)
            ->map(function (PurchaseRequestItem $item) use ($lineItemPaymentSummaries, $purchaseOrder) {
                $lineAmount = $purchaseOrder->lineItemPayableAmount($item);
                $summary = $lineItemPaymentSummaries->get((string) $item->id, [
                    'paid_amount' => 0,
                    'remaining_amount' => $lineAmount,
                ]);

                return [
                    'id' => (string) $item->id,
                    'label' => trim(($item->resource?->name ?? $item->resourceCategory?->name ?? 'Line item')
                        . ($item->milestone ? ' | ' . $item->milestone : '')),
                    'category' => $item->resourceCategory?->name ?: 'N/A',
                    'resource' => $item->resource?->name ?: 'N/A',
                    'deliverable_title' => $item->milestone ?: $item->deliverable?->title,
                    'budget_code' => $item->budget_code,
                    'amount' => $lineAmount,
                    'unit_price' => $purchaseOrder->lineItemDeliveredUnitPrice($item),
                    'ordered_quantity' => $purchaseOrder->lineItemOrderedQuantity($item),
                    'delivered_quantity' => $purchaseOrder->lineItemDeliveredQuantity($item),
                    'base_paid_amount' => round((float) ($summary['paid_amount'] ?? 0), 2),
                    'base_remaining_amount' => round((float) ($summary['remaining_amount'] ?? 0), 2),
                    'currency' => $purchaseOrder->resolved_currency,
                ];
            })
            ->values()
            ->all();
    }

    private function paymentRowsForEditView($editableDisbursements): array
    {
        return collect($editableDisbursements)
            ->map(fn (ProcurementDisbursement $row) => [
                'id' => (string) $row->id,
                'reference_no' => $row->reference_no,
                'purchase_request_item_id' => $row->purchase_request_item_id ? (string) $row->purchase_request_item_id : null,
                'amount' => number_format((float) $row->amount, 2, '.', ''),
                'payment_method' => $row->payment_method,
                'transfer_reference' => $row->transfer_reference,
                'status' => $row->status ?: 'completed',
                'paid_at' => $row->paid_at?->format('Y-m-d') ?? now()->format('Y-m-d'),
                'notes' => $row->notes,
                'signed_documents' => collect($row->signed_documents ?? [])
                    ->filter(fn ($document) => is_array($document))
                    ->map(fn ($document, $index) => [
                        'name' => $document['name'] ?? 'Document',
                        'display_name' => $document['display_name'] ?? null,
                        'url' => route('procurement.disbursements.signed-document', [$row, $index]) . '?download=1',
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private function validatedPaymentRows(
        ProcurementPurchaseOrder $purchaseOrder,
        array $payments,
        array $excludeDisbursementIds = [],
        array $allowedExistingIds = []
    ): array {
        if (empty($payments)) {
            return [];
        }

        $lineItems = $this->sourceLineItemsForPurchaseOrder($purchaseOrder)
            ->mapWithKeys(fn (PurchaseRequestItem $item) => [(string) $item->id => $item]);

        if ($lineItems->isEmpty()) {
            throw ValidationException::withMessages([
                'payments' => 'This purchase order does not have purchase request item lines to pay.',
            ]);
        }

        $statusOptions = array_keys($this->disbursementStatusOptions());
        $allowedExistingIds = collect($allowedExistingIds)->map(fn ($id) => (string) $id)->all();
        $activeExistingIds = [];
        $referenceLookup = [];
        $normalized = [];

        foreach ($payments as $index => $payment) {
            $inputKey = filled($payment['input_key'] ?? null)
                ? (string) $payment['input_key']
                : (string) $index;
            $existingId = trim((string) ($payment['id'] ?? ''));
            if ($existingId !== '') {
                if (! empty($allowedExistingIds) && ! in_array($existingId, $allowedExistingIds, true)) {
                    throw ValidationException::withMessages([
                        "payments.{$index}.id" => 'This payment row does not belong to the purchase order being edited.',
                    ]);
                }

                if (in_array($existingId, $activeExistingIds, true)) {
                    throw ValidationException::withMessages([
                        "payments.{$index}.id" => 'The same disbursement row was submitted more than once.',
                    ]);
                }

                $activeExistingIds[] = $existingId;
            }

            $lineItemId = (string) ($payment['purchase_request_item_id'] ?? '');
            $lineItem = $lineItems->get($lineItemId);
            if (! $lineItem) {
                throw ValidationException::withMessages([
                    "payments.{$index}.purchase_request_item_id" => 'Select a purchase order line item that belongs to this purchase order.',
                ]);
            }

            $status = strtolower(trim((string) ($payment['status'] ?? 'completed')));
            if (! in_array($status, $statusOptions, true)) {
                throw ValidationException::withMessages([
                    "payments.{$index}.status" => 'Select a valid payment status.',
                ]);
            }

            $amount = ExactMoney::normalize($payment['amount'] ?? '0.00');
            $lineAmountCents = $this->lineItemPayableCents($purchaseOrder, $lineItem);
            if (ExactMoney::cents($amount) > $lineAmountCents) {
                throw ValidationException::withMessages([
                    "payments.{$index}.amount" => 'Payment amount cannot exceed the selected item line amount of '.ExactMoney::fromCents($lineAmountCents).' '.$purchaseOrder->resolved_currency.'.',
                ]);
            }

            $referenceNo = trim((string) ($payment['reference_no'] ?? ''));
            $creationId = null;
            if ($existingId === '') {
                $candidateCreationId = trim((string) ($payment['creation_id'] ?? ''));
                $creationId = Str::isUuid($candidateCreationId)
                    ? $candidateCreationId
                    : (string) Str::uuid();
                $referenceNo = $referenceNo !== ''
                    ? $referenceNo
                    : ProcurementDisbursement::generateReference();
            }
            if ($referenceNo !== '') {
                $referenceKey = strtolower($referenceNo);
                if (isset($referenceLookup[$referenceKey])) {
                    throw ValidationException::withMessages([
                        "payments.{$index}.reference_no" => 'Each payment row must have a unique receipt reference.',
                    ]);
                }
                $referenceLookup[$referenceKey] = $index;
            }

            $normalized[] = [
                'index' => $index,
                'input_key' => $inputKey,
                'creation_id' => $creationId,
                'id' => $existingId !== '' ? $existingId : null,
                'reference_no' => $referenceNo !== '' ? $referenceNo : null,
                'purchase_request_item_id' => $lineItemId,
                'deliverable_id' => $lineItem->deliverable_id ? (string) $lineItem->deliverable_id : null,
                'amount' => $amount,
                'payment_method' => trim((string) ($payment['payment_method'] ?? '')),
                'transfer_reference' => trim((string) ($payment['transfer_reference'] ?? '')) ?: null,
                'status' => $status,
                'paid_at' => $payment['paid_at'] ?? now()->toDateString(),
                'notes' => trim((string) ($payment['notes'] ?? '')) ?: null,
            ];
        }

        $references = collect($normalized)
            ->pluck('reference_no')
            ->filter()
            ->values()
            ->all();

        if (! empty($references)) {
            $usedReference = ProcurementDisbursement::query()
                ->whereIn('reference_no', $references)
                ->when(! empty($activeExistingIds), fn ($query) => $query->whereNotIn('id', $activeExistingIds))
                ->value('reference_no');

            if ($usedReference) {
                $rowIndex = $referenceLookup[strtolower($usedReference)] ?? 0;
                throw ValidationException::withMessages([
                    "payments.{$rowIndex}.reference_no" => 'Receipt reference ' . $usedReference . ' is already in use.',
                ]);
            }
        }

        $baseLinePaidCents = $this->paidCentsByLineItemExcludingIds($purchaseOrder, $excludeDisbursementIds);
        $basePoPaidCents = $this->purchaseOrderPaidCentsExcludingIds($purchaseOrder, $excludeDisbursementIds);
        $submittedPaidByLine = [];
        $submittedRowByLine = [];
        $submittedPaidTotalCents = 0;

        foreach ($normalized as $paymentRow) {
            if (! $this->statusCountsAgainstPurchaseOrder($paymentRow['status'])) {
                continue;
            }

            $lineId = (string) $paymentRow['purchase_request_item_id'];
            $paymentCents = ExactMoney::cents($paymentRow['amount']);
            $submittedPaidByLine[$lineId] = ($submittedPaidByLine[$lineId] ?? 0) + $paymentCents;
            $submittedRowByLine[$lineId] ??= $paymentRow['index'];
            $submittedPaidTotalCents += $paymentCents;
        }

        foreach ($submittedPaidByLine as $lineId => $submittedCents) {
            $lineItem = $lineItems->get((string) $lineId);
            $lineAmountCents = $lineItem ? $this->lineItemPayableCents($purchaseOrder, $lineItem) : 0;
            $allowedCents = max($lineAmountCents - (int) $baseLinePaidCents->get((string) $lineId, 0), 0);

            if ($submittedCents > $allowedCents) {
                $rowIndex = $submittedRowByLine[$lineId] ?? 0;
                throw ValidationException::withMessages([
                    "payments.{$rowIndex}.amount" => 'Payment amount exceeds the selected item line balance of '.ExactMoney::fromCents($allowedCents).' '.$purchaseOrder->resolved_currency.'.',
                ]);
            }
        }

        $poAllowedCents = max(ExactMoney::cents($purchaseOrder->amount ?? '0.00') - $basePoPaidCents, 0);
        if ($submittedPaidTotalCents > $poAllowedCents) {
            throw ValidationException::withMessages([
                'payments' => 'Total paid amount exceeds the purchase order balance of '.ExactMoney::fromCents($poAllowedCents).' '.$purchaseOrder->resolved_currency.'.',
            ]);
        }

        return $normalized;
    }

    private function lineItemPayableCents(
        ProcurementPurchaseOrder $purchaseOrder,
        PurchaseRequestItem $lineItem,
    ): int {
        $evidence = $purchaseOrder->lineItemEvidenceFor($lineItem);
        $amount = $evidence && $evidence->delivered_amount !== null
            ? $evidence->delivered_amount
            : $lineItem->amount;

        return ExactMoney::cents($amount ?? '0.00');
    }

    private function paidCentsByLineItemExcludingIds(
        ProcurementPurchaseOrder $purchaseOrder,
        array $excludeDisbursementIds = [],
    ) {
        $purchaseOrder->loadMissing('disbursements');
        $excluded = collect($excludeDisbursementIds)
            ->map(fn ($id): string => (string) $id)
            ->all();

        return $purchaseOrder->disbursements
            ->reject(fn (ProcurementDisbursement $payment): bool => in_array((string) $payment->id, $excluded, true))
            ->filter(fn (ProcurementDisbursement $payment): bool => filled($payment->purchase_request_item_id)
                && $this->disbursementCountsAsPaid($payment))
            ->groupBy(fn (ProcurementDisbursement $payment): string => (string) $payment->purchase_request_item_id)
            ->map(fn ($payments): int => $payments
                ->sum(fn (ProcurementDisbursement $payment): int => ExactMoney::cents($payment->amount)));
    }

    private function purchaseOrderPaidCentsExcludingIds(
        ProcurementPurchaseOrder $purchaseOrder,
        array $excludeDisbursementIds = [],
    ): int {
        $purchaseOrder->loadMissing('disbursements');
        $excluded = collect($excludeDisbursementIds)
            ->map(fn ($id): string => (string) $id)
            ->all();

        return $purchaseOrder->disbursements
            ->reject(fn (ProcurementDisbursement $payment): bool => in_array((string) $payment->id, $excluded, true))
            ->filter(fn (ProcurementDisbursement $payment): bool => $this->disbursementCountsAsPaid($payment))
            ->sum(fn (ProcurementDisbursement $payment): int => ExactMoney::cents($payment->amount));
    }

    private function createDisbursementForPaymentRow(
        ProcurementPurchaseOrder $purchaseOrder,
        array $paymentRow,
        ?array $payload = null,
    ): ProcurementDisbursement {
        $creationId = trim((string) ($paymentRow['creation_id'] ?? ''));
        if (! Str::isUuid($creationId)) {
            throw ValidationException::withMessages([
                'payments' => 'A stable server payment identity could not be established. Refresh the form and try again.',
            ]);
        }

        return ProcurementDisbursement::query()->forceCreate([
            'id' => $creationId,
            ...($payload ?? $this->disbursementPayloadForPaymentRow($purchaseOrder, $paymentRow)),
        ]);
    }

    private function disbursementPayloadForPaymentRow(
        ProcurementPurchaseOrder $purchaseOrder,
        array $paymentRow,
        ?ProcurementDisbursement $existing = null
    ): array {
        $referenceNo = $paymentRow['reference_no']
            ?: ($existing?->reference_no ?: ProcurementDisbursement::generateReference());

        $payload = [
            'purchase_order_id'  => $purchaseOrder->id,
            'purchase_request_item_id' => $paymentRow['purchase_request_item_id'],
            'deliverable_id'     => $paymentRow['deliverable_id'],
            'procurement_id'     => $purchaseOrder->procurement_id,
            'vendor_id'          => $purchaseOrder->vendor_id,
            'sub_activity_id'    => $purchaseOrder->sub_activity_id,
            'governance_node_id' => $purchaseOrder->governance_node_id,
            'consortium_id'      => $purchaseOrder->consortium_id,
            'think_tank_member_id' => $purchaseOrder->think_tank_member_id,
            'reference_no'       => $referenceNo,
            'amount'             => $paymentRow['amount'],
            'currency'           => $purchaseOrder->resolved_currency,
            'payment_method'     => $paymentRow['payment_method'],
            'transfer_reference' => $paymentRow['transfer_reference'],
            'status'             => $paymentRow['status'],
            'paid_at'            => $paymentRow['paid_at'],
            'notes'              => $paymentRow['notes'],
            'procurement_processing_status' => $existing?->procurement_processing_status ?: ProcurementDisbursement::PROCUREMENT_STATUS_PENDING,
        ];

        if (! $existing) {
            $payload['created_by'] = auth()->id();
        }

        return $payload;
    }

    private function disbursementCountsAsPaid(ProcurementDisbursement $disbursement): bool
    {
        return (bool) $disbursement->paid_at
            && $this->statusCountsAgainstPurchaseOrder($disbursement->status ?? 'completed');
    }

    private function isGovernedThinkTankFundingReceipt(ProcurementPurchaseOrder $purchaseOrder): bool
    {
        if (strtolower((string) $purchaseOrder->po_type) === 'think_tank_transfer') {
            return true;
        }
        if (! filled($purchaseOrder->think_tank_member_id) || ! filled($purchaseOrder->consortium_id)) {
            return false;
        }

        $member = ConsortiumThinkTank::query()
            ->whereKey($purchaseOrder->think_tank_member_id)
            ->where('consortium_id', $purchaseOrder->consortium_id)
            ->first();
        if (! $member) {
            return false;
        }

        return app(ThinkTankFinanceApiService::class)
            ->incomingFundingPurchaseOrdersQuery($member)
            ->whereKey($purchaseOrder->id)
            ->exists();
    }

    private function assertNotGovernedThinkTankFundingReceiptMutation(
        ProcurementPurchaseOrder $purchaseOrder,
    ): void {
        if ($this->isGovernedThinkTankFundingReceipt($purchaseOrder)) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'Secretariat-to-Think-Tank funding receipts cannot be created or edited through procurement payments. Use the governed funding-transfer workflow.',
            ]);
        }
    }

    /** @return array{0: ProcurementDisbursementSubmissionBatch, 1: bool} */
    private function claimDisbursementBatch(
        string $idempotencyKey,
        string $fingerprint,
        string $operation,
        ProcurementPurchaseOrder $purchaseOrder,
        mixed $actorId,
    ): array {
        $existing = ProcurementDisbursementSubmissionBatch::query()
            ->where('idempotency_key', $idempotencyKey)
            ->lockForUpdate()
            ->first();
        if ($existing) {
            $this->assertDisbursementBatchReplayMatches(
                $existing,
                $fingerprint,
                $operation,
                $purchaseOrder,
                $actorId,
            );

            return [$existing, true];
        }

        return [ProcurementDisbursementSubmissionBatch::query()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'actor_id' => filled($actorId) ? $actorId : null,
            'operation' => $operation,
            'idempotency_key' => $idempotencyKey,
            'fingerprint' => $fingerprint,
            'result_payment_ids' => null,
            'status' => 'processing',
        ]), false];
    }

    private function disbursementBatchReplay(
        string $idempotencyKey,
        string $fingerprint,
        string $operation,
        ProcurementPurchaseOrder $purchaseOrder,
        mixed $actorId,
    ): ?ProcurementDisbursementSubmissionBatch {
        $existing = ProcurementDisbursementSubmissionBatch::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if (! $existing) {
            return null;
        }

        $this->assertDisbursementBatchReplayMatches(
            $existing,
            $fingerprint,
            $operation,
            $purchaseOrder,
            $actorId,
        );

        return $existing;
    }

    private function assertDisbursementBatchReplayMatches(
        ProcurementDisbursementSubmissionBatch $batch,
        string $fingerprint,
        string $operation,
        ProcurementPurchaseOrder $purchaseOrder,
        mixed $actorId,
    ): void {
        if ((string) $batch->purchase_order_id !== (string) $purchaseOrder->id
            || (string) $batch->actor_id !== (string) $actorId
            || $batch->operation !== $operation) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This disbursement submission key belongs to a different user, purchase order, or operation.',
            ]);
        }
        $this->submissionIdempotency->assertReplayMatches($batch->fingerprint, $fingerprint);
        if ($batch->status !== 'completed' || ! is_array($batch->result_payment_ids)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The earlier disbursement submission did not complete cleanly. Contact an administrator before retrying.',
            ]);
        }
    }

    private function paymentsForBatch(
        ProcurementDisbursementSubmissionBatch $batch,
    ): \Illuminate\Support\Collection {
        $ids = collect($batch->result_payment_ids ?? [])->map(fn ($id): string => (string) $id)->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        $byId = ProcurementDisbursement::query()->whereIn('id', $ids)->get()->keyBy(
            fn (ProcurementDisbursement $payment): string => (string) $payment->id
        );

        return $ids->map(fn (string $id) => $byId->get($id))->filter()->values();
    }

    private function purchaseOrderStatusAfterPaymentSync(
        string $currentStatus,
        float $totalPaid,
        float $remaining,
    ): string {
        if ($totalPaid > 0) {
            return $remaining <= 0 ? 'paid' : 'partial_paid';
        }

        $currentStatus = strtolower(trim($currentStatus));

        // Reversing the final recognized payment must not erase the underlying
        // contractual commitment. Payment-derived statuses return to issued;
        // every other lifecycle status (including draft/closed/cancelled) is
        // preserved instead of being silently rewritten to draft.
        if (in_array($currentStatus, ['partial_paid', 'partially_paid', 'paid', 'fully_paid'], true)) {
            return 'issued';
        }

        return $currentStatus !== '' ? $currentStatus : 'draft';
    }

    /**
     * Recognized payments form an append-only accounting record. Corrections
     * must use the explicit reversal action and then create a replacement.
     * Supporting evidence may still be appended to an otherwise unchanged row.
     *
     * @param  iterable<int, ProcurementDisbursement>  $payments
     * @param  array<int, array<string, mixed>>  $paymentRows
     * @param  iterable<int, string>  $deleteIds
     */
    private function assertRecognizedPaymentMutationsUseReversal(
        iterable $payments,
        array $paymentRows,
        iterable $deleteIds,
    ): void {
        $payments = collect($payments);
        $recognized = $payments
            ->filter(fn (ProcurementDisbursement $payment): bool => $this->disbursementCountsAsPaid($payment));
        $nonEditable = $payments
            ->filter(fn (ProcurementDisbursement $payment): bool => in_array(
                strtolower(trim((string) $payment->status)),
                ['void', 'reversed'],
                true,
            ));
        $protected = $recognized->merge($nonEditable)->unique(fn (ProcurementDisbursement $payment): string => (string) $payment->id);
        $deleted = collect($deleteIds)->map(fn ($id): string => (string) $id);

        if ($protected->contains(fn (ProcurementDisbursement $payment): bool => $deleted->contains((string) $payment->id))) {
            throw ValidationException::withMessages([
                'delete_payment_ids' => 'A recognized, voided, or reversed payment cannot be removed. Use Revert Payment so the original receipt remains in the audit trail.',
            ]);
        }

        $submitted = collect($paymentRows)
            ->filter(fn (array $row): bool => filled($row['id'] ?? null))
            ->keyBy(fn (array $row): string => (string) $row['id']);
        foreach ($protected as $payment) {
            $row = $submitted->get((string) $payment->id);
            if (! is_array($row)) {
                continue;
            }

            $current = [
                'reference_no' => $this->nullablePaymentText($payment->reference_no),
                'purchase_request_item_id' => (string) $payment->purchase_request_item_id,
                'deliverable_id' => $payment->deliverable_id ? (string) $payment->deliverable_id : null,
                'amount' => $this->paymentAmountCents($payment->amount),
                'payment_method' => trim((string) $payment->payment_method),
                'transfer_reference' => $this->nullablePaymentText($payment->transfer_reference),
                'status' => strtolower(trim((string) $payment->status)),
                'paid_at' => $payment->paid_at?->toDateString(),
                'notes' => $this->nullablePaymentText($payment->notes),
            ];
            $proposed = [
                'reference_no' => $this->nullablePaymentText($row['reference_no'] ?? null),
                'purchase_request_item_id' => (string) ($row['purchase_request_item_id'] ?? ''),
                'deliverable_id' => filled($row['deliverable_id'] ?? null) ? (string) $row['deliverable_id'] : null,
                'amount' => $this->paymentAmountCents($row['amount'] ?? 0),
                'payment_method' => trim((string) ($row['payment_method'] ?? '')),
                'transfer_reference' => $this->nullablePaymentText($row['transfer_reference'] ?? null),
                'status' => strtolower(trim((string) ($row['status'] ?? ''))),
                'paid_at' => $this->paymentDate($row['paid_at'] ?? null),
                'notes' => $this->nullablePaymentText($row['notes'] ?? null),
            ];

            if ($current !== $proposed) {
                $index = $row['index'] ?? 0;
                throw ValidationException::withMessages([
                    "payments.{$index}.id" => 'A recognized, voided, or reversed payment is immutable. Use Revert Payment, then record the corrected payment as a new receipt.',
                ]);
            }
        }
    }

    private function nullablePaymentText(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function paymentAmountCents(mixed $amount): int
    {
        return ExactMoney::cents($amount);
    }

    private function paymentDate(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function summaryCurrencyFor($records): string
    {
        $currencies = collect($records)
            ->map(fn ($record) => $record->resolved_currency ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($currencies->count() === 1) {
            return (string) $currencies->first();
        }

        return $currencies->isEmpty() ? 'USD' : 'Mixed';
    }

    private function statusCountsAgainstPurchaseOrder(?string $status): bool
    {
        $status = strtolower((string) ($status ?: 'completed'));

        return in_array($status, ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES, true);
    }

    private function canEditDisbursements(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->isAdmin() || $user->isSuperAdmin()));
    }

    private function canHandleProcurementProcessing(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return true;
        }

        if (method_exists($user, 'hasPermission') && $user->hasPermission('finance.purchase_orders.create')) {
            return true;
        }

        return str_contains(strtolower((string) ($user->role?->name ?? '')), 'procurement');
    }

    private function authorizeDisbursementEdit(): void
    {
        if (! $this->canEditDisbursements()) {
            abort(403, 'Only administrators can edit disbursements.');
        }
    }

    private function authorizeDisbursementRevert(): void
    {
        if (! $this->canEditDisbursements()) {
            abort(403, 'Only administrators can revert disbursement payments.');
        }
    }

    private function detectWordDocumentExtension(string $path): string
    {
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            return '';
        }

        $absolutePath = $disk->path($path);
        $handle = @fopen($absolutePath, 'rb');
        if (! is_resource($handle)) {
            return '';
        }

        $signature = fread($handle, 8) ?: '';
        fclose($handle);

        if (str_starts_with($signature, "PK\x03\x04") && class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            $opened = $zip->open($absolutePath);
            if ($opened === true) {
                $isDocx = $zip->locateName('word/document.xml') !== false;
                $zip->close();

                return $isDocx ? 'docx' : '';
            }
        }

        return str_starts_with($signature, "\xD0\xCF\x11\xE0") ? 'doc' : '';
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

    private function disbursementStatusOptions(): array
    {
        return [
            'completed' => 'Completed',
            'paid' => 'Paid',
            'fully_paid' => 'Fully Paid',
            'pending' => 'Pending',
            'cancelled' => 'Cancelled',
            'void' => 'Void',
            'reversed' => 'Reversed',
        ];
    }

    private function storeLineItemEvidence(Request $request, ProcurementPurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing([
            'lineItemEvidence',
            'purchaseRequest.items.deliverable',
            'budgetCommitment.purchaseRequest.items.deliverable',
        ]);

        $sourcePurchaseRequest = $purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest;
        if (! $sourcePurchaseRequest) {
            return;
        }

        $evidenceInput = $request->input('item_evidence', []);
        $filesInput = $request->file('item_evidence', []);
        $items = $sourcePurchaseRequest->items->keyBy(fn ($item) => (string) $item->id);
        $existingEvidence = $purchaseOrder->lineItemEvidence->keyBy(fn (ProcurementPurchaseOrderItemEvidence $evidence) => (string) $evidence->purchase_request_item_id);

        foreach ($evidenceInput as $itemId => $input) {
            if (! $items->has((string) $itemId)) {
                throw ValidationException::withMessages([
                    'item_evidence' => 'One or more line item evidence records do not belong to the selected purchase order.',
                ]);
            }

            $item = $items->get((string) $itemId);
            $existing = $existingEvidence->get((string) $itemId);
            $documents = collect($existing?->documents ?? [])
                ->filter(fn ($document) => is_array($document))
                ->values()
                ->all();
            $documentNames = $input['document_names'] ?? [];

            foreach (($filesInput[$itemId]['documents'] ?? []) as $index => $file) {
                if (! $file || ! $file->isValid()) {
                    continue;
                }

                $displayName = trim((string) ($documentNames[$index] ?? ''));
                $path = $file->store("procurement_purchase_orders/{$purchaseOrder->id}/line-item-evidence/{$itemId}");
                $documents[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'display_name' => $displayName !== '' ? $displayName : null,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => auth()->id(),
                    'uploaded_at' => now()->toIso8601String(),
                ];
            }

            $isMet = (bool) ($input['is_met'] ?? false);
            $deliverableDate = trim((string) ($input['deliverable_date'] ?? ''));
            $notes = trim((string) ($input['notes'] ?? ''));
            $hasDeliveredPricing = $existing
                && ($existing->delivered_unit_price !== null
                    || $existing->delivered_quantity !== null
                    || $existing->delivered_amount !== null);

            if (! $isMet && $deliverableDate === '' && $notes === '' && empty($documents) && ! $hasDeliveredPricing) {
                if ($existing) {
                    $existing->delete();
                }

                continue;
            }

            ProcurementPurchaseOrderItemEvidence::updateOrCreate(
                [
                    'purchase_order_id' => $purchaseOrder->id,
                    'purchase_request_item_id' => $itemId,
                ],
                [
                    'deliverable_id' => $item->deliverable_id,
                    'is_met' => $isMet,
                    'deliverable_date' => $deliverableDate !== '' ? $deliverableDate : null,
                    'notes' => $notes !== '' ? $notes : null,
                    'documents' => $documents,
                    'created_by' => auth()->id(),
                ]
            );
        }
    }

    private function storeSignedPaymentDocuments(Request $request, ProcurementDisbursement $disbursement, ?string $inputKey): void
    {
        if ($inputKey === null || $inputKey === '') {
            return;
        }

        $paymentFiles = $request->file('payments', []);
        $paymentInputs = $request->input('payments', []);
        $files = $paymentFiles[$inputKey]['signed_documents'] ?? [];
        $names = $paymentInputs[$inputKey]['signed_document_names'] ?? [];
        $metaInputs = $paymentInputs[$inputKey]['signed_document_meta'] ?? [];

        if (! is_array($files) || empty($files)) {
            return;
        }

        $documents = collect($disbursement->signed_documents ?? [])
            ->filter(fn ($document) => is_array($document))
            ->values()
            ->all();
        $newDocumentIndexes = [];

        foreach ($files as $index => $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $displayName = trim((string) ($names[$index] ?? ''));
            $metadata = $this->decodeSignedDocumentMetadata($metaInputs[$index] ?? null);
            $path = $file->store("procurement_disbursements/{$disbursement->id}/signed-documents");

            $document = [
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'display_name' => $displayName !== '' ? $displayName : null,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now()->toIso8601String(),
            ];

            if ($metadata !== []) {
                $document = array_merge($document, $metadata, [
                    'is_digital_signature' => (bool) ($metadata['is_digital_signature'] ?? true),
                ]);
            }

            if (empty($document['digital_signature_code'])) {
                $document['digital_signature_code'] = $this->generateSignedDocumentCode($disbursement);
            }

            $newDocumentIndexes[] = count($documents);
            $documents[] = $document;
        }

        $disbursement->forceFill(['signed_documents' => $documents])->save();

        $service = app(SignedDisbursementDocumentService::class);
        foreach ($newDocumentIndexes as $documentIndex) {
            $service->ensurePdf($disbursement->fresh(), $documentIndex);
        }
    }

    private function decodeSignedDocumentMetadata(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return [];
        }

        $allowed = [
            'is_digital_signature',
            'digital_signature_code',
            'source_document_name',
            'source_item_label',
            'source_deliverable',
            'source_purchase_order',
            'signed_by_name',
            'signed_by_email',
            'signed_at',
            'signature_position',
        ];

        return collect($decoded)
            ->only($allowed)
            ->map(fn ($item) => is_bool($item) ? $item : trim((string) $item))
            ->filter(fn ($item) => is_bool($item) || $item !== '')
            ->all();
    }

    private function generateSignedDocumentCode(ProcurementDisbursement $disbursement): string
    {
        $datePart = now()->format('Ymd');
        $receiptPart = (string) Str::of($disbursement->reference_no ?: 'ATTP')
            ->replaceMatches('/[^A-Za-z0-9]+/', '')
            ->upper()
            ->substr(-8);
        $receiptPart = $receiptPart !== '' ? $receiptPart : 'ATTP';

        return 'ATTP-SD-' . $datePart . '-' . $receiptPart . '-' . Str::upper(Str::random(6));
    }

    private function sendReceipt(ProcurementDisbursement $disbursement): void
    {
        $vendor = $disbursement->vendor;
        if (!$vendor || empty($vendor->email)) {
            return;
        }

        $disbursement->loadMissing([
            'purchaseOrder',
            'purchaseRequestItem.resourceCategory',
            'purchaseRequestItem.resource',
            'purchaseRequestItem.deliverable.procurement',
            'deliverable.procurement',
            'procurement',
            'subActivity',
        ]);

        $pdf = Pdf::loadView('procurement.disbursements.pdf', [
            'disbursement' => $disbursement,
        ]);

        $mail = new VendorDisbursementReceipt($disbursement, $pdf->output());

        try {
            Mail::to($vendor->email)->send($mail);
        } catch (\Throwable $exception) {
            logger()->error('Disbursement receipt email failed.', [
                'disbursement_id' => $disbursement->id,
                'vendor_id' => $vendor->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function assertPurchaseOrderInScope(ProcurementPurchaseOrder $purchaseOrder): void
    {
        $currentUser = auth()->user();
        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            if (! $this->purchaseOrderIsInAssignedPortfolio($purchaseOrder, $currentUser)) {
                abort(403, 'You do not have access to this purchase order.');
            }

            return;
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$purchaseOrder->governance_node_id || !in_array($purchaseOrder->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this purchase order.');
        }
    }

    private function assertDisbursementInScope(ProcurementDisbursement $disbursement): void
    {
        $currentUser = auth()->user();
        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            if (! $this->disbursementIsInAssignedPortfolio($disbursement, $currentUser)) {
                abort(403, 'You do not have access to this disbursement.');
            }

            return;
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$disbursement->governance_node_id || !in_array($disbursement->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this disbursement.');
        }
    }
}
