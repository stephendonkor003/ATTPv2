<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Mail\VendorDisbursementReceipt;
use App\Models\ProcurementAuditLog;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ProcurementPurchaseOrderItemEvidence;
use App\Models\PurchaseRequestItem;
use App\Services\ProcurementDisbursementHandoffNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class ProcurementDisbursementController extends Controller
{
    use GovernanceScope;

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner', 'permission:finance.purchase_requests.view']);
    }

    public function index()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to disbursements.');
        }

        $baseQuery = ProcurementDisbursement::query()
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            });

        $paidQuery = (clone $baseQuery)
            ->whereNotNull('paid_at')
            ->whereIn('status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES);

        $summaryCurrencyDisbursements = (clone $baseQuery)
            ->with([
                'purchaseOrder.purchaseRequest.programFunding.program',
                'purchaseOrder.budgetCommitment.programFunding.program',
                'purchaseOrder.budgetCommitment.purchaseRequest.programFunding.program',
            ])
            ->get(['id', 'purchase_order_id', 'currency']);

        $disbursementSummary = [
            'currency' => $this->summaryCurrencyFor($summaryCurrencyDisbursements),
            'total_receipts' => (clone $baseQuery)->count(),
            'total_paid_amount' => (float) (clone $paidQuery)->sum('amount'),
            'this_month_paid_amount' => (float) (clone $paidQuery)
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'pending_amount' => (float) (clone $baseQuery)
                ->where(function ($query) {
                    $query->whereNull('paid_at')
                        ->orWhereNotIn('status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES);
                })
                ->sum('amount'),
            'paid_purchase_orders' => (clone $paidQuery)
                ->whereNotNull('purchase_order_id')
                ->distinct()
                ->count('purchase_order_id'),
            'paid_line_items' => (clone $paidQuery)
                ->whereNotNull('purchase_request_item_id')
                ->distinct()
                ->count('purchase_request_item_id'),
        ];

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

    public function create(Request $request)
    {
        $purchaseOrderId = $request->get('purchase_order_id');
        $paymentMethods = $this->paymentMethods();

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
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
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
                                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                                $previewUrl = route('procurement.purchase-orders.line-item-evidence.document', [$order, $evidence, $index]);
                                $publicPreviewUrl = URL::temporarySignedRoute(
                                    'procurement.purchase-orders.line-item-evidence.public-preview',
                                    now()->addMinutes(45),
                                    [$order, $evidence, $index]
                                );

                                return [
                                    'name' => $name,
                                    'display_name' => $document['display_name'] ?? null,
                                    'mime_type' => $document['mime_type'] ?? null,
                                    'extension' => $extension,
                                    'url' => $previewUrl,
                                    'preview_url' => $previewUrl,
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
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_order_id'  => 'required|exists:procurement_purchase_orders,id',
            'payments' => ['required', 'array', 'min:1', 'max:50'],
            'payments.*.reference_no' => ['nullable', 'string', 'max:100'],
            'payments.*.purchase_request_item_id' => ['required', 'exists:myb_purchase_request_items,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.payment_method' => ['required', 'string', 'max:100'],
            'payments.*.transfer_reference' => ['nullable', 'string', 'max:255'],
            'payments.*.status' => ['nullable', 'string', 'in:' . implode(',', array_keys($this->disbursementStatusOptions()))],
            'payments.*.paid_at' => ['required', 'date'],
            'payments.*.notes' => ['nullable', 'string', 'max:2000'],
            'payments.*.signed_document_names' => ['nullable', 'array', 'max:20'],
            'payments.*.signed_document_names.*' => ['nullable', 'string', 'max:255'],
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

        $paymentRows = $this->validatedPaymentRows($purchaseOrder, $data['payments']);

        $this->storeLineItemEvidence($request, $purchaseOrder);

        $disbursements = collect();

        DB::transaction(function () use ($purchaseOrder, $paymentRows, $request, &$disbursements) {
            foreach ($paymentRows as $paymentRow) {
                $disbursement = ProcurementDisbursement::create($this->disbursementPayloadForPaymentRow($purchaseOrder, $paymentRow));
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
        });

        $disbursements->each(fn (ProcurementDisbursement $row) => $this->sendReceipt($row->fresh()));
        $handoffNotifier = app(ProcurementDisbursementHandoffNotificationService::class);
        $disbursements->each(fn (ProcurementDisbursement $row) => $handoffNotifier->notify($row->fresh()));

        $message = $disbursements->count() === 1
            ? 'Disbursement recorded and receipt sent.'
            : $disbursements->count() . ' disbursements recorded and receipts sent.';

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

        $data = $request->validate([
            'payments' => ['nullable', 'array', 'max:50'],
            'payments.*.id' => ['nullable', 'exists:procurement_disbursements,id'],
            'payments.*.reference_no' => ['nullable', 'string', 'max:100'],
            'payments.*.purchase_request_item_id' => ['required', 'exists:myb_purchase_request_items,id'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.payment_method' => ['required', 'string', 'max:100'],
            'payments.*.transfer_reference' => ['nullable', 'string', 'max:255'],
            'payments.*.status' => ['required', 'string', 'in:' . implode(',', array_keys($this->disbursementStatusOptions()))],
            'payments.*.paid_at' => ['required', 'date'],
            'payments.*.notes' => ['nullable', 'string', 'max:2000'],
            'payments.*.signed_document_names' => ['nullable', 'array', 'max:20'],
            'payments.*.signed_document_names.*' => ['nullable', 'string', 'max:255'],
            'payments.*.signed_documents' => ['nullable', 'array', 'max:20'],
            'payments.*.signed_documents.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'max:20480'],
            'delete_payment_ids' => ['nullable', 'array'],
            'delete_payment_ids.*' => ['nullable', 'exists:procurement_disbursements,id'],
        ], [
            'payments.*.signed_documents.*.mimes' => 'Signed payment documents must be a PDF, Office document, image, or ZIP file.',
        ]);

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

        $updatedDisbursements = collect();
        $createdDisbursements = collect();

        DB::transaction(function () use (
            $purchaseOrder,
            $editableDisbursements,
            $deleteIds,
            $paymentRows,
            $before,
            $request,
            &$updatedDisbursements,
            &$createdDisbursements
        ) {
            $editableById = $editableDisbursements->keyBy(fn (ProcurementDisbursement $row) => (string) $row->id);

            foreach ($deleteIds as $deleteId) {
                $editableById->get((string) $deleteId)?->delete();
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
                    $newDisbursement = ProcurementDisbursement::create($payload);
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
                    'deleted_disbursement_ids' => $deleteIds->all(),
                    'updated_disbursement_ids' => $updatedDisbursements->pluck('id')->all(),
                    'created_disbursement_ids' => $createdDisbursements->pluck('id')->all(),
                    'before' => $before,
                    'after' => $after,
                ],
                'created_at' => now(),
            ]);
        });

        $createdDisbursements->each(fn (ProcurementDisbursement $row) => $this->sendReceipt($row->fresh()));
        $handoffNotifier = app(ProcurementDisbursementHandoffNotificationService::class);
        $createdDisbursements
            ->merge($updatedDisbursements->filter(fn (ProcurementDisbursement $row) => ! $row->procurement_notified_at))
            ->each(fn (ProcurementDisbursement $row) => $handoffNotifier->notify($row->fresh()));

        $freshDisbursement = ProcurementDisbursement::find($disbursement->id);
        $redirectRoute = $freshDisbursement
            ? route('procurement.disbursements.show', $freshDisbursement)
            : route('procurement.disbursements.index');

        return redirect($redirectRoute)
            ->with('success', 'Disbursement payment lines updated.');
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

        $documents = $disbursement->signed_documents ?? [];
        $file = $documents[$document] ?? null;
        abort_unless(is_array($file) && ! empty($file['path']), 404, 'Signed document not found.');

        $privateDisk = Storage::disk('local');
        abort_unless($privateDisk->exists($file['path']), 404, 'Signed document file missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $fileName = $file['name'] ?? basename($file['path']);

        if ($request->boolean('download')) {
            return $privateDisk->download($file['path'], $fileName, $headers);
        }

        return $privateDisk->response($file['path'], $fileName, $headers);
    }

    public function destroy(ProcurementDisbursement $disbursement)
    {
        $this->authorizeDisbursementRevert();
        $this->assertDisbursementInScope($disbursement);

        $disbursement->load('purchaseOrder');

        $purchaseOrder = $disbursement->purchaseOrder;
        if ($purchaseOrder) {
            $this->assertPurchaseOrderInScope($purchaseOrder);
        }

        DB::transaction(function () use ($disbursement, $purchaseOrder) {
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
        });

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

        $status = $totalPaid <= 0 ? 'draft' : ($remaining <= 0 ? 'paid' : 'partial_paid');

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

            $amount = round((float) ($payment['amount'] ?? 0), 2);
            $lineAmount = $purchaseOrder->lineItemPayableAmount($lineItem);
            if ($amount > $lineAmount) {
                throw ValidationException::withMessages([
                    "payments.{$index}.amount" => 'Payment amount cannot exceed the selected item line amount of ' . number_format($lineAmount, 2) . ' ' . $purchaseOrder->resolved_currency . '.',
                ]);
            }

            $referenceNo = trim((string) ($payment['reference_no'] ?? ''));
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
                'input_key' => (string) $index,
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

        $baseLinePaid = $this->paidAmountsByLineItemForPurchaseOrderExcludingIds($purchaseOrder, $excludeDisbursementIds);
        $basePoPaid = $this->purchaseOrderPaidAmountExcludingIds($purchaseOrder, $excludeDisbursementIds);
        $submittedPaidByLine = [];
        $submittedRowByLine = [];
        $submittedPaidTotal = 0.0;

        foreach ($normalized as $paymentRow) {
            if (! $this->statusCountsAgainstPurchaseOrder($paymentRow['status'])) {
                continue;
            }

            $lineId = (string) $paymentRow['purchase_request_item_id'];
            $submittedPaidByLine[$lineId] = round(($submittedPaidByLine[$lineId] ?? 0) + $paymentRow['amount'], 2);
            $submittedRowByLine[$lineId] ??= $paymentRow['index'];
            $submittedPaidTotal = round($submittedPaidTotal + $paymentRow['amount'], 2);
        }

        foreach ($submittedPaidByLine as $lineId => $submittedAmount) {
            $lineItem = $lineItems->get((string) $lineId);
            $lineAmount = $lineItem ? $purchaseOrder->lineItemPayableAmount($lineItem) : 0.0;
            $allowed = round(max($lineAmount - (float) $baseLinePaid->get((string) $lineId, 0), 0), 2);

            if ($submittedAmount > $allowed + 0.004) {
                $rowIndex = $submittedRowByLine[$lineId] ?? 0;
                throw ValidationException::withMessages([
                    "payments.{$rowIndex}.amount" => 'Payment amount exceeds the selected item line balance of ' . number_format($allowed, 2) . ' ' . $purchaseOrder->resolved_currency . '.',
                ]);
            }
        }

        $poAmount = round((float) ($purchaseOrder->amount ?? 0), 2);
        $poAllowed = round(max($poAmount - $basePoPaid, 0), 2);
        if ($submittedPaidTotal > $poAllowed + 0.004) {
            throw ValidationException::withMessages([
                'payments' => 'Total paid amount exceeds the purchase order balance of ' . number_format($poAllowed, 2) . ' ' . $purchaseOrder->resolved_currency . '.',
            ]);
        }

        return $normalized;
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

        if (! is_array($files) || empty($files)) {
            return;
        }

        $documents = collect($disbursement->signed_documents ?? [])
            ->filter(fn ($document) => is_array($document))
            ->values()
            ->all();

        foreach ($files as $index => $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $displayName = trim((string) ($names[$index] ?? ''));
            $path = $file->store("procurement_disbursements/{$disbursement->id}/signed-documents");

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

        $disbursement->forceFill(['signed_documents' => $documents])->save();
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
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$disbursement->governance_node_id || !in_array($disbursement->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this disbursement.');
        }
    }
}
