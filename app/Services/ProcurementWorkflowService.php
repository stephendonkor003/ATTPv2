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
        $missingRequirements = $this->missingPublishingRequirements($procurement);
        if (!empty($missingRequirements)) {
            throw new Exception($this->publishBlockedMessage($missingRequirements));
        }

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
