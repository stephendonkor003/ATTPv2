<?php

namespace App\Http\Controllers;

use App\Models\{
    SiteVisit,
    SiteVisitAssignment,
    SiteVisitGroup,
    SiteVisitGroupMember,
    FormSubmission,
    Procurement,
    User
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteVisitController extends Controller
{
    /* =========================
     | INDEX
     ========================= */
    public function index()
{
    $user = auth()->user();

    $query = SiteVisit::with([
        'procurement',
        'submission.values',
        'group.leader',
        'assignment.user'
    ]);

    // 👑 Admin / Approver sees all
    if ($user->can('site_visits.approve')) {
        // no filter
    }
    else {
        // 👤 Assigned individual
        $query->whereHas('assignment', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })

        // 👥 Group member or leader
        ->orWhereHas('group.members', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    $siteVisits = $query
        ->orderByDesc('created_at')
        ->get();

    return view('site-visits.index', compact('siteVisits'));
}


    /* =========================
     | CREATE
     ========================= */
    public function create()
    {
        $procurements = Procurement::orderBy('title')->get();
        $submissions  = FormSubmission::with('values')->orderByDesc('submitted_at')->get();
        $users        = User::orderBy('name')->get();

        return view('site-visits.create', compact(
            'procurements',
            'submissions',
            'users'
        ));
    }

    /* =========================
     | STORE
     ========================= */
     public function store(Request $request)
{
    $validated = $request->validate([
        'procurement_id'     => 'required|exists:procurements,id',
        'form_submission_id' => 'required|exists:form_submissions,id',
        'assignment_type'    => 'required|in:individual,group',
        'visit_date'         => 'required|date',

        // Individual
        'assigned_user_id'   => 'required_if:assignment_type,individual|nullable|exists:users,id',

        // Group
        'group_name'         => 'required_if:assignment_type,group|nullable|string|max:255',
        'group_members'      => 'required_if:assignment_type,group|nullable|array|min:1',
        'group_members.*'    => 'exists:users,id',
        'group_leader_id'    => 'required_if:assignment_type,group|nullable|exists:users,id',
    ]);

    $submission = FormSubmission::findOrFail($validated['form_submission_id']);

    if ($submission->procurement_id != $validated['procurement_id']) {
        return back()
            ->withErrors(['form_submission_id' => 'Submission does not belong to selected procurement'])
            ->withInput();
    }

    if (
        $validated['assignment_type'] === 'group' &&
        !in_array($validated['group_leader_id'], $validated['group_members'])
    ) {
        return back()
            ->withErrors(['group_leader_id' => 'Group leader must be part of the group'])
            ->withInput();
    }

    DB::transaction(function () use ($validated) {

        $siteVisit = SiteVisit::create([
            'procurement_id'     => $validated['procurement_id'],
            'form_submission_id' => $validated['form_submission_id'],
            'assignment_type'    => $validated['assignment_type'],
            'visit_date'         => $validated['visit_date'],
            'status'             => 'draft',
            'created_by'         => auth()->id(),
            'assigned_by'        => auth()->id(),
        ]);

        if ($validated['assignment_type'] === 'individual') {
            SiteVisitAssignment::create([
                'site_visit_id' => $siteVisit->id,
                'user_id'       => $validated['assigned_user_id'],
            ]);
        }

        if ($validated['assignment_type'] === 'group') {
            $group = SiteVisitGroup::create([
                'site_visit_id' => $siteVisit->id,
                'group_name'    => $validated['group_name'],
                'leader_id'     => $validated['group_leader_id'],
            ]);

            foreach ($validated['group_members'] as $userId) {
                SiteVisitGroupMember::create([
                    'group_id' => $group->id,
                    'user_id'  => $userId,
                    'role'     => $userId == $validated['group_leader_id']
                        ? 'leader'
                        : 'member',
                ]);
            }
        }
    });

    return redirect()
        ->route('site-visits.index')
        ->with('success', 'Site visit created successfully.');
}


    /* =========================
     | SHOW
     ========================= */
     public function show(SiteVisit $siteVisit)
{
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

    if (
        $user->can('site_visits.approve') ||

        $siteVisit->assignment?->user_id === $user->id ||

        $siteVisit->group?->members()
            ->where('user_id', $user->id)
            ->exists()
    ) {
        return view('site-visits.show', compact('siteVisit'));
    }

    abort(403, 'You are not assigned to this site visit.');
}


    /* =========================
     | SUBMIT (LEADER)
     ========================= */
    public function submit(SiteVisit $siteVisit)
    {
        if ($siteVisit->observations()->count() === 0) {
            return back()->withErrors([
                'observations' => 'At least one observation is required before submission.'
            ]);
        }

        $siteVisit->update(['status' => 'submitted']);

        return back()->with('success', 'Site visit submitted successfully.');
    }

    /* =========================
     | APPROVE / REJECT
     ========================= */
    public function approve(Request $request, SiteVisit $siteVisit)
    {
        $request->validate([
            'status'  => 'required|in:approved,rejected',
            'remarks' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $siteVisit) {

            $siteVisit->approvals()->create([
                'reviewer_id' => auth()->id(),
                'status'      => $request->status,
                'remarks'     => $request->remarks,
            ]);

            $siteVisit->update(['status' => $request->status]);
        });

        return back()->with('success', 'Decision recorded successfully.');
    }
}
