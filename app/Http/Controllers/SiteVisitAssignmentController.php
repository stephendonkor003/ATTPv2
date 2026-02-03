<?php

namespace App\Http\Controllers;

use App\Models\{SiteVisit, SiteVisitAssignment};
use Illuminate\Http\Request;

class SiteVisitAssignmentController extends Controller
{
    public function assignIndividual(Request $request, SiteVisit $siteVisit)
    {
        $user = auth()->user();
        abort_unless($user && $user->can('site_visits.approve'), 403);

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        SiteVisitAssignment::updateOrCreate(
            ['site_visit_id' => $siteVisit->id],
            ['user_id' => $request->user_id]
        );

        return response()->json(['message' => 'Individual assigned']);
    }
}
