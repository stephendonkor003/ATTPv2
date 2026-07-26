<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesSiteVisitsToPortfolio;
use App\Models\SiteVisit;
use App\Models\SiteVisitGroup;
use App\Models\SiteVisitGroupMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteVisitGroupController extends Controller
{
    use ScopesSiteVisitsToPortfolio;

    public function assignGroup(Request $request, SiteVisit $siteVisit)
    {
        abort_if(
            $siteVisit->visit_type === 'biannual_monitoring',
            422,
            'Bi-Annual monitoring teams must be managed through the Bi-Annual Site Visits module.'
        );

        $user = auth()->user();
        abort_unless($user && $user->can('site_visits.approve'), 403);
        $this->assertSiteVisitInPortfolioScope($siteVisit);

        $request->validate([
            'group_name' => 'required|string',
            'leader_id' => 'required|exists:users,id',
            'members' => 'required|array',
            'members.*' => 'distinct|exists:users,id',
        ]);
        $this->assertAssignableSiteVisitUserInScope((string) $request->leader_id);
        $this->assertAssignableSiteVisitUsersInScope($request->members ?? []);

        DB::transaction(function () use ($request, $siteVisit) {
            $lockedSiteVisit = SiteVisit::query()
                ->lockForUpdate()
                ->findOrFail($siteVisit->id);

            abort_if(
                $lockedSiteVisit->group()->exists(),
                422,
                'A monitoring group has already been assigned to this site visit.'
            );

            $group = SiteVisitGroup::create([
                'site_visit_id' => $lockedSiteVisit->id,
                'group_name' => $request->group_name,
                'leader_id' => $request->leader_id,
            ]);

            // Leader
            SiteVisitGroupMember::create([
                'group_id' => $group->id,
                'user_id' => $request->leader_id,
                'role' => 'leader',
            ]);

            // Members
            foreach ($request->members as $memberId) {
                if ($memberId != $request->leader_id) {
                    SiteVisitGroupMember::create([
                        'group_id' => $group->id,
                        'user_id' => $memberId,
                        'role' => 'member',
                    ]);
                }
            }
        });

        return response()->json(['message' => 'Group assigned']);
    }
}
