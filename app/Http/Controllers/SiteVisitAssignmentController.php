<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesSiteVisitsToPortfolio;
use App\Models\{SiteVisit, SiteVisitAssignment};
use Illuminate\Http\Request;

class SiteVisitAssignmentController extends Controller
{
    use ScopesSiteVisitsToPortfolio;

    public function assignIndividual(Request $request, SiteVisit $siteVisit)
    {
        $user = auth()->user();
        abort_unless($user && $user->can('site_visits.approve'), 403);
        $this->assertSiteVisitInPortfolioScope($siteVisit);

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);
        $this->assertAssignableSiteVisitUserInScope((string) $request->user_id);

        SiteVisitAssignment::updateOrCreate(
            ['site_visit_id' => $siteVisit->id],
            ['user_id' => $request->user_id]
        );

        return response()->json(['message' => 'Individual assigned']);
    }
}
