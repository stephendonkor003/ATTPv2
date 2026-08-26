<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\ProcurementAuditLog;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use Illuminate\Support\Facades\DB;

class ProcurementAuditController extends Controller
{
    use GovernanceScope;

    public function index()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to audit logs.');
        }

        $logs = ProcurementAuditLog::query()
            ->with(['procurement', 'user'])
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('procurement_id', function ($sub) use ($scopedNodeIds) {
                    $sub->select('id')
                        ->from('procurements')
                        ->whereIn('governance_node_id', $scopedNodeIds)
                        ->whereNotNull('governance_node_id');
                });
            })
            ->latest()
            ->paginate(50);
        return view('procurement.audit.index', compact('logs'));
    }
}
