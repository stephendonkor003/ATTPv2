<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Models\Activity;
use App\Models\BudgetCommitment;
use App\Models\Procurement;
use App\Models\ProcurementDeliverable;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ProgramFunding;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\SubActivity;
use App\Models\User;
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
        $this->middleware(['auth', 'not.funding.partner', 'permission:finance.purchase_requests.view']);
    }

    public function index()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to purchase orders.');
        }

        $purchaseOrders = ProcurementPurchaseOrder::with([
            'procurement',
            'vendor',
            'subActivity',
            'invoice',
            'budgetCommitment.purchaseRequest',
            'purchaseRequest',
        ])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('procurement.purchase-orders.index', compact('purchaseOrders'));
    }

    public function create()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to create purchase orders.');
        }

        $purchaseRequests = PurchaseRequest::with([
            'programFunding.program',
            'governanceNode',
            'subActivity',
            'items.resourceCategory',
            'items.resource',
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

        $procurementIds = $procurements->pluck('id');

        $deliverablesRaw = ProcurementDeliverable::whereIn('procurement_id', $procurementIds)
            ->orderBy('sequence')
            ->orderBy('title')
            ->get();

        $deliverablesByProcurement = $deliverablesRaw
            ->groupBy(fn (ProcurementDeliverable $d) => (string) $d->procurement_id)
            ->map(fn ($group) => $group->map(fn (ProcurementDeliverable $d) => [
                'id'          => (string) $d->id,
                'title'       => $d->title,
                'type'        => $d->type,
                'description' => $d->description,
                'status'      => $d->status,
                'amount'      => (float) ($d->amount ?? 0),
                'currency'    => $d->currency,
                'start'       => $d->timeline_start?->format('M d, Y'),
                'end'         => $d->timeline_end?->format('M d, Y'),
            ])->values())
            ->toArray();

        $buyerDefaults = [
            'name' => auth()->user()?->name,
            'email' => auth()->user()?->email,
        ];

        return view('procurement.purchase-orders.create', compact(
            'purchaseRequests',
            'procurements',
            'procurementOptions',
            'deliverablesByProcurement',
            'vendors',
            'vendorOptions',
            'buyerDefaults'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_request_id' => ['required', 'exists:myb_purchase_requests,id'],
            'budget_commitment_id' => ['required', 'exists:myb_budget_commitments,id'],
            'procurement_id' => ['nullable', 'exists:procurements,id'],
            'deliverable_ids'   => ['nullable', 'array'],
            'deliverable_ids.*' => ['exists:procurement_deliverables,id'],
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
        ], [
            'purchase_request_id.required' => 'Select the approved purchase request before creating the purchase order.',
            'budget_commitment_id.required' => 'Select the approved commitment year that will fund this purchase order.',
            'billing_address.required' => 'Enter the bill-to address for this purchase order.',
            'shipping_address.required' => 'Enter the ship-to address for this purchase order.',
            'delivery_terms.required' => 'Enter the delivery terms for this purchase order.',
            'payment_terms.required' => 'Enter the payment terms for this purchase order.',
            'supporting_document.required' => 'Attach the supporting documentation before creating this purchase order.',
            'supporting_document.mimes' => 'Supporting documentation must be a PDF, Office document, image, or ZIP file.',
        ]);

        $purchaseRequest = PurchaseRequest::with('commitments')->findOrFail($data['purchase_request_id']);
        $this->assertPurchaseRequestInScope($purchaseRequest);

        if ($purchaseRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'purchase_request_id' => 'Only approved purchase requests can be used to create purchase orders.',
            ]);
        }

        $commitment = BudgetCommitment::findOrFail($data['budget_commitment_id']);
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

        $remaining = $this->remainingCommitmentAmount($commitment);
        if ((float) $data['amount'] > $remaining) {
            throw ValidationException::withMessages([
                'amount' => 'The purchase order amount cannot exceed the remaining commitment balance of ' . number_format($remaining, 2) . '.',
            ]);
        }

        $procurement = null;
        if (!empty($data['procurement_id'])) {
            $procurement = Procurement::findOrFail($data['procurement_id']);
            $this->assertProcurementInScope($procurement);

            if (!empty($data['deliverable_ids'])) {
                $invalid = ProcurementDeliverable::whereIn('id', $data['deliverable_ids'])
                    ->where('procurement_id', '!=', $procurement->id)
                    ->exists();

                if ($invalid) {
                    throw ValidationException::withMessages([
                        'deliverable_ids' => 'One or more selected deliverables do not belong to the chosen procurement.',
                    ]);
                }
            }
        }

        $vendor = null;
        if (!empty($data['vendor_id'])) {
            $vendor = User::query()
                ->where('user_type', 'vendor')
                ->findOrFail($data['vendor_id']);
        } elseif ($procurement?->awarded_vendor_id) {
            $vendor = User::query()->find($procurement->awarded_vendor_id);
        }

        $purchaseOrder = DB::transaction(function () use ($commitment, $data, $procurement, $purchaseRequest, $request, $vendor) {
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
                'currency' => $data['currency'] ?: $this->commitmentCurrency($commitment),
                'status' => $data['status'],
                'created_by' => auth()->id(),
                'issued_at' => $data['issued_at'] ?? now(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? $purchaseRequest->delivery_date?->toDateString(),
                'valid_until' => $data['valid_until'] ?? null,
            ]);

            $this->attachSupportingDocument($request, $purchaseOrder);

            if (!empty($data['deliverable_ids'])) {
                $purchaseOrder->deliverables()->sync($data['deliverable_ids']);
            }

            return $purchaseOrder;
        });

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order created from approved purchase request ' . $purchaseRequest->reference_no . '.');
    }

    public function show(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $purchaseOrder->load([
            'procurement',
            'deliverables',
            'vendor',
            'subActivity',
            'negotiation',
            'invoice',
            'disbursements',
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
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
            'vendor',
            'subActivity',
            'negotiation',
            'invoice',
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
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
            'vendor',
            'subActivity',
            'negotiation',
            'invoice',
            'budgetCommitment.purchaseRequest.items.resourceCategory',
            'budgetCommitment.purchaseRequest.items.resource',
            'purchaseRequest.items.resourceCategory',
            'purchaseRequest.items.resource',
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

    public function destroy(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->disbursements()->update(['purchase_order_id' => null]);
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

    private function remainingCommitmentAmount(BudgetCommitment $commitment): float
    {
        $committed = (float) ($commitment->commitment_amount ?? 0);
        $issued = (float) ProcurementPurchaseOrder::query()
            ->where('budget_commitment_id', $commitment->id)
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
        if ($commitment->program_funding_id) {
            $currency = ProgramFunding::query()->whereKey($commitment->program_funding_id)->value('currency');
            if ($currency) {
                return $currency;
            }
        }

        if ($commitment->purchase_request_id) {
            $currency = PurchaseRequest::query()->whereKey($commitment->purchase_request_id)->value('currency');
            if ($currency) {
                return $currency;
            }
        }

        return 'USD';
    }

    private function purchaseRequestCreateOption(PurchaseRequest $purchaseRequest): ?array
    {
        $commitments = $purchaseRequest->commitments
            ->filter(fn (BudgetCommitment $commitment) => $commitment->status === BudgetCommitment::STATUS_APPROVED)
            ->map(function (BudgetCommitment $commitment) {
                $remaining = $this->remainingCommitmentAmount($commitment);
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
        $currency = $purchaseRequest->currency ?: ($firstCommitment['currency'] ?? 'USD');
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
                'category' => $item->resourceCategory?->name ?: 'N/A',
                'resource' => $item->resource?->name ?: 'N/A',
                'description' => $item->milestone ?: $item->observations ?: $item->object_type ?: 'N/A',
                'milestone_date' => $item->milestone_date?->format('Y-m-d'),
                'amount' => round((float) $item->amount, 2),
                'budget_code' => $item->budget_code,
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
