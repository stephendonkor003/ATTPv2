<?php

namespace App\Services;

use App\Models\Procurement;
use App\Models\ProcurementAuditLog;
use App\Models\ProcurementPlan;
use Exception;

class ProcurementWorkflowService
{
    public function approve(Procurement $procurement)
    {
        $this->ensureStatus($procurement, 'draft');
        $this->transition($procurement, 'approved', 'Approved procurement');
    }

    public function publish(Procurement $procurement)
    {
        $this->ensureStatus($procurement, 'approved');
        $this->transition($procurement, 'published', 'Published procurement');
        ProcurementPlan::markLaunchedByCode($procurement->reference_no);
    }

    public function close(Procurement $procurement)
    {
        $this->ensureStatus($procurement, 'published');
        $this->transition($procurement, 'closed', 'Closed procurement');
    }

    public function award(Procurement $procurement)
    {
        app(\App\Services\ProcurementAwardService::class)->award($procurement);
    }

    private function ensureStatus(Procurement $procurement, string $required)
    {
        if ($procurement->status !== $required) {
            throw new Exception(
                "Invalid action. Procurement must be '{$required}'."
            );
        }
    }

    private function transition(
        Procurement $procurement,
        string $to,
        string $action
    ) {
        $procurement->update(['status' => $to]);

        ProcurementAuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'procurement_id' => $procurement->id,
            'created_at' => now()
        ]);
    }
}
