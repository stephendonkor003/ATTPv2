<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Services\ProcurementWorkflowService;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;

class ProcurementWorkflowController extends Controller
{
    use GovernanceScope;

    public function approve(
        Procurement $procurement,
        ProcurementWorkflowService $service
    ) {
        $this->assertProcurementInScope($procurement);
        $service->approve($procurement);
        return back()->with('success', 'Procurement approved');
    }

    public function publish(
        Procurement $procurement,
        ProcurementWorkflowService $service
    ) {
        $this->assertProcurementInScope($procurement);
        try {
            $service->publish($procurement);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Procurement published');
    }

    public function close(
        Procurement $procurement,
        ProcurementWorkflowService $service
    ) {
        $this->assertProcurementInScope($procurement);
        $service->close($procurement);
        return back()->with('success', 'Procurement closed');
    }

    public function award(
        Procurement $procurement,
        ProcurementWorkflowService $service
    ) {
        $this->assertProcurementInScope($procurement);
        try {
            $service->award($procurement);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Procurement awarded and vendor notified');
    }
}
