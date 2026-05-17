<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Models\Activity;
use App\Models\BudgetCommitment;
use App\Models\Procurement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ProgramFunding;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\SubActivity;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
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
            'budgetCommitment',
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

        $commitments = BudgetCommitment::query()
            ->where('status', BudgetCommitment::STATUS_APPROVED)
            ->whereNotNull('commitment_amount')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderByDesc('commitment_year')
            ->get()
            ->map(function (BudgetCommitment $commitment) {
                $hierarchy = $this->commitmentHierarchy($commitment);

                $commitment->remaining_amount = $this->remainingCommitmentAmount($commitment);
                $commitment->project_label = $hierarchy['project'];
                $commitment->activity_label = $hierarchy['activity'];
                $commitment->sub_activity_label = $hierarchy['sub_activity'];
                $commitment->purchase_request_reference = $this->commitmentPurchaseRequestReference($commitment);
                $commitment->commitment_currency = $this->commitmentCurrency($commitment);

                return $commitment;
            })
            ->filter(fn (BudgetCommitment $commitment) => $commitment->remaining_amount > 0)
            ->values();

        $procurements = Procurement::query()
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('title')
            ->get();

        $vendors = User::where('user_type', 'vendor')
            ->orderBy('name')
            ->get();

        return view('procurement.purchase-orders.create', compact('commitments', 'procurements', 'vendors'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'budget_commitment_id' => ['required', 'exists:myb_budget_commitments,id'],
            'procurement_id' => ['nullable', 'exists:procurements,id'],
            'vendor_id' => ['nullable', 'exists:users,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'status' => ['required', 'in:draft,issued,closed,cancelled'],
            'issued_at' => ['nullable', 'date'],
        ]);

        $commitment = BudgetCommitment::findOrFail($data['budget_commitment_id']);
        $this->assertCommitmentInScope($commitment);

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
        }

        $purchaseOrder = ProcurementPurchaseOrder::create([
            'budget_commitment_id' => $commitment->id,
            'procurement_id' => $procurement?->id,
            'vendor_id' => $data['vendor_id'] ?? $procurement?->awarded_vendor_id,
            'sub_activity_id' => $commitment->allocation_level === 'sub_activity' ? $commitment->allocation_id : null,
            'governance_node_id' => $commitment->governance_node_id,
            'reference_no' => ProcurementPurchaseOrder::generateReference(),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?: $this->commitmentCurrency($commitment),
            'status' => $data['status'],
            'created_by' => auth()->id(),
            'issued_at' => $data['issued_at'] ?? now(),
        ]);

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order created and tied to the approved commitment.');
    }

    public function show(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $purchaseOrder->load(['procurement', 'vendor', 'subActivity', 'negotiation', 'invoice', 'disbursements', 'budgetCommitment']);

        return view('procurement.purchase-orders.show', compact('purchaseOrder'));
    }

    public function pdf(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $purchaseOrder->load(['procurement', 'vendor', 'subActivity', 'negotiation', 'invoice', 'budgetCommitment']);

        $pdf = Pdf::loadView('procurement.purchase-orders.pdf', [
            'purchaseOrder' => $purchaseOrder,
        ]);

        return $pdf->stream('purchase-order-' . ($purchaseOrder->reference_no ?? 'draft') . '.pdf');
    }

    public function download(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $purchaseOrder->load(['procurement', 'vendor', 'subActivity', 'negotiation', 'invoice', 'budgetCommitment']);

        $pdf = Pdf::loadView('procurement.purchase-orders.pdf', [
            'purchaseOrder' => $purchaseOrder,
        ]);

        return $pdf->download('purchase-order-' . ($purchaseOrder->reference_no ?? 'draft') . '.pdf');
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
}
