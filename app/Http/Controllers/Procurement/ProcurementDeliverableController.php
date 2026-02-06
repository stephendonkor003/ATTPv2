<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\ProcurementDeliverable;
use App\Models\User;
use Illuminate\Http\Request;

class ProcurementDeliverableController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner', 'permission:procurement.manage_all']);
    }

    public function index(Request $request)
    {
        $procurementId = $request->string('procurement_id')->toString();
        $vendorId = $request->string('vendor_id')->toString();
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();

        $baseQuery = ProcurementDeliverable::query();
        if ($procurementId) {
            $baseQuery->where('procurement_id', $procurementId);
        }
        if ($vendorId) {
            $baseQuery->where('vendor_id', $vendorId);
        }

        $counts = [
            'total' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'completed' => (clone $baseQuery)->where('status', 'completed')->count(),
            'cancelled' => (clone $baseQuery)->where('status', 'cancelled')->count(),
            'awaiting_admin' => (clone $baseQuery)->where('admin_approval_status', 'pending')->count(),
            'awaiting_vendor' => (clone $baseQuery)->where('vendor_approval_status', 'pending')->count(),
        ];

        $deliverables = $baseQuery
            ->with(['procurement', 'vendor'])
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($type, fn($query) => $query->where('type', $type))
            ->orderByRaw('COALESCE(timeline_start, created_at)')
            ->orderBy('sequence')
            ->paginate(20)
            ->appends($request->query());

        $procurements = Procurement::orderBy('reference_no')->get();
        $vendors = User::where('user_type', 'vendor')->orderBy('name')->get();

        return view('procurement.deliverables.index', [
            'deliverables' => $deliverables,
            'procurements' => $procurements,
            'vendors' => $vendors,
            'counts' => $counts,
            'filters' => [
                'procurement_id' => $procurementId,
                'vendor_id' => $vendorId,
                'status' => $status,
                'type' => $type,
            ],
        ]);
    }

    public function sheet(Request $request)
    {
        $procurementId = $request->string('procurement_id')->toString();
        $vendorId = $request->string('vendor_id')->toString();

        $deliverables = ProcurementDeliverable::with(['procurement', 'vendor'])
            ->when($procurementId, fn($query) => $query->where('procurement_id', $procurementId))
            ->when($vendorId, fn($query) => $query->where('vendor_id', $vendorId))
            ->orderByRaw('COALESCE(timeline_start, created_at)')
            ->orderBy('sequence')
            ->get();

        return view('procurement.deliverables.sheet', [
            'deliverables' => $deliverables,
        ]);
    }

    public function approve(ProcurementDeliverable $deliverable)
    {
        if ($deliverable->admin_approval_status === 'approved') {
            return back()->with('success', 'Deliverable already approved.');
        }

        $deliverable->update([
            'admin_approval_status' => 'approved',
            'admin_approved_by' => auth()->id(),
            'admin_approved_at' => now(),
        ]);

        return back()->with('success', 'Deliverable approved successfully.');
    }

    public function reject(Request $request, ProcurementDeliverable $deliverable)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $deliverable->update([
            'admin_approval_status' => 'rejected',
            'admin_approved_by' => auth()->id(),
            'admin_approved_at' => now(),
            'notes' => $data['reason'],
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return back()->with('success', 'Deliverable rejected.');
    }

    public function updateStatus(Request $request, ProcurementDeliverable $deliverable)
    {
        $data = $request->validate([
            'status' => 'required|string|in:pending,in_progress,completed,cancelled',
        ]);

        $updates = [
            'status' => $data['status'],
        ];

        if ($data['status'] === 'completed') {
            $updates['completed_at'] = now();
        } else {
            $updates['completed_at'] = null;
        }

        if ($data['status'] === 'cancelled') {
            $updates['cancelled_at'] = now();
        } else {
            $updates['cancelled_at'] = null;
        }

        $deliverable->update($updates);

        return back()->with('success', 'Deliverable status updated.');
    }
}
