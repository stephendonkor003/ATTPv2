<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\ProcurementPlan;
use Illuminate\Http\Request;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;

class ProcurementStatusController extends Controller
{
    use GovernanceScope;

    /* ===============================
     | STATUS TRANSITIONS ONLY
     =============================== */

    public function submit(Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);
        if (!in_array($procurement->status, ['draft', 'rejected'])) {
            return back()->with('error', 'Only draft or rejected procurements can be submitted.');
        }

        $procurement->update([
            'status' => 'submitted',
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Procurement submitted.');
    }

    public function approve(Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);
        if ($procurement->status !== 'submitted') {
            return back()->with('error', 'Only submitted procurements can be approved.');
        }

        $procurement->update([
            'status' => 'approved',
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Procurement approved.');
    }

    public function reject(Request $request, Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);
        if ($procurement->status !== 'submitted') {
            return back()->with('error', 'Only submitted procurements can be rejected.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|min:5',
        ]);

        $procurement->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
        ]);

        return back()->with('success', 'Procurement rejected.');
    }

    public function publish(Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);
        if ($procurement->status !== 'approved') {
            return back()->with('error', 'Only approved procurements can be published.');
        }

        $procurement->update([
            'status' => 'published',
        ]);

        ProcurementPlan::markLaunchedByCode($procurement->reference_no);

        return back()->with('success', 'Procurement published.');
    }

    public function close(Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);
        if ($procurement->status !== 'published') {
            return back()->with('error', 'Only published procurements can be closed.');
        }

        $procurement->update([
            'status' => 'closed',
        ]);

        return back()->with('success', 'Procurement closed.');
    }

    public function award(Procurement $procurement, \App\Services\ProcurementAwardService $awardService)
    {
        $this->assertProcurementInScope($procurement);
        try {
            $awardService->award($procurement);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Procurement awarded and vendor notified.');
    }
}
