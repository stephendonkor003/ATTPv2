<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Models\Activity;
use App\Models\BudgetCommitment;
use App\Models\Procurement;
use App\Models\ProcurementDeliverable;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ProcurementPurchaseOrderItemEvidence;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Resource;
use App\Models\ResourceCategory;
use App\Models\SubActivity;
use App\Models\User;
use App\Services\VendorPurchaseOrderEvidenceResubmissionNotificationService;
use App\Services\VendorPurchaseOrderNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProcurementPurchaseOrderController extends Controller
{
    use GovernanceScope;

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner', 'permission:finance.purchase_requests.view'])
            ->except('publicLineItemEvidenceDocumentPreview');
    }

    public function index()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to purchase orders.');
        }

        $purchaseOrderQuery = ProcurementPurchaseOrder::query()
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            });

        $summaryPurchaseOrders = (clone $purchaseOrderQuery)
            ->with([
                'lineItemEvidence',
                'disbursements',
                'purchaseRequest.programFunding.program',
                'purchaseRequest.items',
                'budgetCommitment.programFunding.program',
                'budgetCommitment.purchaseRequest.programFunding.program',
                'budgetCommitment.purchaseRequest.items',
            ])
            ->get();

        $purchaseOrderValueTotals = [
            'approved_amount' => 0.0,
            'item_total_amount' => 0.0,
            'item_paid_amount' => 0.0,
            'item_pending_amount' => 0.0,
            'total_items' => 0,
            'paid_items' => 0,
            'pending_items' => 0,
        ];

        foreach ($summaryPurchaseOrders as $summaryPurchaseOrder) {
            $lineItemSummary = $summaryPurchaseOrder->lineItemSummary();
            $purchaseOrderValueTotals['approved_amount'] += $lineItemSummary['total_amount'];

            if ($lineItemSummary['has_line_items']) {
                $purchaseOrderValueTotals['item_total_amount'] += $lineItemSummary['total_amount'];
                $purchaseOrderValueTotals['item_paid_amount'] += $lineItemSummary['paid_amount'];
                $purchaseOrderValueTotals['item_pending_amount'] += $lineItemSummary['pending_amount'];
                $purchaseOrderValueTotals['total_items'] += $lineItemSummary['total_items'];
                $purchaseOrderValueTotals['paid_items'] += $lineItemSummary['paid_items'];
                $purchaseOrderValueTotals['pending_items'] += $lineItemSummary['pending_items'];
            }
        }

        $purchaseOrderValueTotals['approved_amount'] = round($purchaseOrderValueTotals['approved_amount'], 2);
        $purchaseOrderValueTotals['item_total_amount'] = round($purchaseOrderValueTotals['item_total_amount'], 2);
        $purchaseOrderValueTotals['item_paid_amount'] = round($purchaseOrderValueTotals['item_paid_amount'], 2);
        $purchaseOrderValueTotals['item_pending_amount'] = round($purchaseOrderValueTotals['item_pending_amount'], 2);
        $purchaseOrderValueTotals['currency'] = $this->summaryCurrencyFor($summaryPurchaseOrders);

        $approvedPurchaseOrderCommitmentTotal = $purchaseOrderValueTotals['approved_amount'];
        $totalDisbursedAmount = (float) ProcurementDisbursement::query()
            ->whereIn('purchase_order_id', (clone $purchaseOrderQuery)->select('procurement_purchase_orders.id'))
            ->whereNotNull('paid_at')
            ->whereIn('status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES)
            ->sum('amount');

        $purchaseOrders = $purchaseOrderQuery
            ->with([
                'procurement',
                'vendor',
                'subActivity',
                'invoice',
                'lineItemEvidence',
                'budgetCommitment.programFunding.program',
                'budgetCommitment.purchaseRequest.programFunding.program',
                'budgetCommitment.purchaseRequest.items',
                'purchaseRequest.programFunding.program',
                'purchaseRequest.items',
                'disbursements',
            ])
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('procurement.purchase-orders.index', compact(
            'purchaseOrders',
            'approvedPurchaseOrderCommitmentTotal',
            'totalDisbursedAmount',
            'purchaseOrderValueTotals'
        ));
    }

    public function create()
    {
        return view('procurement.purchase-orders.create', $this->purchaseOrderFormContext());
    }

    public function edit(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $purchaseOrder->load([
            'deliverables',
            'lineItemEvidence',
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
            'purchaseRequest.items.deliverable',
            'purchaseRequest.commitments' => fn ($query) => $query->where('status', BudgetCommitment::STATUS_APPROVED)
                ->orderBy('commitment_year'),
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'budgetCommitment.purchaseRequest.items.deliverable',
            'budgetCommitment.purchaseRequest.commitments' => fn ($query) => $query->where('status', BudgetCommitment::STATUS_APPROVED)
                ->orderBy('commitment_year'),
        ]);

        return view('procurement.purchase-orders.create', $this->purchaseOrderFormContext($purchaseOrder));
    }

    private function purchaseOrderFormContext(?ProcurementPurchaseOrder $purchaseOrder = null): array
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to purchase orders.');
        }

        if ($purchaseOrder) {
            $sourcePurchaseRequest = $purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest;
            $purchaseRequests = collect([$sourcePurchaseRequest])
                ->filter()
                ->map(fn (PurchaseRequest $purchaseRequest) => $this->purchaseRequestCreateOption($purchaseRequest, $purchaseOrder))
                ->filter()
                ->values();
        } else {
            $purchaseRequests = PurchaseRequest::with([
                'programFunding.program',
                'governanceNode',
                'subActivity',
                'items.resourceCategory',
                'items.resource',
                'items.deliverable',
                'commitments' => fn ($query) => $query->where('status', BudgetCommitment::STATUS_APPROVED)
                    ->orderBy('commitment_year'),
            ])
                ->where('status', 'approved')
                ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                    $query->whereIn('governance_node_id', $scopedNodeIds)
                        ->whereNotNull('governance_node_id');
                })
                ->whereHas('commitments', function ($query) {
                    $query->where('status', BudgetCommitment::STATUS_APPROVED)
                        ->whereNotNull('commitment_amount');
                })
                ->orderByDesc('approved_at')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (PurchaseRequest $purchaseRequest) => $this->purchaseRequestCreateOption($purchaseRequest))
                ->filter()
                ->values();
        }

        $procurements = Procurement::query()
            ->with('awardedVendor')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('title')
            ->get();

        $vendors = User::where('user_type', 'vendor')
            ->orderBy('name')
            ->get();

        $procurementOptions = $procurements
            ->map(fn (Procurement $procurement) => [
                'id' => (string) $procurement->id,
                'title' => $procurement->title,
                'reference_no' => $procurement->reference_no,
                'awarded_vendor_id' => $procurement->awarded_vendor_id,
                'awarded_vendor_name' => $procurement->awardedVendor?->name,
            ])
            ->values();

        $vendorOptions = $vendors
            ->map(fn (User $vendor) => [
                'id' => (string) $vendor->id,
                'name' => $vendor->name,
                'email' => $vendor->email,
                'phone' => $vendor->payment_mobile_number,
                'address' => $vendor->payment_address,
                'tax_id' => $vendor->payment_tax_id,
            ])
            ->values();

        $resourceCategories = ResourceCategory::where('status', 'active')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $resourcesByCategory = Resource::where('status', 'active')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('name')
            ->get(['id', 'resource_category_id', 'name'])
            ->groupBy(fn (Resource $resource) => (string) $resource->resource_category_id)
            ->map(fn ($resources) => $resources->map(fn (Resource $resource) => [
                'id' => (string) $resource->id,
                'name' => $resource->name,
            ])->values())
            ->toArray();

        $buyerDefaults = [
            'name' => auth()->user()?->name,
            'email' => auth()->user()?->email,
        ];

        $itemEvidenceDefaults = [];
        if ($purchaseOrder) {
            $itemEvidenceDefaults = $purchaseOrder->lineItemEvidence
                ->mapWithKeys(fn (ProcurementPurchaseOrderItemEvidence $evidence) => [
                    (string) $evidence->purchase_request_item_id => [
                        'is_met' => $evidence->is_met ? '1' : '0',
                        'deliverable_date' => $evidence->deliverable_date?->format('Y-m-d'),
                        'delivered_unit_price' => $evidence->delivered_unit_price !== null ? number_format((float) $evidence->delivered_unit_price, 2, '.', '') : null,
                        'delivered_quantity' => $evidence->delivered_quantity !== null ? number_format((float) $evidence->delivered_quantity, 2, '.', '') : null,
                        'delivered_amount' => $evidence->delivered_amount !== null ? number_format((float) $evidence->delivered_amount, 2, '.', '') : null,
                        'notes' => $evidence->notes,
                        'existing_documents' => collect($evidence->documents ?? [])
                            ->map(fn ($document) => [
                                'name' => $document['name'] ?? 'Document',
                                'display_name' => $document['display_name'] ?? null,
                            ])
                            ->values()
                            ->all(),
                    ],
                ])
                ->all();
        }

        return compact(
            'purchaseRequests',
            'procurements',
            'procurementOptions',
            'vendors',
            'vendorOptions',
            'resourceCategories',
            'resourcesByCategory',
            'buyerDefaults',
            'purchaseOrder',
            'itemEvidenceDefaults'
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_request_id' => ['required', 'exists:myb_purchase_requests,id'],
            'budget_commitment_id' => ['required', 'exists:myb_budget_commitments,id'],
            'procurement_id' => ['nullable', 'exists:procurements,id'],
            'deliverable_ids'   => ['nullable', 'array'],
            'deliverable_ids.*' => ['exists:procurement_deliverables,id'],
            'line_item_resource_categories' => ['nullable', 'array'],
            'line_item_resource_categories.*' => ['nullable', 'exists:myb_resource_categories,id'],
            'line_item_resources' => ['nullable', 'array'],
            'line_item_resources.*' => ['nullable', 'exists:myb_resources,id'],
            'line_item_deliverables' => ['nullable', 'array'],
            'line_item_deliverables.*' => ['nullable', 'string', 'max:255'],
            'line_item_dates' => ['nullable', 'array'],
            'line_item_dates.*' => ['nullable', 'date'],
            'line_item_unit_prices' => ['nullable', 'array'],
            'line_item_unit_prices.*' => ['nullable', 'numeric', 'min:0.01'],
            'line_item_quantities' => ['nullable', 'array'],
            'line_item_quantities.*' => ['nullable', 'numeric', 'min:0.01'],
            'line_item_amounts' => ['nullable', 'array'],
            'line_item_amounts.*' => ['nullable', 'numeric', 'min:0.01'],
            'vendor_id' => ['nullable', 'exists:users,id'],
            'po_title' => ['nullable', 'string', 'max:255'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'contract_reference' => ['nullable', 'string', 'max:255'],
            'buyer_contact_name' => ['nullable', 'string', 'max:255'],
            'buyer_contact_email' => ['nullable', 'email', 'max:255'],
            'buyer_contact_phone' => ['nullable', 'string', 'max:100'],
            'vendor_contact_name' => ['nullable', 'string', 'max:255'],
            'vendor_contact_email' => ['nullable', 'email', 'max:255'],
            'vendor_contact_phone' => ['nullable', 'string', 'max:100'],
            'billing_address' => ['required', 'string', 'max:2000'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'delivery_location' => ['nullable', 'string', 'max:2000'],
            'incoterm' => ['nullable', 'string', 'max:30'],
            'delivery_terms' => ['required', 'string', 'max:255'],
            'payment_terms' => ['required', 'string', 'max:255'],
            'warranty_terms' => ['nullable', 'string', 'max:2000'],
            'inspection_requirements' => ['nullable', 'string', 'max:2000'],
            'special_instructions' => ['nullable', 'string', 'max:2000'],
            'terms_conditions' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['required', 'in:draft,issued,closed,cancelled'],
            'issued_at' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'supporting_document' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'max:20480'],
            'item_evidence' => ['nullable', 'array'],
            'item_evidence.*.is_met' => ['nullable', 'boolean'],
            'item_evidence.*.deliverable_date' => ['nullable', 'date'],
            'item_evidence.*.delivered_unit_price' => ['nullable', 'numeric', 'min:0.01'],
            'item_evidence.*.delivered_quantity' => ['nullable', 'numeric', 'min:0'],
            'item_evidence.*.delivered_amount' => ['nullable', 'numeric', 'min:0'],
            'item_evidence.*.notes' => ['nullable', 'string', 'max:3000'],
            'item_evidence.*.document_names' => ['nullable', 'array', 'max:20'],
            'item_evidence.*.document_names.*' => ['nullable', 'string', 'max:255'],
            'item_evidence.*.documents' => ['nullable', 'array', 'max:20'],
            'item_evidence.*.documents.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'max:20480'],
        ], [
            'purchase_request_id.required' => 'Select the approved purchase request before creating the purchase order.',
            'budget_commitment_id.required' => 'Select the approved commitment year that will fund this purchase order.',
            'billing_address.required' => 'Enter the bill-to address for this purchase order.',
            'shipping_address.required' => 'Enter the ship-to address for this purchase order.',
            'delivery_terms.required' => 'Enter the delivery terms for this purchase order.',
            'payment_terms.required' => 'Enter the payment terms for this purchase order.',
            'supporting_document.required' => 'Attach the supporting documentation before creating this purchase order.',
            'supporting_document.mimes' => 'Supporting documentation must be a PDF, Office document, image, or ZIP file.',
            'item_evidence.*.documents.*.mimes' => 'Line item evidence must be a PDF, Office document, image, or ZIP file.',
        ]);

        $purchaseRequest = PurchaseRequest::with(['programFunding.program', 'commitments', 'items.deliverable'])->findOrFail($data['purchase_request_id']);
        $this->assertPurchaseRequestInScope($purchaseRequest);

        if ($purchaseRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'purchase_request_id' => 'Only approved purchase requests can be used to create purchase orders.',
            ]);
        }

        $commitment = BudgetCommitment::with(['programFunding.program', 'purchaseRequest.programFunding.program'])->findOrFail($data['budget_commitment_id']);
        $this->assertCommitmentInScope($commitment);

        if ((string) $commitment->purchase_request_id !== (string) $purchaseRequest->id) {
            throw ValidationException::withMessages([
                'budget_commitment_id' => 'The selected commitment does not belong to the selected purchase request.',
            ]);
        }

        if ($commitment->status !== BudgetCommitment::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'budget_commitment_id' => 'Purchase orders can only be tied to approved commitments.',
            ]);
        }

        $data['amount'] = $this->lineItemTotalFromRequest($request, $purchaseRequest);

        $remaining = $this->remainingCommitmentAmount($commitment);
        if ((float) $data['amount'] > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'The purchase order amount cannot exceed the remaining commitment balance of ' . number_format($remaining, 2) . '.',
            ]);
        }

        $submittedDeliverableIds = collect($data['deliverable_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();
        $itemDeliverableIds = $purchaseRequest->items
            ->pluck('deliverable_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();
        $deliverableIds = $submittedDeliverableIds
            ->merge($itemDeliverableIds)
            ->unique()
            ->values();

        $procurement = null;
        if (!empty($data['procurement_id'])) {
            $procurement = Procurement::findOrFail($data['procurement_id']);
            $this->assertProcurementInScope($procurement);

            if ($deliverableIds->isNotEmpty()) {
                $invalid = ProcurementDeliverable::whereIn('id', $deliverableIds)
                    ->where('procurement_id', '!=', $procurement->id)
                    ->exists();

                if ($invalid) {
                    throw ValidationException::withMessages([
                        'deliverable_ids' => 'One or more selected deliverables do not belong to the chosen procurement.',
                    ]);
                }
            }
        } elseif ($submittedDeliverableIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'procurement_id' => 'Select the procurement before choosing additional deliverables.',
            ]);
        }

        $vendor = null;
        if (!empty($data['vendor_id'])) {
            $vendor = User::query()
                ->where('user_type', 'vendor')
                ->findOrFail($data['vendor_id']);
        } elseif ($procurement?->awarded_vendor_id) {
            $vendor = User::query()->find($procurement->awarded_vendor_id);
        }

        $purchaseOrder = DB::transaction(function () use ($commitment, $data, $deliverableIds, $procurement, $purchaseRequest, $request, $vendor) {
            $this->syncPurchaseRequestLineItems($request, $purchaseRequest);

            $purchaseOrder = ProcurementPurchaseOrder::create([
                'budget_commitment_id' => $commitment->id,
                'purchase_request_id' => $purchaseRequest->id,
                'procurement_id' => $procurement?->id,
                'vendor_id' => $vendor?->id,
                'sub_activity_id' => $commitment->allocation_level === 'sub_activity' ? $commitment->allocation_id : null,
                'governance_node_id' => $commitment->governance_node_id,
                'reference_no' => ProcurementPurchaseOrder::generateReference(),
                'po_title' => $data['po_title'] ?: 'Purchase Order for ' . $purchaseRequest->reference_no,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'contract_reference' => $data['contract_reference'] ?? null,
                'buyer_contact_name' => $data['buyer_contact_name'] ?? auth()->user()?->name,
                'buyer_contact_email' => $data['buyer_contact_email'] ?? auth()->user()?->email,
                'buyer_contact_phone' => $data['buyer_contact_phone'] ?? null,
                'vendor_contact_name' => $data['vendor_contact_name'] ?? $vendor?->name,
                'vendor_contact_email' => $data['vendor_contact_email'] ?? $vendor?->email,
                'vendor_contact_phone' => $data['vendor_contact_phone'] ?? $vendor?->payment_mobile_number,
                'billing_address' => $data['billing_address'],
                'shipping_address' => $data['shipping_address'],
                'delivery_location' => $data['delivery_location'] ?? null,
                'incoterm' => $data['incoterm'] ?? null,
                'delivery_terms' => $data['delivery_terms'],
                'payment_terms' => $data['payment_terms'],
                'warranty_terms' => $data['warranty_terms'] ?? null,
                'inspection_requirements' => $data['inspection_requirements'] ?? null,
                'special_instructions' => $data['special_instructions'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
                'amount' => $data['amount'],
                'currency' => $this->commitmentCurrency($commitment),
                'status' => $data['status'],
                'created_by' => auth()->id(),
                'issued_at' => $data['issued_at'] ?? now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $purchaseRequest->delivery_date?->toDateString(),
                'valid_until' => $data['valid_until'] ?? null,
            ]);

            $this->attachSupportingDocument($request, $purchaseOrder);

            if ($deliverableIds->isNotEmpty()) {
                $purchaseOrder->deliverables()->sync($deliverableIds->all());
            }

            $this->storeLineItemEvidence($request, $purchaseOrder, $purchaseRequest);

            return $purchaseOrder;
        });

        app(VendorPurchaseOrderNotificationService::class)->notifyCreated($purchaseOrder->fresh());

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order created from approved purchase request ' . $purchaseRequest->reference_no . '.');
    }

    public function update(Request $request, ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $data = $request->validate([
            'purchase_request_id' => ['required', 'exists:myb_purchase_requests,id'],
            'budget_commitment_id' => ['required', 'exists:myb_budget_commitments,id'],
            'procurement_id' => ['nullable', 'exists:procurements,id'],
            'deliverable_ids'   => ['nullable', 'array'],
            'deliverable_ids.*' => ['exists:procurement_deliverables,id'],
            'line_item_resource_categories' => ['nullable', 'array'],
            'line_item_resource_categories.*' => ['nullable', 'exists:myb_resource_categories,id'],
            'line_item_resources' => ['nullable', 'array'],
            'line_item_resources.*' => ['nullable', 'exists:myb_resources,id'],
            'line_item_deliverables' => ['nullable', 'array'],
            'line_item_deliverables.*' => ['nullable', 'string', 'max:255'],
            'line_item_dates' => ['nullable', 'array'],
            'line_item_dates.*' => ['nullable', 'date'],
            'line_item_unit_prices' => ['nullable', 'array'],
            'line_item_unit_prices.*' => ['nullable', 'numeric', 'min:0.01'],
            'line_item_quantities' => ['nullable', 'array'],
            'line_item_quantities.*' => ['nullable', 'numeric', 'min:0.01'],
            'line_item_amounts' => ['nullable', 'array'],
            'line_item_amounts.*' => ['nullable', 'numeric', 'min:0.01'],
            'vendor_id' => ['nullable', 'exists:users,id'],
            'po_title' => ['nullable', 'string', 'max:255'],
            'supplier_reference' => ['nullable', 'string', 'max:255'],
            'contract_reference' => ['nullable', 'string', 'max:255'],
            'buyer_contact_name' => ['nullable', 'string', 'max:255'],
            'buyer_contact_email' => ['nullable', 'email', 'max:255'],
            'buyer_contact_phone' => ['nullable', 'string', 'max:100'],
            'vendor_contact_name' => ['nullable', 'string', 'max:255'],
            'vendor_contact_email' => ['nullable', 'email', 'max:255'],
            'vendor_contact_phone' => ['nullable', 'string', 'max:100'],
            'billing_address' => ['required', 'string', 'max:2000'],
            'shipping_address' => ['required', 'string', 'max:2000'],
            'delivery_location' => ['nullable', 'string', 'max:2000'],
            'incoterm' => ['nullable', 'string', 'max:30'],
            'delivery_terms' => ['required', 'string', 'max:255'],
            'payment_terms' => ['required', 'string', 'max:255'],
            'warranty_terms' => ['nullable', 'string', 'max:2000'],
            'inspection_requirements' => ['nullable', 'string', 'max:2000'],
            'special_instructions' => ['nullable', 'string', 'max:2000'],
            'terms_conditions' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['required', 'in:draft,issued,closed,cancelled'],
            'issued_at' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'max:20480'],
            'item_evidence' => ['nullable', 'array'],
            'item_evidence.*.is_met' => ['nullable', 'boolean'],
            'item_evidence.*.deliverable_date' => ['nullable', 'date'],
            'item_evidence.*.delivered_unit_price' => ['nullable', 'numeric', 'min:0.01'],
            'item_evidence.*.delivered_quantity' => ['nullable', 'numeric', 'min:0'],
            'item_evidence.*.delivered_amount' => ['nullable', 'numeric', 'min:0'],
            'item_evidence.*.notes' => ['nullable', 'string', 'max:3000'],
            'item_evidence.*.document_names' => ['nullable', 'array', 'max:20'],
            'item_evidence.*.document_names.*' => ['nullable', 'string', 'max:255'],
            'item_evidence.*.documents' => ['nullable', 'array', 'max:20'],
            'item_evidence.*.documents.*' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip', 'max:20480'],
        ], [
            'purchase_request_id.required' => 'Select the approved purchase request before saving the purchase order.',
            'budget_commitment_id.required' => 'Select the approved commitment year that funds this purchase order.',
            'billing_address.required' => 'Enter the bill-to address for this purchase order.',
            'shipping_address.required' => 'Enter the ship-to address for this purchase order.',
            'delivery_terms.required' => 'Enter the delivery terms for this purchase order.',
            'payment_terms.required' => 'Enter the payment terms for this purchase order.',
            'supporting_document.mimes' => 'Supporting documentation must be a PDF, Office document, image, or ZIP file.',
            'item_evidence.*.documents.*.mimes' => 'Line item evidence must be a PDF, Office document, image, or ZIP file.',
        ]);

        $purchaseRequest = PurchaseRequest::with(['programFunding.program', 'commitments', 'items.deliverable'])->findOrFail($data['purchase_request_id']);
        $this->assertPurchaseRequestInScope($purchaseRequest);

        if ($purchaseRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'purchase_request_id' => 'Only approved purchase requests can be used on purchase orders.',
            ]);
        }

        $commitment = BudgetCommitment::with(['programFunding.program', 'purchaseRequest.programFunding.program'])->findOrFail($data['budget_commitment_id']);
        $this->assertCommitmentInScope($commitment);

        if ((string) $commitment->purchase_request_id !== (string) $purchaseRequest->id) {
            throw ValidationException::withMessages([
                'budget_commitment_id' => 'The selected commitment does not belong to the selected purchase request.',
            ]);
        }

        if ($commitment->status !== BudgetCommitment::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'budget_commitment_id' => 'Purchase orders can only be tied to approved commitments.',
            ]);
        }

        $data['amount'] = $this->lineItemTotalFromRequest($request, $purchaseRequest);

        $remaining = $this->remainingCommitmentAmount($commitment, $purchaseOrder);
        if ((float) $data['amount'] > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'The purchase order amount cannot exceed the remaining commitment balance of ' . number_format($remaining, 2) . '.',
            ]);
        }

        $submittedDeliverableIds = collect($data['deliverable_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();
        $itemDeliverableIds = $purchaseRequest->items
            ->pluck('deliverable_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();
        $deliverableIds = $submittedDeliverableIds
            ->merge($itemDeliverableIds)
            ->unique()
            ->values();

        $procurement = null;
        if (!empty($data['procurement_id'])) {
            $procurement = Procurement::findOrFail($data['procurement_id']);
            $this->assertProcurementInScope($procurement);

            if ($deliverableIds->isNotEmpty()) {
                $invalid = ProcurementDeliverable::whereIn('id', $deliverableIds)
                    ->where('procurement_id', '!=', $procurement->id)
                    ->exists();

                if ($invalid) {
                    throw ValidationException::withMessages([
                        'deliverable_ids' => 'One or more selected deliverables do not belong to the chosen procurement.',
                    ]);
                }
            }
        } elseif ($submittedDeliverableIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'procurement_id' => 'Select the procurement before choosing additional deliverables.',
            ]);
        }

        $vendor = null;
        if (!empty($data['vendor_id'])) {
            $vendor = User::query()
                ->where('user_type', 'vendor')
                ->findOrFail($data['vendor_id']);
        } elseif ($procurement?->awarded_vendor_id) {
            $vendor = User::query()->find($procurement->awarded_vendor_id);
        }

        $originalVendorId = $purchaseOrder->vendor_id;
        $originalStatus = strtolower((string) $purchaseOrder->status);

        DB::transaction(function () use ($commitment, $data, $deliverableIds, $procurement, $purchaseOrder, $purchaseRequest, $request, $vendor) {
            $this->syncPurchaseRequestLineItems($request, $purchaseRequest);

            $purchaseOrder->update([
                'budget_commitment_id' => $commitment->id,
                'purchase_request_id' => $purchaseRequest->id,
                'procurement_id' => $procurement?->id,
                'vendor_id' => $vendor?->id,
                'sub_activity_id' => $commitment->allocation_level === 'sub_activity' ? $commitment->allocation_id : null,
                'governance_node_id' => $commitment->governance_node_id,
                'po_title' => $data['po_title'] ?: 'Purchase Order for ' . $purchaseRequest->reference_no,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'contract_reference' => $data['contract_reference'] ?? null,
                'buyer_contact_name' => $data['buyer_contact_name'] ?? auth()->user()?->name,
                'buyer_contact_email' => $data['buyer_contact_email'] ?? auth()->user()?->email,
                'buyer_contact_phone' => $data['buyer_contact_phone'] ?? null,
                'vendor_contact_name' => $data['vendor_contact_name'] ?? $vendor?->name,
                'vendor_contact_email' => $data['vendor_contact_email'] ?? $vendor?->email,
                'vendor_contact_phone' => $data['vendor_contact_phone'] ?? $vendor?->payment_mobile_number,
                'billing_address' => $data['billing_address'],
                'shipping_address' => $data['shipping_address'],
                'delivery_location' => $data['delivery_location'] ?? null,
                'incoterm' => $data['incoterm'] ?? null,
                'delivery_terms' => $data['delivery_terms'],
                'payment_terms' => $data['payment_terms'],
                'warranty_terms' => $data['warranty_terms'] ?? null,
                'inspection_requirements' => $data['inspection_requirements'] ?? null,
                'special_instructions' => $data['special_instructions'] ?? null,
                'terms_conditions' => $data['terms_conditions'] ?? null,
                'amount' => $data['amount'],
                'currency' => $this->commitmentCurrency($commitment),
                'status' => $data['status'],
                'issued_at' => $data['issued_at'] ?? $purchaseOrder->issued_at ?? now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $purchaseRequest->delivery_date?->toDateString(),
                'valid_until' => $data['valid_until'] ?? null,
            ]);

            if ($request->hasFile('supporting_document')) {
                $this->deleteSupportingDocument($purchaseOrder);
                $this->attachSupportingDocument($request, $purchaseOrder);
            }

            $purchaseOrder->deliverables()->sync($deliverableIds->all());
            $this->storeLineItemEvidence($request, $purchaseOrder, $purchaseRequest, true);
        });

        $purchaseOrder->refresh();
        $vendorChanged = (string) $originalVendorId !== (string) $purchaseOrder->vendor_id;
        $becameIssued = $originalStatus !== 'issued' && strtolower((string) $purchaseOrder->status) === 'issued';

        if ($purchaseOrder->vendor_id && ($vendorChanged || $becameIssued)) {
            app(VendorPurchaseOrderNotificationService::class)->notifyCreated($purchaseOrder);
        }

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order updated successfully.');
    }

    public function show(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $purchaseOrder->load([
            'procurement',
            'deliverables' => fn($q) => $q->withTrashed()->with('deletedBy'),
            'vendor',
            'subActivity',
            'negotiation',
            'invoice',
            'disbursements.deliverable',
            'lineItemEvidence.deliverable',
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'budgetCommitment.purchaseRequest.items.deliverable',
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
            'purchaseRequest.items.deliverable',
            'purchaseRequest.programFunding.program',
            'purchaseRequest.governanceNode',
        ]);

        return view('procurement.purchase-orders.show', compact('purchaseOrder'));
    }

    public function pdf(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $purchaseOrder->load([
            'procurement',
            'deliverables',
            'vendor',
            'subActivity',
            'negotiation',
            'invoice',
            'lineItemEvidence',
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'budgetCommitment.purchaseRequest.items.deliverable',
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
            'purchaseRequest.items.deliverable',
            'purchaseRequest.programFunding.program',
            'purchaseRequest.governanceNode',
        ]);

        $pdf = Pdf::loadView('procurement.purchase-orders.pdf', [
            'purchaseOrder' => $purchaseOrder,
        ]);

        return $pdf->stream('purchase-order-' . ($purchaseOrder->reference_no ?? 'draft') . '.pdf');
    }

    public function download(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $purchaseOrder->load([
            'procurement',
            'deliverables',
            'vendor',
            'subActivity',
            'negotiation',
            'invoice',
            'lineItemEvidence',
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'budgetCommitment.purchaseRequest.items.deliverable',
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
            'purchaseRequest.items.deliverable',
            'purchaseRequest.programFunding.program',
            'purchaseRequest.governanceNode',
        ]);

        $pdf = Pdf::loadView('procurement.purchase-orders.pdf', [
            'purchaseOrder' => $purchaseOrder,
        ]);

        return $pdf->download('purchase-order-' . ($purchaseOrder->reference_no ?? 'draft') . '.pdf');
    }

    public function downloadSupportingDocument(Request $request, ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $path = (string) ($purchaseOrder->supporting_document_path ?? '');
        abort_if($path === '', 404, 'Supporting document not found.');

        $privateDisk = Storage::disk('local');

        if (! $privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            $stream = Storage::disk('public')->readStream($path);
            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                Storage::disk('public')->delete($path);
            }
        }

        abort_unless($privateDisk->exists($path), 404, 'Supporting document file missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $fileName = $purchaseOrder->supporting_document_name ?: basename($path);

        if ($request->boolean('download')) {
            return $privateDisk->download($path, $fileName, $headers);
        }

        return $privateDisk->response($path, $fileName, $headers);
    }

    public function downloadLineItemEvidenceDocument(
        Request $request,
        ProcurementPurchaseOrder $purchaseOrder,
        ProcurementPurchaseOrderItemEvidence $evidence,
        int $document
    ) {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        abort_unless((string) $evidence->purchase_order_id === (string) $purchaseOrder->id, 404);

        $documents = $evidence->documents ?? [];
        $file = $documents[$document] ?? null;
        abort_unless(is_array($file) && !empty($file['path']), 404, 'Evidence document not found.');

        $privateDisk = Storage::disk('local');
        abort_unless($privateDisk->exists($file['path']), 404, 'Evidence document file missing on disk.');

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

    public function publicLineItemEvidenceDocumentPreview(
        Request $request,
        ProcurementPurchaseOrder $purchaseOrder,
        ProcurementPurchaseOrderItemEvidence $evidence,
        int $document
    ) {
        abort_unless((string) $evidence->purchase_order_id === (string) $purchaseOrder->id, 404);

        $documents = $evidence->documents ?? [];
        $file = $documents[$document] ?? null;
        abort_unless(is_array($file) && ! empty($file['path']), 404, 'Evidence document not found.');

        $privateDisk = Storage::disk('local');
        abort_unless($privateDisk->exists($file['path']), 404, 'Evidence document file missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $fileName = $file['name'] ?? basename($file['path']);

        return $privateDisk->response($file['path'], $fileName, $headers);
    }

    public function requestLineItemEvidenceResubmission(
        Request $request,
        ProcurementPurchaseOrder $purchaseOrder,
        ProcurementPurchaseOrderItemEvidence $evidence
    ) {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        abort_unless((string) $evidence->purchase_order_id === (string) $purchaseOrder->id, 404);

        if (! $purchaseOrder->vendor_id) {
            return back()->withErrors(['resubmission' => 'This purchase order has no vendor assigned.']);
        }

        if (! $evidence->hasVendorDocuments()) {
            return back()->withErrors(['resubmission' => 'There is no vendor-submitted evidence to return for resubmission.']);
        }

        $data = $request->validate([
            'vendor_resubmission_note' => ['required', 'string', 'min:5', 'max:3000'],
        ], [
            'vendor_resubmission_note.required' => 'Enter the reason the vendor needs to resubmit this evidence.',
        ]);

        $evidence->update([
            'is_met' => false,
            'vendor_submission_status' => ProcurementPurchaseOrderItemEvidence::VENDOR_STATUS_REVISION_REQUESTED,
            'vendor_resubmission_requested_at' => now(),
            'vendor_resubmission_requested_by' => auth()->id(),
            'vendor_resubmission_note' => $data['vendor_resubmission_note'],
        ]);

        app(VendorPurchaseOrderEvidenceResubmissionNotificationService::class)->notify($purchaseOrder, $evidence->fresh());

        return back()->with('success', 'Evidence resubmission requested from the vendor.');
    }

    public function destroy(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->disbursements()->update(['purchase_order_id' => null]);
            $this->deleteLineItemEvidenceDocuments($purchaseOrder);
            $this->deleteSupportingDocument($purchaseOrder);
            $purchaseOrder->delete();
        });

        return redirect()
            ->route('procurement.purchase-orders.index')
            ->with('success', 'Purchase order deleted successfully.');
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

    private function assertCommitmentInScope(BudgetCommitment $commitment): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$commitment->governance_node_id || !in_array($commitment->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this commitment.');
        }
    }

    private function assertPurchaseRequestInScope(PurchaseRequest $purchaseRequest): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$purchaseRequest->governance_node_id || !in_array($purchaseRequest->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this purchase request.');
        }
    }

    private function assertProcurementInScope(Procurement $procurement): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$procurement->governance_node_id || !in_array($procurement->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this procurement.');
        }
    }

    private function assertResourceCategoryInScope(ResourceCategory $category): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$category->governance_node_id || !in_array($category->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this resource category.');
        }
    }

    private function remainingCommitmentAmount(BudgetCommitment $commitment, ?ProcurementPurchaseOrder $ignorePurchaseOrder = null): float
    {
        $committed = (float) ($commitment->commitment_amount ?? 0);
        $issued = (float) ProcurementPurchaseOrder::query()
            ->where('budget_commitment_id', $commitment->id)
            ->when($ignorePurchaseOrder, fn ($query) => $query->where($ignorePurchaseOrder->getKeyName(), '!=', $ignorePurchaseOrder->getKey()))
            ->whereNotIn('status', ['cancelled'])
            ->sum('amount');

        return max($committed - $issued, 0);
    }

    private function commitmentHierarchy(BudgetCommitment $commitment): array
    {
        $hierarchy = [
            'project' => 'Project not set',
            'activity' => null,
            'sub_activity' => null,
        ];

        if (!$commitment->allocation_id) {
            return $hierarchy;
        }

        if ($commitment->allocation_level === 'project') {
            $hierarchy['project'] = Project::query()
                ->whereKey($commitment->allocation_id)
                ->value('name') ?? 'Project not found';

            return $hierarchy;
        }

        if ($commitment->allocation_level === 'activity') {
            $activity = Activity::query()
                ->whereKey($commitment->allocation_id)
                ->first(['id', 'name', 'project_id']);

            if (!$activity) {
                $hierarchy['activity'] = 'Activity not found';
                return $hierarchy;
            }

            $hierarchy['activity'] = $activity->name;
            $hierarchy['project'] = Project::query()
                ->whereKey($activity->project_id)
                ->value('name') ?? 'Project not found';

            return $hierarchy;
        }

        if ($commitment->allocation_level === 'sub_activity') {
            $subActivity = SubActivity::query()
                ->whereKey($commitment->allocation_id)
                ->first(['id', 'name', 'activity_id']);

            if (!$subActivity) {
                $hierarchy['sub_activity'] = 'Sub-Activity not found';
                return $hierarchy;
            }

            $hierarchy['sub_activity'] = $subActivity->name;

            $activity = Activity::query()
                ->whereKey($subActivity->activity_id)
                ->first(['id', 'name', 'project_id']);

            if (!$activity) {
                $hierarchy['activity'] = 'Activity not found';
                return $hierarchy;
            }

            $hierarchy['activity'] = $activity->name;
            $hierarchy['project'] = Project::query()
                ->whereKey($activity->project_id)
                ->value('name') ?? 'Project not found';
        }

        return $hierarchy;
    }

    private function commitmentPurchaseRequestReference(BudgetCommitment $commitment): string
    {
        if (!$commitment->purchase_request_id) {
            return 'Commitment';
        }

        return PurchaseRequest::query()->whereKey($commitment->purchase_request_id)->value('reference_no') ?? 'Commitment';
    }

    private function commitmentCurrency(BudgetCommitment $commitment): string
    {
        $commitment->loadMissing([
            'programFunding.program',
            'purchaseRequest.programFunding.program',
        ]);

        return $commitment->resolved_currency;
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

    private function purchaseRequestCreateOption(PurchaseRequest $purchaseRequest, ?ProcurementPurchaseOrder $purchaseOrder = null): ?array
    {
        $commitments = $purchaseRequest->commitments
            ->filter(fn (BudgetCommitment $commitment) => $commitment->status === BudgetCommitment::STATUS_APPROVED)
            ->map(function (BudgetCommitment $commitment) use ($purchaseOrder) {
                $remaining = $this->remainingCommitmentAmount($commitment, $purchaseOrder);
                if ($remaining <= 0) {
                    return null;
                }

                $hierarchy = $this->commitmentHierarchy($commitment);

                return [
                    'id' => (string) $commitment->id,
                    'year' => $commitment->commitment_year,
                    'amount' => round((float) $commitment->commitment_amount, 2),
                    'remaining_amount' => round($remaining, 2),
                    'currency' => $this->commitmentCurrency($commitment),
                    'project' => $hierarchy['project'],
                    'activity' => $hierarchy['activity'],
                    'sub_activity' => $hierarchy['sub_activity'],
                    'description' => $commitment->description,
                ];
            })
            ->filter()
            ->values();

        if ($commitments->isEmpty()) {
            return null;
        }

        $firstCommitment = $commitments->first();
        $currency = $purchaseRequest->resolved_currency ?: ($firstCommitment['currency'] ?? 'USD');
        $program = $purchaseRequest->programFunding?->program?->name
            ?? $purchaseRequest->programFunding?->program_name
            ?? 'Program not set';
        $subActivity = $purchaseRequest->subActivity?->name ?: ($firstCommitment['sub_activity'] ?? 'Sub-activity not set');
        $project = $firstCommitment['project'] ?? 'Project not set';
        $activity = $firstCommitment['activity'] ?? null;
        $totalAmount = round((float) $purchaseRequest->total_amount, 2);
        $remainingAmount = round((float) $commitments->sum('remaining_amount'), 2);

        $items = $purchaseRequest->items
            ->map(fn ($item) => [
                'id' => (string) $item->id,
                'resource_category_id' => (string) $item->resource_category_id,
                'resource_id' => (string) $item->resource_id,
                'category' => $item->resourceCategory?->name ?: 'N/A',
                'resource' => $item->resource?->name ?: 'N/A',
                'description' => $item->observations ?: $item->object_type ?: '',
                'line_deliverable' => $item->milestone ?: $item->deliverable?->title ?: '',
                'milestone_date' => $item->milestone_date?->format('Y-m-d'),
                'unit_price' => round((float) ($item->unit_price ?: $item->amount), 2),
                'quantity' => round((float) ($item->quantity ?: 1), 2),
                'amount' => round((float) $item->amount, 2),
                'budget_code' => $item->budget_code,
                'deliverable_id' => $item->deliverable_id,
                'deliverable_title' => $item->deliverable?->title,
                'units' => $item->work_plan_units,
                'payment_basis' => $item->work_plan_payment_basis,
            ])
            ->values();

        $searchText = collect([
            $purchaseRequest->reference_no,
            $program,
            $purchaseRequest->governanceNode?->name,
            $project,
            $activity,
            $subActivity,
            $purchaseRequest->start_year,
            $currency,
        ])->filter()->implode(' ');

        return [
            'id' => (string) $purchaseRequest->id,
            'reference_no' => $purchaseRequest->reference_no,
            'program' => $program,
            'governance_node' => $purchaseRequest->governanceNode?->name ?: 'N/A',
            'project' => $project,
            'activity' => $activity ?: 'N/A',
            'sub_activity' => $subActivity,
            'start_year' => $purchaseRequest->start_year,
            'commitment_date' => $purchaseRequest->commitment_date?->format('Y-m-d'),
            'delivery_date' => $purchaseRequest->delivery_date?->format('Y-m-d'),
            'currency' => $currency,
            'total_amount' => $totalAmount,
            'remaining_amount' => $remainingAmount,
            'description' => $purchaseRequest->description,
            'commitments' => $commitments,
            'items' => $items,
            'search_text' => $searchText,
            'label' => trim(($purchaseRequest->reference_no ?? 'Purchase Request') . ' | ' . $program . ' | ' . $currency . ' ' . number_format($remainingAmount, 2)),
        ];
    }

    private function lineItemTotalFromRequest(Request $request, PurchaseRequest $purchaseRequest): float
    {
        $unitPrices = $request->input('line_item_unit_prices', []);
        $quantities = $request->input('line_item_quantities', []);
        $amounts = $request->input('line_item_amounts', []);
        $evidenceInput = $request->input('item_evidence', []);

        $submittedIds = collect([$unitPrices, $quantities, $amounts, $evidenceInput])
            ->filter(fn ($values) => is_array($values))
            ->flatMap(fn ($values) => array_keys($values))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        $purchaseRequest->loadMissing('items');

        if ($submittedIds->isEmpty()) {
            return round((float) $purchaseRequest->items->sum('amount'), 2);
        }

        $itemsById = $purchaseRequest->items->keyBy(fn ($item) => (string) $item->id);
        $total = 0.0;

        foreach ($submittedIds as $key) {
            if (! $itemsById->has($key)) {
                throw ValidationException::withMessages([
                    'line_item_amounts' => 'One or more edited line items do not belong to the selected purchase request.',
                ]);
            }

            $orderedPricing = $this->lineItemPricingFromRequest($key, $itemsById->get($key), $unitPrices, $quantities, $amounts);
            $total += $this->lineItemDeliveredPricingFromRequest($key, $orderedPricing, $evidenceInput[$key] ?? [])['amount'];
        }

        $total = round($total, 2);
        if ($total <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Purchase order line items must have a total amount greater than zero.',
            ]);
        }

        return $total;
    }

    private function lineItemPricingFromRequest(string $key, $item, array $unitPrices, array $quantities, array $amounts): array
    {
        $existingAmount = round((float) ($item->amount ?? 0), 2);
        $unitPrice = array_key_exists($key, $unitPrices) && $unitPrices[$key] !== null && $unitPrices[$key] !== ''
            ? round((float) $unitPrices[$key], 2)
            : round((float) (($item->unit_price ?? null) ?: $existingAmount), 2);
        $quantity = array_key_exists($key, $quantities) && $quantities[$key] !== null && $quantities[$key] !== ''
            ? round((float) $quantities[$key], 2)
            : round((float) (($item->quantity ?? null) ?: 1), 2);
        $amount = round($unitPrice * $quantity, 2);

        if ($amount <= 0 && array_key_exists($key, $amounts)) {
            $amount = round((float) $amounts[$key], 2);
            $unitPrice = $amount;
            $quantity = 1.00;
        }

        return [
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'amount' => $amount,
        ];
    }

    private function lineItemDeliveredPricingFromRequest(string $key, array $orderedPricing, $input): array
    {
        $input = is_array($input) ? $input : [];
        $unitPrice = array_key_exists('delivered_unit_price', $input) && $input['delivered_unit_price'] !== null && $input['delivered_unit_price'] !== ''
            ? round((float) $input['delivered_unit_price'], 2)
            : round((float) $orderedPricing['unit_price'], 2);
        $quantity = array_key_exists('delivered_quantity', $input) && $input['delivered_quantity'] !== null && $input['delivered_quantity'] !== ''
            ? round((float) $input['delivered_quantity'], 2)
            : round((float) $orderedPricing['quantity'], 2);
        $orderedQuantity = round((float) $orderedPricing['quantity'], 2);

        if ($quantity > $orderedQuantity) {
            throw ValidationException::withMessages([
                "item_evidence.{$key}.delivered_quantity" => 'Delivered quantity cannot exceed the ordered quantity of ' . number_format($orderedQuantity, 2) . '.',
            ]);
        }

        return [
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'amount' => round($unitPrice * $quantity, 2),
        ];
    }

    private function syncPurchaseRequestLineItems(Request $request, PurchaseRequest $purchaseRequest): void
    {
        $categories = $request->input('line_item_resource_categories', []);
        $resources = $request->input('line_item_resources', []);
        $deliverables = $request->input('line_item_deliverables', []);
        $dates = $request->input('line_item_dates', []);
        $unitPrices = $request->input('line_item_unit_prices', []);
        $quantities = $request->input('line_item_quantities', []);
        $amounts = $request->input('line_item_amounts', []);

        $submittedIds = collect([$categories, $resources, $deliverables, $dates, $unitPrices, $quantities, $amounts])
            ->filter(fn ($values) => is_array($values))
            ->flatMap(fn ($values) => array_keys($values))
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($submittedIds->isEmpty()) {
            return;
        }

        $purchaseRequest->loadMissing('items');
        $itemsById = $purchaseRequest->items->keyBy(fn ($item) => (string) $item->id);

        foreach ($submittedIds as $key) {
            if (! $itemsById->has($key)) {
                throw ValidationException::withMessages([
                    'line_item_resources' => 'One or more edited line items do not belong to the selected purchase request.',
                ]);
            }

            $item = $itemsById->get($key);
            $categoryId = (string) ($categories[$key] ?? $item->resource_category_id ?? '');
            $resourceId = (string) ($resources[$key] ?? $item->resource_id ?? '');
            $pricing = $this->lineItemPricingFromRequest($key, $item, $unitPrices, $quantities, $amounts);
            $unitPrice = $pricing['unit_price'];
            $quantity = $pricing['quantity'];
            $amount = $pricing['amount'];
            $text = array_key_exists($key, $deliverables)
                ? trim((string) $deliverables[$key])
                : (string) ($item->milestone ?? '');
            $date = array_key_exists($key, $dates)
                ? trim((string) $dates[$key])
                : $item->milestone_date?->format('Y-m-d');

            if ($categoryId === '' || $resourceId === '' || $amount <= 0) {
                throw ValidationException::withMessages([
                    'line_item_resources' => 'Each purchase request line item must have a category, resource item, and amount.',
                ]);
            }

            $category = ResourceCategory::find($categoryId);
            $resource = Resource::find($resourceId);

            if (!$category || !$resource) {
                throw ValidationException::withMessages([
                    'line_item_resources' => 'One or more selected resource values were not found.',
                ]);
            }

            $this->assertResourceCategoryInScope($category);
            $this->assertResourceInScope($resource);

            if ((string) $resource->resource_category_id !== $categoryId) {
                throw ValidationException::withMessages([
                    'line_item_resources' => 'One or more resource items do not match the selected category.',
                ]);
            }

            $item->update([
                'resource_category_id' => $categoryId,
                'resource_id' => $resourceId,
                'milestone' => $text !== '' ? $text : null,
                'milestone_date' => $date !== '' ? $date : null,
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'amount' => $amount,
            ]);
        }

        $purchaseRequest->unsetRelation('items');
        $purchaseRequest->load('items.deliverable');
        $purchaseRequest->update([
            'total_amount' => round((float) $purchaseRequest->items->sum('amount'), 2),
        ]);
    }

    private function attachSupportingDocument(Request $request, ProcurementPurchaseOrder $purchaseOrder): void
    {
        if (! $request->hasFile('supporting_document')) {
            return;
        }

        $file = $request->file('supporting_document');
        $path = $file->store("procurement_purchase_orders/{$purchaseOrder->id}/supporting-documents");

        $purchaseOrder->update([
            'supporting_document_path' => $path,
            'supporting_document_name' => $file->getClientOriginalName(),
            'supporting_document_mime_type' => $file->getClientMimeType(),
            'supporting_document_size' => $file->getSize(),
        ]);
    }

    private function storeLineItemEvidence(
        Request $request,
        ProcurementPurchaseOrder $purchaseOrder,
        PurchaseRequest $purchaseRequest,
        bool $preserveExistingDocuments = false
    ): void
    {
        $evidenceInput = $request->input('item_evidence', []);
        $filesInput = $request->file('item_evidence', []);
        $unitPrices = $request->input('line_item_unit_prices', []);
        $quantities = $request->input('line_item_quantities', []);
        $amounts = $request->input('line_item_amounts', []);
        $items = $purchaseRequest->items->keyBy(fn ($item) => (string) $item->id);
        $existingEvidence = $preserveExistingDocuments
            ? $purchaseOrder->lineItemEvidence()->get()->keyBy(fn (ProcurementPurchaseOrderItemEvidence $evidence) => (string) $evidence->purchase_request_item_id)
            : collect();

        foreach ($evidenceInput as $itemId => $input) {
            if (!$items->has((string) $itemId)) {
                throw ValidationException::withMessages([
                    'item_evidence' => 'One or more line item evidence records do not belong to the selected purchase request.',
                ]);
            }

            $item = $items->get((string) $itemId);
            $isMet = (bool) ($input['is_met'] ?? false);
            $deliverableDate = trim((string) ($input['deliverable_date'] ?? ''));
            $notes = trim((string) ($input['notes'] ?? ''));
            $documentNames = $input['document_names'] ?? [];
            $existing = $existingEvidence->get((string) $itemId);
            $orderedPricing = $this->lineItemPricingFromRequest((string) $itemId, $item, $unitPrices, $quantities, $amounts);
            $deliveredPricing = $this->lineItemDeliveredPricingFromRequest((string) $itemId, $orderedPricing, $input);
            $documents = $preserveExistingDocuments
                ? collect($existing?->documents ?? [])->filter(fn ($document) => is_array($document))->values()->all()
                : [];

            foreach (($filesInput[$itemId]['documents'] ?? []) as $index => $file) {
                if (!$file || !$file->isValid()) {
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

            $hasDeliveredPricing = array_key_exists('delivered_unit_price', $input)
                || array_key_exists('delivered_quantity', $input)
                || array_key_exists('delivered_amount', $input);

            if (!$isMet && $deliverableDate === '' && $notes === '' && empty($documents) && ! $hasDeliveredPricing) {
                if ($existing) {
                    $existing->delete();
                    continue;
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
                    'delivered_unit_price' => $deliveredPricing['unit_price'],
                    'delivered_quantity' => $deliveredPricing['quantity'],
                    'delivered_amount' => $deliveredPricing['amount'],
                    'notes' => $notes !== '' ? $notes : null,
                    'documents' => $documents,
                    'created_by' => auth()->id(),
                ]
            );
        }
    }

    private function deleteLineItemEvidenceDocuments(ProcurementPurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing('lineItemEvidence');

        foreach ($purchaseOrder->lineItemEvidence as $evidence) {
            foreach (($evidence->documents ?? []) as $document) {
                $path = $document['path'] ?? null;
                if ($path && Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);
                }
            }
        }
    }

    private function deleteSupportingDocument(ProcurementPurchaseOrder $purchaseOrder): void
    {
        $path = (string) ($purchaseOrder->supporting_document_path ?? '');
        if ($path === '') {
            return;
        }

        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
