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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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

        $disbursements = ProcurementDisbursement::with(['purchaseOrder', 'deliverable.procurement', 'vendor', 'procurement', 'thinkTankMember', 'consortium'])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderByDesc('paid_at')
            ->paginate(12);

        $canEditDisbursements = $this->canEditDisbursements();

        return view('procurement.disbursements.index', compact('disbursements', 'canEditDisbursements'));
    }

    public function create(Request $request)
    {
        $purchaseOrderId = $request->get('purchase_order_id');
        $paymentMethods = $this->paymentMethods();

        $scopedNodeIds = $this->scopedNodeIds();

        $purchaseOrders = ProcurementPurchaseOrder::with([
            'procurement', 'vendor', 'disbursements', 'thinkTankMember',
            'consortium', 'subActivity', 'governanceNode',
            'deliverables.procurement',
            'lineItemEvidence',
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
            'purchaseRequest.items.deliverable.procurement',
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'budgetCommitment.purchaseRequest.items.deliverable.procurement',
        ])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn (ProcurementPurchaseOrder $order) => $order->remainingAmount() > 0)
            ->values();

        $purchaseOrder = $purchaseOrderId
            ? $purchaseOrders->firstWhere('id', $purchaseOrderId)
            : null;

        // If coming from a PO that has no remaining balance, still show it (controller will reject on store)
        if ($purchaseOrderId && !$purchaseOrder) {
            $po = ProcurementPurchaseOrder::with([
                'procurement', 'vendor', 'disbursements', 'thinkTankMember',
                'consortium', 'subActivity', 'governanceNode',
                'deliverables.procurement',
                'lineItemEvidence',
                'purchaseRequest.items.resourceCategory',
                'purchaseRequest.items.resource',
                'purchaseRequest.items.deliverable.procurement',
                'budgetCommitment.purchaseRequest.items.resourceCategory',
                'budgetCommitment.purchaseRequest.items.resource',
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
            $evidenceByItem = $order->lineItemEvidence->keyBy(fn (ProcurementPurchaseOrderItemEvidence $evidence) => (string) $evidence->purchase_request_item_id);
            $poAmount = round((float) ($order->amount ?? 0), 2);
            $paidAmount = round($order->paidAmount(), 2);
            $balanceAmount = round(max($poAmount - $paidAmount, 0), 2);
            $lineItems = $sourcePurchaseRequest?->items?->map(function ($item) use ($evidenceByItem, $order) {
                $evidence = $evidenceByItem->get((string) $item->id);

                return [
                    'id' => (string) $item->id,
                    'category' => $item->resourceCategory?->name ?: 'N/A',
                    'resource' => $item->resource?->name ?: 'N/A',
                    'description' => $item->observations ?: $item->object_type ?: '',
                    'budget_code' => $item->budget_code,
                    'amount' => round((float) $item->amount, 2),
                    'deliverable_id' => $item->deliverable_id ? (string) $item->deliverable_id : null,
                    'deliverable_title' => $item->milestone ?: $item->deliverable?->title,
                    'evidence' => $evidence ? [
                        'is_met' => (bool) $evidence->is_met,
                        'deliverable_date' => $evidence->deliverable_date?->format('Y-m-d'),
                        'notes' => $evidence->notes,
                        'documents' => collect($evidence->documents ?? [])
                            ->map(fn ($document, $index) => [
                                'name' => $document['name'] ?? 'Document',
                                'display_name' => $document['display_name'] ?? null,
                                'url' => route('procurement.purchase-orders.line-item-evidence.document', [$order, $evidence, $index]) . '?download=1',
                            ])
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
                    'currency'             => $order->currency ?? 'USD',
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
                        'currency'        => $deliverable->currency,
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
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_order_id'  => 'required|exists:procurement_purchase_orders,id',
            'deliverable_id'     => 'nullable|exists:procurement_deliverables,id',
            'amount'             => 'required|numeric|min:0.01',
            'payment_method'     => 'required|string|max:100',
            'transfer_reference' => 'nullable|string|max:255',
            'paid_at'            => 'required|date',
            'notes'              => 'nullable|string|max:2000',
            'item_evidence' => ['nullable', 'array'],
            'item_evidence.*.is_met' => ['nullable', 'boolean'],
            'item_evidence.*.deliverable_date' => ['nullable', 'date'],
            'item_evidence.*.notes' => ['nullable', 'string', 'max:3000'],
            'item_evidence.*.document_names' => ['nullable', 'array', 'max:20'],
            'item_evidence.*.document_names.*' => ['nullable', 'string', 'max:255'],
            'item_evidence.*.documents' => ['nullable', 'array', 'max:20'],
            'item_evidence.*.documents.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'max:20480'],
        ], [
            'item_evidence.*.documents.*.mimes' => 'Line item evidence must be a PDF, Office document, image, or ZIP file.',
        ]);

        $purchaseOrder = ProcurementPurchaseOrder::with([
            'procurement',
            'vendor',
            'subActivity',
            'disbursements',
            'thinkTankMember',
            'consortium',
            'deliverables.procurement',
            'lineItemEvidence',
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
            'purchaseRequest.items.deliverable.procurement',
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'budgetCommitment.purchaseRequest.items.deliverable.procurement',
        ])
            ->findOrFail($data['purchase_order_id']);
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $eligibleDeliverables = $this->eligibleDeliverablesForPurchaseOrder($purchaseOrder);
        $eligibleDeliverableIds = $eligibleDeliverables
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values();

        $selectedDeliverableId = $data['deliverable_id'] ?? null;
        if (empty($selectedDeliverableId)) {
            $selectedDeliverableId = $this->inferDisbursementDeliverableId($request, $purchaseOrder, $eligibleDeliverableIds);
        }

        if (!empty($selectedDeliverableId) && !$eligibleDeliverableIds->contains((string) $selectedDeliverableId)) {
            throw ValidationException::withMessages([
                'deliverable_id' => 'The selected deliverable is not linked to this purchase order.',
            ]);
        }

        if (!$purchaseOrder->amount || $purchaseOrder->amount <= 0) {
            return back()->with('error', 'Purchase order amount is missing. Please update the purchase order first.');
        }

        $remaining = $purchaseOrder->remainingAmount();
        if ($remaining <= 0) {
            return back()->with('error', 'This purchase order is already fully paid.');
        }

        if ($data['amount'] > $remaining) {
            return back()->with('error', 'Disbursement amount exceeds remaining balance of ' . number_format($remaining, 2) . '.');
        }

        $this->storeLineItemEvidence($request, $purchaseOrder);

        $disbursement = ProcurementDisbursement::create([
            'purchase_order_id'  => $purchaseOrder->id,
            'deliverable_id'     => $selectedDeliverableId,
            'procurement_id'     => $purchaseOrder->procurement_id,
            'vendor_id'          => $purchaseOrder->vendor_id,
            'sub_activity_id'    => $purchaseOrder->sub_activity_id,
            'governance_node_id' => $purchaseOrder->governance_node_id,
            'consortium_id'      => $purchaseOrder->consortium_id,
            'think_tank_member_id' => $purchaseOrder->think_tank_member_id,
            'reference_no'       => ProcurementDisbursement::generateReference(),
            'amount'             => $data['amount'],
            'currency'           => $purchaseOrder->currency,
            'payment_method'     => $data['payment_method'],
            'transfer_reference' => $data['transfer_reference'] ?? null,
            'status'             => 'completed',
            'paid_at'            => $data['paid_at'],
            'created_by'         => auth()->id(),
            'notes'              => $data['notes'] ?? null,
        ]);

        $this->syncPurchaseOrderStatus($purchaseOrder);

        ProcurementAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'Created disbursement',
            'procurement_id' => $purchaseOrder->procurement_id,
            'metadata' => [
                'purchase_order_id' => $purchaseOrder->id,
                'disbursement_id' => $disbursement->id,
                'deliverable_id' => $disbursement->deliverable_id,
                'amount' => $disbursement->amount,
            ],
            'created_at' => now(),
        ]);

        $this->sendReceipt($disbursement);

        return redirect()
            ->route('procurement.disbursements.show', $disbursement)
            ->with('success', 'Disbursement recorded and receipt sent.');
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
            'deliverable.procurement',
            'vendor',
            'procurement',
            'subActivity',
            'governanceNode',
        ]);
        $canEditDisbursements = $this->canEditDisbursements();

        return view('procurement.disbursements.show', compact('disbursement', 'canEditDisbursements'));
    }

    public function edit(ProcurementDisbursement $disbursement)
    {
        $this->authorizeDisbursementEdit();
        $this->assertDisbursementInScope($disbursement);

        $disbursement->load([
            'purchaseOrder.procurement',
            'purchaseOrder.vendor',
            'purchaseOrder.disbursements',
            'purchaseOrder.deliverables.procurement',
            'purchaseOrder.purchaseRequest.items.deliverable.procurement',
            'purchaseOrder.budgetCommitment.purchaseRequest.items.deliverable.procurement',
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

        $deliverables = $this->deliverablesForDisbursement($disbursement);
        $paymentMethods = $this->paymentMethods();
        $statusOptions = $this->disbursementStatusOptions();
        $maxPayingAmount = $this->editableDisbursementMaxAmount($purchaseOrder, $disbursement, $disbursement->status ?? 'completed');
        $currentStatusCountsAsPaid = $this->statusCountsAgainstPurchaseOrder($disbursement->status ?? 'completed');
        $paidExcludingCurrent = max($purchaseOrder->paidAmount() - ($currentStatusCountsAsPaid ? (float) $disbursement->amount : 0), 0);

        return view('procurement.disbursements.edit', compact(
            'disbursement',
            'purchaseOrder',
            'deliverables',
            'paymentMethods',
            'statusOptions',
            'maxPayingAmount',
            'paidExcludingCurrent'
        ));
    }

    public function update(Request $request, ProcurementDisbursement $disbursement)
    {
        $this->authorizeDisbursementEdit();
        $this->assertDisbursementInScope($disbursement);

        $disbursement->load([
            'purchaseOrder.disbursements',
            'purchaseOrder.deliverables.procurement',
            'purchaseOrder.purchaseRequest.items.deliverable.procurement',
            'purchaseOrder.budgetCommitment.purchaseRequest.items.deliverable.procurement',
            'deliverable.procurement',
        ]);

        $purchaseOrder = $disbursement->purchaseOrder;
        if (! $purchaseOrder) {
            abort(404, 'The purchase order for this disbursement could not be found.');
        }

        $this->assertPurchaseOrderInScope($purchaseOrder);

        $statusOptions = array_keys($this->disbursementStatusOptions());

        $data = $request->validate([
            'deliverable_id'     => 'nullable|exists:procurement_deliverables,id',
            'amount'             => 'required|numeric|min:0.01',
            'payment_method'     => 'required|string|max:100',
            'transfer_reference' => 'nullable|string|max:255',
            'status'             => 'required|string|in:' . implode(',', $statusOptions),
            'paid_at'            => 'required|date',
            'notes'              => 'nullable|string|max:2000',
        ]);

        $eligibleDeliverables = $this->deliverablesForDisbursement($disbursement);
        $eligibleDeliverableIds = $eligibleDeliverables
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values();

        if ($eligibleDeliverables->isNotEmpty() && empty($data['deliverable_id'])) {
            throw ValidationException::withMessages([
                'deliverable_id' => 'Select the deliverable this disbursement is paying for.',
            ]);
        }

        if (!empty($data['deliverable_id']) && !$eligibleDeliverableIds->contains((string) $data['deliverable_id'])) {
            throw ValidationException::withMessages([
                'deliverable_id' => 'The selected deliverable is not linked to this purchase order.',
            ]);
        }

        $maxAmount = $this->editableDisbursementMaxAmount($purchaseOrder, $disbursement, $data['status']);
        if ((float) $data['amount'] > $maxAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Disbursement amount exceeds the editable balance of ' . number_format($maxAmount, 2) . ' ' . ($purchaseOrder->currency ?? '') . '.',
            ]);
        }

        $before = $disbursement->only([
            'deliverable_id',
            'amount',
            'payment_method',
            'transfer_reference',
            'status',
            'paid_at',
            'notes',
        ]);

        $disbursement->update([
            'deliverable_id'     => $data['deliverable_id'] ?? null,
            'amount'             => $data['amount'],
            'payment_method'     => $data['payment_method'],
            'transfer_reference' => $data['transfer_reference'] ?? null,
            'status'             => $data['status'],
            'paid_at'            => $data['paid_at'],
            'notes'              => $data['notes'] ?? null,
        ]);

        $this->syncPurchaseOrderStatus($purchaseOrder);

        ProcurementAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'Updated disbursement',
            'procurement_id' => $purchaseOrder->procurement_id,
            'metadata' => [
                'purchase_order_id' => $purchaseOrder->id,
                'disbursement_id' => $disbursement->id,
                'before' => $before,
                'after' => $disbursement->fresh()->only([
                    'deliverable_id',
                    'amount',
                    'payment_method',
                    'transfer_reference',
                    'status',
                    'paid_at',
                    'notes',
                ]),
            ],
            'created_at' => now(),
        ]);

        return redirect()
            ->route('procurement.disbursements.show', $disbursement)
            ->with('success', 'Disbursement updated.');
    }

    public function pdf(ProcurementDisbursement $disbursement)
    {
        $this->assertDisbursementInScope($disbursement);
        $disbursement->load(['purchaseOrder', 'deliverable.procurement', 'vendor', 'procurement', 'subActivity']);

        $pdf = Pdf::loadView('procurement.disbursements.pdf', [
            'disbursement' => $disbursement,
        ]);

        return $pdf->stream('receipt-' . ($disbursement->reference_no ?? 'payment') . '.pdf');
    }

    public function download(ProcurementDisbursement $disbursement)
    {
        $this->assertDisbursementInScope($disbursement);
        $disbursement->load(['purchaseOrder', 'deliverable.procurement', 'vendor', 'procurement', 'subActivity']);

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
            'currency' => $purchaseOrder->currency,
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

    private function deliverablesForDisbursement(ProcurementDisbursement $disbursement)
    {
        $purchaseOrder = $disbursement->purchaseOrder;
        $deliverables = $purchaseOrder
            ? $this->eligibleDeliverablesForPurchaseOrder($purchaseOrder)
            : collect();

        if ($disbursement->deliverable && ! $deliverables->contains('id', $disbursement->deliverable->id)) {
            $deliverables->push($disbursement->deliverable);
        }

        return $deliverables->filter()->unique('id')->values();
    }

    private function inferDisbursementDeliverableId(Request $request, ProcurementPurchaseOrder $purchaseOrder, $eligibleDeliverableIds): ?string
    {
        $sourcePurchaseRequest = $purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest;
        $items = $sourcePurchaseRequest?->items?->keyBy(fn ($item) => (string) $item->id) ?? collect();
        $evidenceInput = $request->input('item_evidence', []);

        foreach ($evidenceInput as $itemId => $input) {
            $hasLineEvidence = (bool) ($input['is_met'] ?? false)
                || filled($input['deliverable_date'] ?? null)
                || filled($input['notes'] ?? null)
                || ! empty($request->file("item_evidence.{$itemId}.documents", []));

            if (! $hasLineEvidence) {
                continue;
            }

            $deliverableId = $items->get((string) $itemId)?->deliverable_id;
            if ($deliverableId && $eligibleDeliverableIds->contains((string) $deliverableId)) {
                return (string) $deliverableId;
            }
        }

        if ($eligibleDeliverableIds->count() === 1) {
            return (string) $eligibleDeliverableIds->first();
        }

        return null;
    }

    private function editableDisbursementMaxAmount(
        ProcurementPurchaseOrder $purchaseOrder,
        ProcurementDisbursement $disbursement,
        ?string $newStatus
    ): float {
        $purchaseOrder->loadMissing('disbursements');

        if (! $this->statusCountsAgainstPurchaseOrder($newStatus)) {
            return round((float) ($purchaseOrder->amount ?? 0), 2);
        }

        $currentCountsAsPaid = $this->statusCountsAgainstPurchaseOrder($disbursement->status ?? 'completed');
        $editableBalance = $purchaseOrder->remainingAmount()
            + ($currentCountsAsPaid ? (float) $disbursement->amount : 0);

        return round(max($editableBalance, 0), 2);
    }

    private function statusCountsAgainstPurchaseOrder(?string $status): bool
    {
        $status = strtolower((string) ($status ?: 'completed'));

        return ! in_array($status, ProcurementPurchaseOrder::NON_PAYING_DISBURSEMENT_STATUSES, true);
    }

    private function canEditDisbursements(): bool
    {
        $user = auth()->user();

        return (bool) ($user && ($user->isAdmin() || $user->isSuperAdmin()));
    }

    private function authorizeDisbursementEdit(): void
    {
        if (! $this->canEditDisbursements()) {
            abort(403, 'Only administrators can edit disbursements.');
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

            if (! $isMet && $deliverableDate === '' && $notes === '' && empty($documents)) {
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

    private function sendReceipt(ProcurementDisbursement $disbursement): void
    {
        $vendor = $disbursement->vendor;
        if (!$vendor || empty($vendor->email)) {
            return;
        }

        $disbursement->loadMissing(['purchaseOrder', 'deliverable.procurement', 'procurement', 'subActivity']);

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
