<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Models\ProcurementPurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;

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
        ])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('procurement.purchase-orders.index', compact('purchaseOrders'));
    }

    public function show(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $purchaseOrder->load(['procurement', 'vendor', 'subActivity', 'negotiation', 'invoice', 'disbursements']);

        return view('procurement.purchase-orders.show', compact('purchaseOrder'));
    }

    public function pdf(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $purchaseOrder->load(['procurement', 'vendor', 'subActivity', 'negotiation', 'invoice']);

        $pdf = Pdf::loadView('procurement.purchase-orders.pdf', [
            'purchaseOrder' => $purchaseOrder,
        ]);

        return $pdf->stream('purchase-order-' . ($purchaseOrder->reference_no ?? 'draft') . '.pdf');
    }

    public function download(ProcurementPurchaseOrder $purchaseOrder)
    {
        $this->assertPurchaseOrderInScope($purchaseOrder);

        $purchaseOrder->load(['procurement', 'vendor', 'subActivity', 'negotiation', 'invoice']);

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
}
