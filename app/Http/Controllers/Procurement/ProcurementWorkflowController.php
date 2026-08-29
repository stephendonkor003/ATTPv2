<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Models\Procurement;
use App\Services\ProcurementWorkflowService;
use Illuminate\Validation\ValidationException;

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
        } catch (ValidationException $e) {
            return back()->with(
                'error',
                data_get($e->errors(), 'procurement.0', 'Complete the pending evaluation rework before awarding this procurement.')
            );
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Procurement awarded and vendor notified');
    }
}
