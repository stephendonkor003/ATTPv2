<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Mail\VendorDisbursementReceipt;
use App\Models\ProcurementAuditLog;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

        $disbursements = ProcurementDisbursement::with(['purchaseOrder', 'vendor', 'procurement', 'thinkTankMember', 'consortium'])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderByDesc('paid_at')
            ->paginate(12);

        return view('procurement.disbursements.index', compact('disbursements'));
    }

    public function create(Request $request)
    {
        $purchaseOrderId = $request->get('purchase_order_id');
        $purchaseOrder = null;
        $purchaseOrders = collect();
        $paymentMethods = [
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

        if ($purchaseOrderId) {
            $purchaseOrder = ProcurementPurchaseOrder::with(['procurement', 'vendor', 'subActivity', 'disbursements', 'thinkTankMember', 'consortium'])
                ->findOrFail($purchaseOrderId);
            $this->assertPurchaseOrderInScope($purchaseOrder);
        } else {
            $scopedNodeIds = $this->scopedNodeIds();
            $purchaseOrders = ProcurementPurchaseOrder::with(['procurement', 'vendor', 'disbursements', 'thinkTankMember', 'consortium'])
                ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                    $query->whereIn('governance_node_id', $scopedNodeIds)
                        ->whereNotNull('governance_node_id');
                })
                ->orderByDesc('created_at')
                ->get()
                ->filter(function (ProcurementPurchaseOrder $order) {
                    return $order->remainingAmount() > 0;
                })
                ->values();
        }

        return view('procurement.disbursements.create', [
            'purchaseOrder' => $purchaseOrder,
            'purchaseOrders' => $purchaseOrders,
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'purchase_order_id' => 'required|exists:procurement_purchase_orders,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|max:100',
            'paid_at' => 'required|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $purchaseOrder = ProcurementPurchaseOrder::with(['procurement', 'vendor', 'subActivity', 'disbursements', 'thinkTankMember', 'consortium'])
            ->findOrFail($data['purchase_order_id']);
        $this->assertPurchaseOrderInScope($purchaseOrder);

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

        $disbursement = ProcurementDisbursement::create([
            'purchase_order_id' => $purchaseOrder->id,
            'procurement_id' => $purchaseOrder->procurement_id,
            'vendor_id' => $purchaseOrder->vendor_id,
            'sub_activity_id' => $purchaseOrder->sub_activity_id,
            'governance_node_id' => $purchaseOrder->governance_node_id,
            'consortium_id' => $purchaseOrder->consortium_id,
            'think_tank_member_id' => $purchaseOrder->think_tank_member_id,
            'reference_no' => ProcurementDisbursement::generateReference(),
            'amount' => $data['amount'],
            'currency' => $purchaseOrder->currency,
            'payment_method' => $data['payment_method'],
            'status' => 'completed',
            'paid_at' => $data['paid_at'],
            'created_by' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);

        $this->syncPurchaseOrderStatus($purchaseOrder);

        ProcurementAuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'Created disbursement',
            'procurement_id' => $purchaseOrder->procurement_id,
            'metadata' => [
                'purchase_order_id' => $purchaseOrder->id,
                'disbursement_id' => $disbursement->id,
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
        $disbursement->load(['purchaseOrder', 'vendor', 'procurement', 'subActivity']);

        return view('procurement.disbursements.show', compact('disbursement'));
    }

    public function pdf(ProcurementDisbursement $disbursement)
    {
        $this->assertDisbursementInScope($disbursement);
        $disbursement->load(['purchaseOrder', 'vendor', 'procurement', 'subActivity']);

        $pdf = Pdf::loadView('procurement.disbursements.pdf', [
            'disbursement' => $disbursement,
        ]);

        return $pdf->stream('receipt-' . ($disbursement->reference_no ?? 'payment') . '.pdf');
    }

    public function download(ProcurementDisbursement $disbursement)
    {
        $this->assertDisbursementInScope($disbursement);
        $disbursement->load(['purchaseOrder', 'vendor', 'procurement', 'subActivity']);

        $pdf = Pdf::loadView('procurement.disbursements.pdf', [
            'disbursement' => $disbursement,
        ]);

        return $pdf->download('receipt-' . ($disbursement->reference_no ?? 'payment') . '.pdf');
    }

    private function syncPurchaseOrderStatus(ProcurementPurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->refresh();
        $remaining = $purchaseOrder->remainingAmount();
        $totalPaid = $purchaseOrder->paidAmount();

        $status = $totalPaid <= 0 ? 'draft' : ($remaining <= 0 ? 'paid' : 'partial_paid');

        $purchaseOrder->update([
            'status' => $status,
        ]);
    }

    private function sendReceipt(ProcurementDisbursement $disbursement): void
    {
        $vendor = $disbursement->vendor;
        if (!$vendor || empty($vendor->email)) {
            return;
        }

        $disbursement->loadMissing(['purchaseOrder', 'procurement', 'subActivity']);

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
