<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesSiteVisitsToPortfolio;
use App\Models\{
    FormSubmission,
    Procurement,
    Sector,
    SiteVisit,
    SiteVisitAssignment,
    SiteVisitGroup,
    SiteVisitGroupMember,
    User
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SiteVisitController extends Controller
{
    use ScopesSiteVisitsToPortfolio;

    public function index()
    {
        $user = auth()->user();

        $query = SiteVisit::with([
            'procurement',
            'submission.values',
            'group.leader',
            'assignment.user',
        ])->where(function ($typeQuery) {
            $typeQuery->whereNull('visit_type')
                ->orWhere('visit_type', '!=', 'biannual_monitoring');
        });

        if ($user->can('site_visits.approve') || $this->userHasSiteVisitPortfolioScope($user)) {
            $this->applySiteVisitPortfolioScope($query);
        } else {
            $query->where(function ($assignmentScope) use ($user) {
                $assignmentScope
                    ->whereHas('assignment', function ($assignmentQuery) use ($user) {
                        $assignmentQuery->where('user_id', $user->id);
                    })
                    ->orWhereHas('group.members', function ($memberQuery) use ($user) {
                        $memberQuery->where('user_id', $user->id);
                    });
            });
        }

        $siteVisits = $query
            ->orderByDesc('created_at')
            ->get();

        return view('site-visits.index', compact('siteVisits'));
    }

    public function create()
    {
        $procurementsQuery = Procurement::query()->orderBy('title');
        $this->applyProcurementScope($procurementsQuery);
        $procurements = $procurementsQuery->get();

        $submissions = $this->applySubmissionPortfolioScope(
            FormSubmission::with(['values', 'procurement'])
        )
            ->orderByDesc('submitted_at')
            ->get();

        $usersQuery = User::query()
            ->where(function ($query) {
                $query->whereNull('is_disabled')
                    ->orWhere('is_disabled', false);
            })
            ->orderBy('name');

        $this->applyAssignableUserScope($usersQuery);
        $users = $usersQuery->get();

        return view('site-visits.create', compact(
            'procurements',
            'submissions',
            'users'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'procurement_id' => [
                'required',
                Rule::exists('procurements', 'id')->whereNull('deleted_at'),
            ],
            'form_submission_id' => 'required|exists:form_submissions,id',
            'assignment_type' => 'required|in:individual,group',
            'visit_date' => 'required|date',
            'assigned_user_id' => 'required_if:assignment_type,individual|nullable|exists:users,id',
            'group_name' => 'required_if:assignment_type,group|nullable|string|max:255',
            'group_members' => 'required_if:assignment_type,group|nullable|array|min:1',
            'group_members.*' => 'exists:users,id',
            'group_leader_id' => 'required_if:assignment_type,group|nullable|exists:users,id',
        ]);

        $procurement = Procurement::findOrFail($validated['procurement_id']);
        $this->assertProcurementInScope($procurement);

        $submission = FormSubmission::with('procurement')->findOrFail($validated['form_submission_id']);
        $this->assertSubmissionInScope($submission);

        if ((string) $submission->procurement_id !== (string) $procurement->id) {
            return back()
                ->withErrors(['form_submission_id' => 'Submission does not belong to the selected procurement.'])
                ->withInput();
        }

        if ($validated['assignment_type'] === 'individual') {
            $this->assertAssignableSiteVisitUserInScope($validated['assigned_user_id'] ?? null);
        }

        if ($validated['assignment_type'] === 'group') {
            if (! in_array($validated['group_leader_id'], $validated['group_members'], true)) {
                return back()
                    ->withErrors(['group_leader_id' => 'Group leader must be part of the group.'])
                    ->withInput();
            }

            $this->assertAssignableSiteVisitUsersInScope($validated['group_members']);
        }

        DB::transaction(function () use ($validated) {
            $siteVisit = SiteVisit::create([
                'procurement_id' => $validated['procurement_id'],
                'form_submission_id' => $validated['form_submission_id'],
                'assignment_type' => $validated['assignment_type'],
                'visit_type' => 'procurement',
                'visit_date' => $validated['visit_date'],
                'status' => 'draft',
                'created_by' => auth()->id(),
                'assigned_by' => auth()->id(),
            ]);

            if ($validated['assignment_type'] === 'individual') {
                SiteVisitAssignment::create([
                    'site_visit_id' => $siteVisit->id,
                    'user_id' => $validated['assigned_user_id'],
                ]);
            }

            if ($validated['assignment_type'] === 'group') {
                $group = SiteVisitGroup::create([
                    'site_visit_id' => $siteVisit->id,
                    'group_name' => $validated['group_name'],
                    'leader_id' => $validated['group_leader_id'],
                ]);

                foreach ($validated['group_members'] as $userId) {
                    SiteVisitGroupMember::create([
                        'group_id' => $group->id,
                        'user_id' => $userId,
                        'role' => (string) $userId === (string) $validated['group_leader_id'] ? 'leader' : 'member',
                    ]);
                }
            }
        });

        return redirect()
            ->route('site-visits.index')
            ->with('success', 'Site visit created successfully.');
    }

    public function show(SiteVisit $siteVisit)
    {
        abort_if($siteVisit->visit_type === 'biannual_monitoring', 404);

        $user = auth()->user();

        $siteVisit->loadMissing([
            'procurement',
            'submission.values',
            'assignment.user',
            'group.leader',
            'group.members.user',
            'observations.media',
            'approvals.reviewer',
        ]);

        if ($user->can('site_visits.approve') || $this->userHasSiteVisitPortfolioScope($user)) {
            $this->assertSiteVisitInPortfolioScope($siteVisit);

            return view('site-visits.show', compact('siteVisit'));
        }

        if ($this->userCanAccessSiteVisitAssignment($siteVisit, $user)) {
            return view('site-visits.show', compact('siteVisit'));
        }

        abort(403, 'You are not assigned to this site visit.');
    }

    public function submit(SiteVisit $siteVisit)
    {
        $this->assertSiteVisitActionAccess($siteVisit, auth()->user());

        if ($siteVisit->observations()->count() === 0) {
            return back()->withErrors([
                'observations' => 'At least one observation is required before submission.',
            ]);
        }

        $siteVisit->update(['status' => 'submitted']);

        return back()->with('success', 'Site visit submitted successfully.');
    }

    public function approve(Request $request, SiteVisit $siteVisit)
    {
        $this->assertSiteVisitInPortfolioScope($siteVisit);

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $siteVisit) {
            $siteVisit->approvals()->create([
                'reviewer_id' => auth()->id(),
                'status' => $request->status,
                'remarks' => $request->remarks,
            ]);

            $siteVisit->update(['status' => $request->status]);
        });

        return back()->with('success', 'Decision recorded successfully.');
    }

    private function assertSiteVisitActionAccess(SiteVisit $siteVisit, ?User $user): void
    {
        abort_unless($user, 403);

        if ($user->can('site_visits.approve') || $this->userHasSiteVisitPortfolioScope($user)) {
            $this->assertSiteVisitInPortfolioScope($siteVisit);
            return;
        }

        abort_unless(
            $this->userCanAccessSiteVisitAssignment($siteVisit, $user),
            403,
            'You are not assigned to this site visit.'
        );
    }

    private function applyAssignableUserScope($query): void
    {
        $scopedNodeIds = $this->scopedNodeIds();

        if ($scopedNodeIds === null) {
            return;
        }

        if (empty($scopedNodeIds)) {
            $query->whereRaw('1 = 0');
            return;
        }

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

        $query->where(function ($scope) use ($scopedNodeIds, $portfolioUserIds) {
            $scope->where(function ($nodeScope) use ($scopedNodeIds) {
                $nodeScope->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            });

            if (! empty($portfolioUserIds)) {
                $scope->orWhereIn('id', $portfolioUserIds);
            }
        });
    }

}
