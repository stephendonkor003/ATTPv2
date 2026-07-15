<?php

namespace App\Http\Controllers\Procurement\Concerns;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Procurement;
use App\Models\ProcurementPlan;
use App\Models\ProcurementProgramPlan;
use App\Models\Resource;
use App\Models\FormSubmission;
use App\Models\Activity;
use App\Models\SubActivity;
use Illuminate\Support\Facades\Auth;

trait GovernanceScope
{
    use ScopesAssignedPortfolios;

    private function scopedNodeIds(): ?array
    {
        $currentUser = Auth::user();

        if (!$currentUser || $currentUser->isAdmin() || $currentUser->isSuperAdmin()) {
            return null;
        }

        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            return $this->assignedPortfolioNodeIds($currentUser);
        }

        if (!$currentUser->governance_node_id) {
            return [];
        }

        return [$currentUser->governance_node_id];
    }

    private function applyProcurementScope($query)
    {
        $currentUser = Auth::user();
        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            $nodeIds = $this->assignedPortfolioNodeIds($currentUser);

            return empty($nodeIds)
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('governance_node_id', $nodeIds)->whereNotNull('governance_node_id');
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return $query;
        }

        return $query->whereIn('governance_node_id', $scopedNodeIds)
            ->whereNotNull('governance_node_id');
    }

    private function assertProcurementInScope(Procurement $procurement): void
    {
        $currentUser = Auth::user();
        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            if (! $this->procurementIsInAssignedPortfolio($procurement, $currentUser)) {
                abort(403, 'You do not have access to this procurement.');
            }

            return;
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$procurement->governance_node_id || !in_array($procurement->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this procurement.');
        }
    }

    private function assertResourceInScope(Resource $resource): void
    {
        $currentUser = Auth::user();
        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            if (! $this->resourceIsInAssignedPortfolioNode($resource, $currentUser)) {
                abort(403, 'You do not have access to this resource.');
            }

            return;
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$resource->governance_node_id || !in_array($resource->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this resource.');
        }
    }

    private function applyGovernanceNodeScope($query, string $column = 'governance_node_id')
    {
        $scopedNodeIds = $this->scopedNodeIds();

        if ($scopedNodeIds === null) {
            return $query;
        }

        if (empty($scopedNodeIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $scopedNodeIds)
            ->whereNotNull($column);
    }

    private function assertGovernanceNodeInScope(?string $governanceNodeId, string $message = 'You do not have access to this record.'): void
    {
        $scopedNodeIds = $this->scopedNodeIds();

        if ($scopedNodeIds === null) {
            return;
        }

        if (! $governanceNodeId || ! in_array((string) $governanceNodeId, array_map('strval', $scopedNodeIds), true)) {
            abort(403, $message);
        }
    }

    private function applyProcurementPlanScope($query)
    {
        return $this->applyGovernanceNodeScope($query);
    }

    private function applyProcurementProgramPlanScope($query)
    {
        return $this->applyGovernanceNodeScope($query);
    }

    private function assertProcurementPlanInScope(ProcurementPlan $plan): void
    {
        $this->assertGovernanceNodeInScope(
            $plan->governance_node_id,
            'You do not have access to this procurement plan.'
        );
    }

    private function assertProcurementProgramPlanInScope(ProcurementProgramPlan $programPlan): void
    {
        $this->assertGovernanceNodeInScope(
            $programPlan->governance_node_id,
            'You do not have access to this procurement plan sheet.'
        );
    }

    private function assertActivityInScope(Activity $activity): void
    {
        $activity->loadMissing('project');

        $this->assertGovernanceNodeInScope(
            $activity->governance_node_id ?? $activity->project?->governance_node_id,
            'You do not have access to this activity.'
        );
    }

    private function assertSubActivityInScope(SubActivity $subActivity): void
    {
        $subActivity->loadMissing('activity.project');

        $this->assertGovernanceNodeInScope(
            $subActivity->governance_node_id
                ?? $subActivity->activity?->governance_node_id
                ?? $subActivity->activity?->project?->governance_node_id,
            'You do not have access to this sub activity.'
        );
    }

    private function assertSubmissionInScope(FormSubmission $submission): void
    {
        $procurement = $submission->procurement;
        if ($procurement) {
            $this->assertProcurementInScope($procurement);
            return;
        }

        abort(403, 'You do not have access to this submission.');
    }
}
