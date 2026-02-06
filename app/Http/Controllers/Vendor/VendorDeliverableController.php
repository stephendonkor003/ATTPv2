<?php

namespace App\Http\Controllers\Vendor;

use App\Exports\Procurement\VendorDeliverableTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\Procurement\VendorDeliverableImport;
use App\Models\Procurement;
use App\Models\ProcurementDeliverable;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException as ExcelValidationException;

class VendorDeliverableController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $procurementId = $request->string('procurement_id')->toString();
        $status = $request->string('status')->toString();
        $type = $request->string('type')->toString();

        $awardedProcurements = Procurement::where('status', 'awarded')
            ->where('awarded_vendor_id', $user->id)
            ->orderByDesc('awarded_at')
            ->get();

        $baseQuery = ProcurementDeliverable::where('vendor_id', $user->id);
        if ($procurementId) {
            $baseQuery->where('procurement_id', $procurementId);
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
            ->with('procurement')
            ->when($status, fn($query) => $query->where('status', $status))
            ->when($type, fn($query) => $query->where('type', $type))
            ->orderByRaw('COALESCE(timeline_start, created_at)')
            ->orderBy('sequence')
            ->paginate(20)
            ->appends($request->query());

        return view('vendor.deliverables.index', [
            'deliverables' => $deliverables,
            'awardedProcurements' => $awardedProcurements,
            'counts' => $counts,
            'filters' => [
                'procurement_id' => $procurementId,
                'status' => $status,
                'type' => $type,
            ],
        ]);
    }

    public function sheet(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $deliverables = ProcurementDeliverable::with('procurement')
            ->where('vendor_id', $user->id)
            ->orderByRaw('COALESCE(timeline_start, created_at)')
            ->orderBy('sequence')
            ->get();

        return view('vendor.deliverables.sheet', [
            'deliverables' => $deliverables,
        ]);
    }

    public function template(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        return Excel::download(new VendorDeliverableTemplateExport(), 'vendor_deliverables_template.xlsx');
    }

    public function import(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $data = $request->validate([
            'procurement_id' => 'required|exists:procurements,id',
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $procurement = Procurement::where('id', $data['procurement_id'])
            ->where('status', 'awarded')
            ->where('awarded_vendor_id', $user->id)
            ->firstOrFail();

        try {
            Excel::import(
                new VendorDeliverableImport($procurement->id, $user->id),
                $request->file('file')
            );
        } catch (ExcelValidationException $exception) {
            $errors = $exception->failures();
            return back()
                ->with('import_errors', $errors)
                ->withInput();
        }

        return back()->with('success', 'Deliverables uploaded successfully.');
    }

    public function approve(Request $request, ProcurementDeliverable $deliverable)
    {
        $user = $request->user();
        $this->assertVendor($user);

        if ($deliverable->vendor_id !== $user->id) {
            abort(403, 'You are not authorized to approve this deliverable.');
        }

        if ($deliverable->vendor_approval_status === 'approved') {
            return back()->with('success', 'Deliverable already approved.');
        }

        $deliverable->update([
            'vendor_approval_status' => 'approved',
            'vendor_approved_by' => $user->id,
            'vendor_approved_at' => now(),
        ]);

        return back()->with('success', 'Deliverable approved successfully.');
    }

    public function updateStatus(Request $request, ProcurementDeliverable $deliverable)
    {
        $user = $request->user();
        $this->assertVendor($user);

        if ($deliverable->vendor_id !== $user->id) {
            abort(403, 'You are not authorized to update this deliverable.');
        }

        if (!$deliverable->isAgreed()) {
            return back()->with('error', 'Deliverables must be approved by both parties before updating status.');
        }

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

    private function assertVendor($user): void
    {
        if (!$user || $user->user_type !== 'vendor') {
            abort(403, 'Access denied. Vendor portal only.');
        }

        if ($user->is_blacklisted) {
            abort(403, 'Your vendor account has been blacklisted. Please contact the administrator.');
        }

        if ($user->is_disabled) {
            abort(403, 'Your vendor account has been disabled. Please contact the administrator.');
        }
    }
}
