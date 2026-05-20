<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Models\Procurement;
use App\Models\ProcurementAuditLog;
use App\Models\ProcurementContractNegotiation;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPlan;
use App\Models\ProcurementPurchaseOrder;
use Illuminate\Http\Request;

class ProcurementInvoiceController extends Controller
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
            abort(403, 'You do not have access to invoices.');
        }

        $invoices = ProcurementInvoice::with(['procurement', 'vendor', 'subActivity', 'purchaseOrder.thinkTankMember'])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('procurement.invoices.index', compact('invoices'));
    }

    public function show(ProcurementInvoice $invoice)
    {
        $this->assertInvoiceInScope($invoice);

        $invoice->load(['procurement', 'vendor', 'subActivity', 'purchaseOrder.thinkTankMember', 'purchaseOrder.budgetCommitment', 'purchaseOrder.disbursements', 'deliverables']);

        $budget = null;
        $currency = null;
        if ($invoice->procurement) {
            [$budget, $currency] = $this->resolveBudget($invoice->procurement);
        } elseif ($invoice->purchaseOrder) {
            $budget = (float) ($invoice->purchaseOrder->budgetCommitment?->commitment_amount ?? $invoice->purchaseOrder->amount ?? $invoice->amount);
            $currency = $invoice->purchaseOrder->currency ?? $invoice->currency;
        }

        $totalInvoiced = $invoice->procurement_id
            ? ProcurementInvoice::where('procurement_id', $invoice->procurement_id)
                ->where('status', '!=', 'rejected')
                ->sum('amount')
            : ($invoice->purchaseOrder?->paidAmount() ?? (float) $invoice->amount);

        $remaining = $budget !== null ? max($budget - $totalInvoiced, 0) : null;

        return view('procurement.invoices.show', [
            'invoice' => $invoice,
            'budget' => $budget,
            'currency' => $currency,
            'remaining' => $remaining,
        ]);
    }

    public function approve(ProcurementInvoice $invoice)
    {
        $this->assertInvoiceInScope($invoice);

        if ($invoice->status === 'approved') {
            return back()->with('success', 'Invoice already approved.');
        }

        if ($invoice->status === 'rejected') {
            return back()->with('error', 'Rejected invoices cannot be approved.');
        }

        $invoice->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        ProcurementAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'Approved procurement invoice',
            'procurement_id' => $invoice->procurement_id,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->amount,
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Invoice approved successfully.');
    }

    public function reject(Request $request, ProcurementInvoice $invoice)
    {
        $this->assertInvoiceInScope($invoice);

        if ($invoice->purchaseOrder) {
            return back()->with('error', 'This invoice already has a purchase order and cannot be rejected.');
        }

        if ($invoice->status !== 'submitted') {
            return back()->with('error', 'Only submitted invoices can be rejected.');
        }

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $invoice->update([
            'status' => 'rejected',
            'notes' => $data['reason'],
        ]);

        ProcurementAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'Rejected procurement invoice',
            'procurement_id' => $invoice->procurement_id,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'reason' => $data['reason'],
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Invoice rejected.');
    }

    public function createPurchaseOrder(ProcurementInvoice $invoice)
    {
        $this->assertInvoiceInScope($invoice);

        if ($invoice->purchaseOrder) {
            return back()->with('error', 'A purchase order already exists for this invoice.');
        }

        if ($invoice->status !== 'approved') {
            return back()->with('error', 'Only approved invoices can generate purchase orders.');
        }

        $procurement = Procurement::find($invoice->procurement_id);
        if (!$procurement) {
            return back()->with('error', 'Procurement record not found.');
        }

        $negotiation = ProcurementContractNegotiation::where('procurement_id', $procurement->id)
            ->where('status', 'agreed')
            ->orderByDesc('agreed_at')
            ->first();

        $purchaseOrder = ProcurementPurchaseOrder::create([
            'procurement_id' => $procurement->id,
            'negotiation_id' => $negotiation?->id,
            'invoice_id' => $invoice->id,
            'vendor_id' => $invoice->vendor_id,
            'sub_activity_id' => $invoice->sub_activity_id,
            'governance_node_id' => $invoice->governance_node_id,
            'reference_no' => ProcurementPurchaseOrder::generateReference(),
            'amount' => $invoice->amount,
            'currency' => $invoice->currency,
            'status' => 'draft',
            'created_by' => auth()->id(),
            'issued_at' => now(),
        ]);

        ProcurementAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'Generated purchase order from invoice',
            'procurement_id' => $procurement->id,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'purchase_order_id' => $purchaseOrder->id,
            ],
            'created_at' => now(),
        ]);

        return redirect()
            ->route('procurement.purchase-orders.show', $purchaseOrder)
            ->with('success', 'Purchase order generated successfully.');
    }

    private function resolveBudget(Procurement $procurement): array
    {
        $plan = null;
        if (!empty($procurement->reference_no)) {
            $plan = ProcurementPlan::where('procurement_code', $procurement->reference_no)->first();
        }

        $negotiation = ProcurementContractNegotiation::where('procurement_id', $procurement->id)
            ->where('status', 'agreed')
            ->orderByDesc('agreed_at')
            ->first();

        $budget = $plan?->estimated_budget ?? $negotiation?->agreed_amount ?? $procurement->estimated_budget;
        $currency = $plan?->currency ?? null;

        return [$budget, $currency];
    }

    private function assertInvoiceInScope(ProcurementInvoice $invoice): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$invoice->governance_node_id || !in_array($invoice->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this invoice.');
        }
    }
}
