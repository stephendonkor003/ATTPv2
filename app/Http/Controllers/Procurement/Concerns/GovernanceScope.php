<?php

namespace App\Http\Controllers\Procurement\Concerns;

use App\Models\Procurement;
use App\Models\Resource;
use App\Models\FormSubmission;
use Illuminate\Support\Facades\Auth;

trait GovernanceScope
{
    private function scopedNodeIds(): ?array
    {
        $currentUser = Auth::user();

        if (!$currentUser || $currentUser->isAdmin() || $currentUser->isSuperAdmin()) {
            return null;
        }

        if (!$currentUser->governance_node_id) {
            return [];
        }

        return [$currentUser->governance_node_id];
    }

    private function applyProcurementScope($query)
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return $query;
        }

        return $query->whereIn('governance_node_id', $scopedNodeIds)
            ->whereNotNull('governance_node_id');
    }

    private function assertProcurementInScope(Procurement $procurement): void
    {
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
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$resource->governance_node_id || !in_array($resource->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this resource.');
        }
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
