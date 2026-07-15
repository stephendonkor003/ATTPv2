<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesSiteVisitsToPortfolio;
use App\Models\{
    SiteVisit,
    SiteVisitObservation
};
use Illuminate\Http\Request;

class SiteVisitObservationController extends Controller
{
    use ScopesSiteVisitsToPortfolio;

    /* =========================
     | SHOW CREATE FORM
     ========================= */
    public function create(SiteVisit $siteVisit)
    {
        $user = auth()->user();

        /* =========================
         | PERMISSION CHECK
         ========================= */
        if (!$user->can('site_visits.observe')) {
            abort(403, 'You are not allowed to add observations.');
        }

        /* =========================
         | STATUS CHECK
         ========================= */
        if ($siteVisit->status !== 'draft') {
            return redirect()
                ->route('site-visits.show', $siteVisit)
                ->withErrors([
                    'status' => 'Observations can only be added while the site visit is in draft status.'
                ]);
        }

        $siteVisit->loadMissing(['submission.values', 'assignment', 'group.members']);

        if ($user->can('site_visits.approve') || $this->userHasSiteVisitPortfolioScope($user)) {
            $this->assertSiteVisitInPortfolioScope($siteVisit);
        }

        /* =========================
         | ASSIGNMENT CHECK
         ========================= */

        // Admin / approver can view visits inside their portfolio scope.
        if ($user->can('site_visits.approve')) {
            return view('site-visits.observations.create', compact('siteVisit'));
        }

        // Individual assignment
        if (
            $siteVisit->assignment_type === 'individual' &&
            $siteVisit->assignment?->user_id === $user->id
        ) {
            return view('site-visits.observations.create', compact('siteVisit'));
        }

        // Group assignment → ONLY leader can add observations
        if ($siteVisit->assignment_type === 'group') {

            $isLeader = $siteVisit->group
                ->members()
                ->where('user_id', $user->id)
                ->where('role', 'leader')
                ->exists();

            if ($isLeader) {
                return view('site-visits.observations.create', compact('siteVisit'));
            }
        }

        abort(403, 'Only the assigned user or group leader can add observations.');
    }

    /* =========================
     | STORE OBSERVATION
     ========================= */
    public function store(Request $request, SiteVisit $siteVisit)
    {
        $user = auth()->user();

        /* =========================
         | PERMISSION CHECK
         ========================= */
        if (!$user->can('site_visits.observe')) {
            abort(403, 'You are not allowed to add observations.');
        }

        /* =========================
         | STATUS CHECK
         ========================= */
        if ($siteVisit->status !== 'draft') {
            return redirect()
                ->route('site-visits.show', $siteVisit)
                ->withErrors([
                    'status' => 'Observations can only be added while the site visit is in draft status.'
                ]);
        }

        if ($user->can('site_visits.approve') || $this->userHasSiteVisitPortfolioScope($user)) {
            $this->assertSiteVisitInPortfolioScope($siteVisit);
        }

        /* =========================
         | ASSIGNMENT CHECK
         ========================= */

        // Admin / approver allowed
        if (!$user->can('site_visits.approve')) {

            // Individual assignment
            if (
                $siteVisit->assignment_type === 'individual' &&
                $siteVisit->assignment?->user_id !== $user->id
            ) {
                abort(403, 'You are not assigned to this site visit.');
            }

            // Group assignment → leader only
            if ($siteVisit->assignment_type === 'group') {
                $isLeader = $siteVisit->group
                    ->members()
                    ->where('user_id', $user->id)
                    ->where('role', 'leader')
                    ->exists();

                if (!$isLeader) {
                    abort(403, 'Only the group leader can add observations.');
                }
            }
        }

        /* =========================
         | VALIDATION
         ========================= */
        $validated = $request->validate([
            'category'        => 'required|string|max:255',
            'description'     => 'required|string',
            'severity'        => 'required|in:low,medium,high',
            'action_required' => 'nullable|boolean',
        ]);

        /* =========================
         | SAVE
         ========================= */
        $siteVisit->observations()->create($validated);

        return redirect()
            ->route('site-visits.show', $siteVisit)
            ->with('success', 'Observation added successfully.');
    }
}
