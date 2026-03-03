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

        $missingRequirements = $this->missingPublishingRequirements($procurement);
        if (!empty($missingRequirements)) {
            return back()->with('error', $this->publishBlockedMessage($missingRequirements));
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

    public function draft(Procurement $procurement)
    {
        $this->assertProcurementInScope($procurement);
        if ($procurement->status !== 'closed') {
            return back()->with('error', 'Only closed procurements can be moved back to draft.');
        }

        $procurement->update([
            'status' => 'draft',
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Procurement moved back to draft.');
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

    private function missingPublishingRequirements(Procurement $procurement): array
    {
        $missing = [];

        if (!$procurement->prescreeningTemplate()->exists()) {
            $missing[] = 'a prescreening template';
        }

        if (!$procurement->forms()->exists()) {
            $missing[] = 'at least one attached form';
        }

        return $missing;
    }

    private function publishBlockedMessage(array $missingRequirements): string
    {
        if (count($missingRequirements) === 1) {
            return 'Cannot publish this procurement yet. Please add ' . $missingRequirements[0] . ' first.';
        }

        return 'Cannot publish this procurement yet. Please add ' . implode(' and ', $missingRequirements) . ' first.';
    }
}
