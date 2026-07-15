<?php

namespace App\Http\Controllers\Concerns;

use App\Http\Controllers\Procurement\Concerns\GovernanceScope;
use App\Models\Sector;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait ScopesSiteVisitsToPortfolio
{
    use GovernanceScope;

    protected function applySiteVisitPortfolioScope($query)
    {
        $scopedNodeIds = $this->scopedNodeIds();

        if ($scopedNodeIds === null) {
            return $query;
        }

        if (empty($scopedNodeIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('procurement', function ($procurementQuery) use ($scopedNodeIds) {
            $procurementQuery->whereIn('governance_node_id', $scopedNodeIds)
                ->whereNotNull('governance_node_id');
        });
    }

    protected function applySubmissionPortfolioScope($query)
    {
        $scopedNodeIds = $this->scopedNodeIds();

        if ($scopedNodeIds === null) {
            return $query;
        }

        if (empty($scopedNodeIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('procurement', function ($procurementQuery) use ($scopedNodeIds) {
            $procurementQuery->whereIn('governance_node_id', $scopedNodeIds)
                ->whereNotNull('governance_node_id');
        });
    }

    protected function assertSiteVisitInPortfolioScope(SiteVisit $siteVisit): void
    {
        $scopedNodeIds = $this->scopedNodeIds();

        if ($scopedNodeIds === null) {
            return;
        }

        if (empty($scopedNodeIds)) {
            abort(403, 'You do not have access to this site visit.');
        }

        $siteVisit->loadMissing('procurement');

        abort_unless(
            $siteVisit->procurement
                && $siteVisit->procurement->governance_node_id
                && in_array((string) $siteVisit->procurement->governance_node_id, array_map('strval', $scopedNodeIds), true),
            403,
            'You do not have access to this site visit.'
        );
    }

    protected function userHasSiteVisitPortfolioScope(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user
            && ! $user->isAdmin()
            && ! $user->isSuperAdmin()
            && ($this->userHasAssignedPortfolioScope($user) || filled($user->governance_node_id));
    }

    protected function userCanAccessSiteVisitAssignment(SiteVisit $siteVisit, ?User $user = null, bool $leaderOnly = false): bool
    {
        $user ??= Auth::user();
        if (! $user) {
            return false;
        }

        $siteVisit->loadMissing(['assignment', 'group.members']);

        if (
            $siteVisit->assignment_type === 'individual'
            && (string) ($siteVisit->assignment?->user_id ?? '') === (string) $user->id
        ) {
            return true;
        }

        if ($siteVisit->assignment_type !== 'group' || ! $siteVisit->group) {
            return false;
        }

        $memberQuery = $siteVisit->group->members()->where('user_id', $user->id);

        if ($leaderOnly) {
            $memberQuery->where('role', 'leader');
        }

        return $memberQuery->exists();
    }

    protected function assertAssignableSiteVisitUserInScope(?string $userId): void
    {
        if (! $userId) {
            return;
        }

        $scopedNodeIds = $this->scopedNodeIds();

        if ($scopedNodeIds === null) {
            return;
        }

        $scopedNodeIds = array_map('strval', $scopedNodeIds);
        $assignee = User::findOrFail($userId);

        $portfolioUserIds = Sector::query()
            ->whereIn('governance_node_id', $scopedNodeIds)
            ->whereNotNull('governance_node_id')
            ->get(['portfolio_manager_user_id', 'me_manager_user_id'])
            ->flatMap(fn ($sector) => [
                $sector->portfolio_manager_user_id,
                $sector->me_manager_user_id,
            ])
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->all();

        $hasScopedNode = $assignee->governance_node_id
            && in_array((string) $assignee->governance_node_id, $scopedNodeIds, true);

        abort_unless(
            $hasScopedNode || in_array((string) $assignee->id, $portfolioUserIds, true),
            422,
            'Selected assignee is not part of your portfolio.'
        );
    }

    protected function assertAssignableSiteVisitUsersInScope(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $this->assertAssignableSiteVisitUserInScope((string) $userId);
        }
    }
}
