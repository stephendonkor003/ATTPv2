<?php

namespace App\Http\Controllers;

use App\Models\{
    SiteVisit,
    SiteVisitGroup,
    SiteVisitGroupMember
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteVisitGroupController extends Controller
{
    public function assignGroup(Request $request, SiteVisit $siteVisit)
    {
        $user = auth()->user();
        abort_unless($user && $user->can('site_visits.approve'), 403);

        $request->validate([
            'group_name' => 'required|string',
            'leader_id'  => 'required|exists:users,id',
            'members'    => 'required|array',
            'members.*'  => 'exists:users,id',
        ]);

        DB::transaction(function () use ($request, $siteVisit) {

            $group = SiteVisitGroup::create([
                'site_visit_id' => $siteVisit->id,
                'group_name'    => $request->group_name,
                'leader_id'     => $request->leader_id,
            ]);

            // Leader
            SiteVisitGroupMember::create([
                'group_id' => $group->id,
                'user_id'  => $request->leader_id,
                'role'     => 'leader',
            ]);

            // Members
            foreach ($request->members as $memberId) {
                if ($memberId != $request->leader_id) {
                    SiteVisitGroupMember::create([
                        'group_id' => $group->id,
                        'user_id'  => $memberId,
                        'role'     => 'member',
                    ]);
                }
            }
        });

        return response()->json(['message' => 'Group assigned']);
    }
}
