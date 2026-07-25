<?php

namespace App\Http\Controllers;

use App\Exceptions\ActivityReallocationException;
use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Program;
use App\Models\ActivityAllocation;
use App\Services\ActivityReallocationTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ActivityController extends Controller
{
    use ScopesAssignedPortfolios;

    /**
     * Display all activities (with project & program info)
     */
    public function index(Request $request)
{
    $search = $request->search;

    $currentUser = Auth::user();
    $isPortfolioLeader = $this->userHasAssignedPortfolioScope($currentUser);
    $scopedNodeIds = $this->scopedNodeIds();
    if (! $isPortfolioLeader && $scopedNodeIds !== null && empty($scopedNodeIds)) {
        abort(403, 'You do not have access to activities.');
    }

    $programs = Program::with([
        'sector',
        'projects.allocations',
        'projects.activities.allocations' => function ($q) {
            $q->orderBy('year', 'asc');
        },
        'projects.activities.subActivities',
    ])
    ->when($isPortfolioLeader, function ($q) use ($currentUser) {
        $this->applyAssignedPortfolioScopeToPrograms($q, $currentUser);
    })
    ->when(! $isPortfolioLeader && $scopedNodeIds !== null, function ($q) use ($scopedNodeIds) {
        $q->whereIn('governance_node_id', $scopedNodeIds)
          ->whereNotNull('governance_node_id');
    })
    ->when($search, function ($q) use ($search) {
        $q->where(function ($q2) use ($search) {
            $q2->where('name', 'like', "%$search%")
              ->orWhereHas('projects', function ($p) use ($search) {
                  $p->where('name', 'like', "%$search%")
                    ->orWhere('project_id', 'like', "%$search%");
              })
              ->orWhereHas('projects.activities', function ($a) use ($search) {
                  $a->where('name', 'like', "%$search%");
              });
        });
    })
    ->orderBy('name')
    ->get();

    $projects = $programs->flatMap->projects;
    $activities = $projects->flatMap->activities;

    $activities->each(function (Activity $activity) {
        $activity->setAttribute('allocation_total', (float) $activity->allocations->sum('amount'));
        $activity->setAttribute('sub_activities_count', $activity->subActivities->count());
    });

    $activityStats = [
        'programs' => $programs->count(),
        'projects' => $projects->count(),
        'activities' => $activities->count(),
        'sub_activities' => $activities->sum('sub_activities_count'),
        'allocation_total' => $activities->sum('allocation_total'),
    ];

    return view('activities.index', compact('programs', 'search', 'activityStats'));
}


    /**
     * Show create activity form
     */
    public function create(Project $project)
    {
        $this->assertProjectInScope($project);
        $project->load(['program', 'sector']);
        return view('activities.create', compact('project'));
    }


    /**
     * Store a new activity
     */
 public function store(Request $request)
{
    $request->validate([
        'project_id' => 'required|exists:myb_projects,id',
        'name'       => 'required|string|max:255',
        'expected_outcome_type' => 'required|in:percentage,text',
        'expected_outcome_percentage' => 'nullable|numeric|min:0|max:100',
        'expected_outcome_text' => 'nullable|string|max:2000',
    ]);

    $project = Project::findOrFail($request->project_id);
    $this->assertProjectInScope($project);

    $expectedOutcomeValue = $request->expected_outcome_type === 'percentage'
        ? (string) ($request->expected_outcome_percentage ?? '')
        : ($request->expected_outcome_text ?? '');

    if ($request->expected_outcome_type === 'percentage' && $expectedOutcomeValue === '') {
        return back()->withErrors(['expected_outcome_percentage' => 'Expected outcome percentage is required.'])->withInput();
    }

    if ($request->expected_outcome_type === 'text' && $expectedOutcomeValue === '') {
        return back()->withErrors(['expected_outcome_text' => 'Expected outcome description is required.'])->withInput();
    }

    // Create Activity
    $activity = Activity::create([
        'project_id'  => $request->project_id,
        'governance_node_id' => $project->governance_node_id,
        'name'        => $request->name,
        'description' => $request->description,
        'expected_outcome_type' => $request->expected_outcome_type,
        'expected_outcome_value' => $expectedOutcomeValue,
        'created_by'  => auth()->id(),
    ]);

    /**
     * Save Allocation Amounts Submitted from the Blade
     * The Blade sends allocations[year] => amount
     */
    if ($request->has('allocations')) {

        foreach ($request->allocations as $year => $amount) {

            ActivityAllocation::create([
                'activity_id' => $activity->id,
                'year'        => $year,
                'amount'      => $amount !== null ? floatval($amount) : 0,
            ]);
        }

    } else {

        // Fallback — should never happen with your Blade
        foreach ($project->years() as $year) {
            ActivityAllocation::create([
                'activity_id' => $activity->id,
                'year'        => $year,
                'amount'      => 0,
            ]);
        }
    }

    return redirect()->route('budget.projects.show', $project->id)
                     ->with('success', 'Activity created successfully.');
}

    /**
     * Edit Activity Allocations
     */
    public function editAllocations($id)
    {
        $activity = Activity::with([
            'allocations',
            'subActivities.allocations',
            'project.allocations',
            'project.activities.allocations',
            'project.program',
        ])->findOrFail($id);
        $this->assertActivityInScope($activity);

        return view('activities.edit_allocations', compact('activity'));
    }

    /**
     * Update Activity Allocations
     */
    public function updateAllocations(Request $request, $id)
    {
        $activity = Activity::with([
            'allocations',
            'subActivities.allocations',
            'project.allocations',
            'project.activities.allocations',
        ])->findOrFail($id);
        $this->assertActivityInScope($activity);

        return $this->saveActivityAllocationsFromRequest($request, $activity);
    }


    public function update(Request $request, $id)
{
    $activity = Activity::with([
        'allocations',
        'subActivities.allocations',
        'project.allocations',
        'project.activities.allocations',
    ])->findOrFail($id);
    $this->assertActivityInScope($activity);

    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'expected_outcome_type' => 'nullable|in:percentage,text',
        'expected_outcome_percentage' => 'nullable|numeric|min:0|max:100',
        'expected_outcome_text' => 'nullable|string|max:2000',
        'allocations' => 'nullable|array',
        'allocations.*' => 'nullable|numeric|min:0',
    ], [
        'name.required' => 'Please enter the activity name.',
        'allocations.array' => 'The allocation data was not submitted in the expected format.',
        'allocations.*.numeric' => 'Activity allocation amounts must be valid numbers.',
        'allocations.*.min' => 'Activity allocation amounts cannot be negative.',
    ]);

    if ($validator->fails()) {
        return back()
            ->withErrors($validator)
            ->withInput()
            ->with('error', 'Activity changes were not saved. Please fix the highlighted fields.');
    }

    $expectedOutcomeType = $request->input('expected_outcome_type', $activity->expected_outcome_type);
    $expectedOutcomeValue = $activity->expected_outcome_value;

    if ($request->filled('expected_outcome_type')) {
        $expectedOutcomeValue = $expectedOutcomeType === 'percentage'
            ? (string) ($request->expected_outcome_percentage ?? '')
            : ($request->expected_outcome_text ?? '');

        if ($expectedOutcomeType === 'percentage' && $expectedOutcomeValue === '') {
            return back()->withErrors(['expected_outcome_percentage' => 'Expected outcome percentage is required.'])->withInput();
        }

        if ($expectedOutcomeType === 'text' && $expectedOutcomeValue === '') {
            return back()->withErrors(['expected_outcome_text' => 'Expected outcome description is required.'])->withInput();
        }
    }

    DB::beginTransaction();

    try {
        $activity->update([
            'name'        => $request->name,
            'description' => $request->description,
            'expected_outcome_type' => $expectedOutcomeType,
            'expected_outcome_value' => $expectedOutcomeValue,
        ]);

        if ($request->has('allocations')) {
            $allocationSave = $this->persistActivityAllocationsFromRequest($request, $activity);
            if ($allocationSave) {
                DB::rollBack();
                return $allocationSave;
            }
        }

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();

        return back()
            ->withInput()
            ->with('error', 'Activity changes were not saved because the database update failed: ' . $e->getMessage());
    }

    return back()->with('success', 'Activity updated successfully.');
}


 public function show($id)
{
    $activity = Activity::with([
        'project.program.sector',
        'project.allocations',
        'project.activities.allocations',
        'subActivities.allocations',
        'allocations' => function ($q) {
            $q->orderBy('year', 'asc');
        }
    ])->findOrFail($id);
    $this->assertActivityInScope($activity);

    $project = $activity->project;
    $subActivities = $activity->subActivities;

    // Useful calculations for the blade
    $totalAllocation = (float) $activity->allocations->sum('amount');
    $projectBudget = (float) $project->total_budget;
    $projectAllocationTotal = (float) $project->allocations->sum('amount');
    $allActivitiesAllocationTotal = (float) $project->activities->sum(fn ($projectActivity) => $projectActivity->allocations->sum('amount'));
    $subActivityAllocationTotal = (float) $subActivities->sum(fn ($subActivity) => $subActivity->allocations->sum('amount'));
    $remainingBudget = $projectBudget - $totalAllocation;
    $remainingActivityAllocation = max($totalAllocation - $subActivityAllocationTotal, 0);
    $percentageUsed = $projectBudget > 0
                        ? min(100, ($totalAllocation / $projectBudget) * 100)
                        : 0;
    $subActivityUsagePercent = $totalAllocation > 0
                        ? min(100, ($subActivityAllocationTotal / $totalAllocation) * 100)
                        : 0;

    $activityStats = [
        'project_budget' => $projectBudget,
        'project_allocation_total' => $projectAllocationTotal,
        'project_activity_total' => $allActivitiesAllocationTotal,
        'activity_allocation_total' => $totalAllocation,
        'sub_activity_count' => $subActivities->count(),
        'sub_activity_allocation_total' => $subActivityAllocationTotal,
        'remaining_activity_allocation' => $remainingActivityAllocation,
        'activity_project_percent' => round($percentageUsed, 1),
        'sub_activity_usage_percent' => round($subActivityUsagePercent, 1),
    ];

    return view('activities.show', compact(
        'activity',
        'project',
        'totalAllocation',
        'remainingBudget',
        'percentageUsed',
        'activityStats'
    ));
}

    public function destroy(Request $request, Activity $activity)
    {
        if ((string) $request->input('confirmed_activity_id') !== (string) $activity->id) {
            return back()->with('error', 'Activity deletion was not confirmed.');
        }

        $this->assertActivityInScope($activity);
        $activityName = $activity->name ?: 'Unnamed activity';

        try {
            $deletedSubActivityCount = DB::transaction(function () use ($activity) {
                $lockedActivity = Activity::query()
                    ->with('project')
                    ->lockForUpdate()
                    ->findOrFail($activity->id);
                $this->assertActivityInScope($lockedActivity);

                $subActivityIds = $lockedActivity->subActivities()
                    ->lockForUpdate()
                    ->pluck('id');

                $linkedCommitmentCount = DB::table('myb_budget_commitments')
                    ->where(function ($query) use ($lockedActivity, $subActivityIds) {
                        $query->where(function ($activityQuery) use ($lockedActivity) {
                            $activityQuery
                                ->where('allocation_level', 'activity')
                                ->where('allocation_id', $lockedActivity->id);
                        });

                        if ($subActivityIds->isNotEmpty()) {
                            $query->orWhere(function ($subActivityQuery) use ($subActivityIds) {
                                $subActivityQuery
                                    ->where('allocation_level', 'sub_activity')
                                    ->whereIn('allocation_id', $subActivityIds);
                            });
                        }
                    })
                    ->count();

                $linkedPurchaseRequestCount = $subActivityIds->isEmpty()
                    ? 0
                    : DB::table('myb_purchase_requests')
                        ->whereIn('allocation_id', $subActivityIds)
                        ->count();

                if ($linkedCommitmentCount > 0 || $linkedPurchaseRequestCount > 0) {
                    $dependencies = collect([
                        $linkedCommitmentCount > 0
                            ? $linkedCommitmentCount . ' budget ' . ($linkedCommitmentCount === 1 ? 'commitment' : 'commitments')
                            : null,
                        $linkedPurchaseRequestCount > 0
                            ? $linkedPurchaseRequestCount . ' purchase ' . ($linkedPurchaseRequestCount === 1 ? 'request' : 'requests')
                            : null,
                    ])->filter()->implode(' and ');

                    throw new \DomainException(
                        "This activity cannot be deleted because it is linked to {$dependencies}. "
                        . 'Reassign or remove those records first.'
                    );
                }

                if ($subActivityIds->isNotEmpty()) {
                    DB::table('myb_sub_activity_allocations')
                        ->whereIn('sub_activity_id', $subActivityIds)
                        ->delete();

                    DB::table('program_budget_allocations')
                        ->whereIn('sub_activity_id', $subActivityIds)
                        ->delete();

                    foreach ([
                        'procurement_disbursements',
                        'procurement_invoices',
                        'procurement_purchase_orders',
                    ] as $table) {
                        DB::table($table)
                            ->whereIn('sub_activity_id', $subActivityIds)
                            ->update(['sub_activity_id' => null]);
                    }
                }

                DB::table('program_budget_allocations')
                    ->where('activity_id', $lockedActivity->id)
                    ->delete();

                $lockedActivity->subActivities()->delete();
                $lockedActivity->allocations()->delete();
                $lockedActivity->delete();

                return $subActivityIds->count();
            });
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'The activity could not be deleted. No activity or sub-activity records were removed.'
            );
        }

        $subActivityMessage = $deletedSubActivityCount === 1
            ? ' Its 1 associated sub-activity was also deleted.'
            : ($deletedSubActivityCount > 1
                ? " Its {$deletedSubActivityCount} associated sub-activities were also deleted."
                : '');

        return back()->with(
            'success',
            "Activity \"{$activityName}\" deleted successfully.{$subActivityMessage}"
        );
    }

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

    /**
     * Reallocate an activity to a different project (and thereby another program).
     * Moves the activity and updates governance node on the activity and its sub-activities.
     */
    public function reallocate(
        Request $request,
        Activity $activity,
        ActivityReallocationTracker $reallocationTracker
    )
    {
        $this->assertActivityInScope($activity);

        $request->validate([
            'project_id' => 'required|exists:myb_projects,id',
            'attempt_id' => 'nullable|uuid',
            'repair' => 'nullable|boolean',
        ]);

        $targetProject = Project::findOrFail($request->project_id);
        $this->assertProjectInScope($targetProject);
        $repairExistingMove = $request->boolean('repair') || $request->filled('attempt_id');

        if ((string) $activity->project_id === (string) $targetProject->id && ! $repairExistingMove) {
            return back()->with('error', 'Activity already assigned to the selected project.');
        }

        $attempt = null;
        try {
            $attempt = $reallocationTracker->begin(
                $activity,
                $targetProject,
                $request->input('attempt_id')
            );
        } catch (ActivityReallocationException $trackingError) {
            return back()->with('error', 'Reallocation failed: ' . $trackingError->getMessage());
        } catch (\Throwable $trackingError) {
            report($trackingError);
        }

        try {
            $result = $this->moveActivityToProject(
                $activity,
                $targetProject,
                $repairExistingMove,
                $attempt
                    ? fn (array $snapshot) => $reallocationTracker->captureSnapshot($attempt, $snapshot)
                    : null
            );
        } catch (\Throwable $e) {
            report($e);
            $message = $this->safeReallocationErrorMessage($e);

            if ($attempt) {
                try {
                    $reallocationTracker->fail($attempt, $message);
                } catch (\Throwable $trackingError) {
                    report($trackingError);
                }
            }

            return back()->with('error', 'Reallocation failed: ' . $message);
        }

        $successMessage = $result['repaired']
            ? 'The activity and all sub-activity relationships were repaired. Their '
                .number_format($result['amount'], 2)
                .' activity envelope remains attached to the current component and was not counted a second time.'
            : 'Activity and its ' . number_format($result['amount'], 2) . ' budget envelope were reallocated successfully. The programme-wide budget was unchanged.';

        if ($attempt) {
            try {
                $reallocationTracker->succeed($attempt, $successMessage);
            } catch (\Throwable $trackingError) {
                report($trackingError);
            }
        }

        return back()->with(
            'success',
            $successMessage
        );
    }

    /**
     * Restore an activity to the first project it occupied before its current
     * chain of successful reallocations.
     */
    public function revertReallocation(
        Request $request,
        Activity $activity,
        ActivityReallocationTracker $reallocationTracker
    ) {
        $this->assertActivityInScope($activity);

        $request->validate([
            'reallocation_attempt_id' => 'required|uuid',
        ]);

        try {
            $reallocation = $reallocationTracker->resolveRevertableReallocation(
                $activity,
                (string) $request->input('reallocation_attempt_id')
            );
        } catch (ActivityReallocationException $trackingError) {
            return back()->with('error', 'Revert failed: '.$trackingError->getMessage());
        }

        $originalProject = Project::find($reallocation['source_project_id']);
        if (! $originalProject) {
            return back()->with('error', 'Revert failed: the original project is no longer available.');
        }

        $this->assertProjectInScope($originalProject);

        $revertAttempt = null;
        try {
            $revertAttempt = $reallocationTracker->beginRevert(
                $activity,
                $originalProject,
                $reallocation['attempts']
            );
        } catch (ActivityReallocationException $trackingError) {
            return back()->with('error', 'Revert failed: '.$trackingError->getMessage());
        } catch (\Throwable $trackingError) {
            report($trackingError);
        }

        try {
            $result = $this->moveActivityToProject(
                $activity,
                $originalProject,
                false,
                null,
                $reallocation['restore_state']
            );
        } catch (\Throwable $e) {
            report($e);
            $message = $this->safeReallocationErrorMessage($e);

            if ($revertAttempt) {
                try {
                    $reallocationTracker->fail($revertAttempt, $message);
                } catch (\Throwable $trackingError) {
                    report($trackingError);
                }
            }

            return back()->with('error', 'Revert failed: '.$message);
        }

        $successMessage = 'The reallocation was reverted successfully. The activity, its '
            .number_format($result['amount'], 2)
            .' budget envelope, and all sub-activities are back in '
            .$originalProject->name
            .'.';

        if ($revertAttempt) {
            try {
                $reallocationTracker->succeed($revertAttempt, $successMessage);
                $reallocationTracker->markReverted($reallocation['attempts'], $revertAttempt);
            } catch (\Throwable $trackingError) {
                report($trackingError);
            }
        }

        return back()->with('success', $successMessage);
    }

    /**
     * Return an attention item to the immediately preceding component using
     * server-resolved audit history. This is a new validated move, so older
     * records without exact snapshots can still be handled safely.
     */
    public function returnToPreviousReallocation(
        Request $request,
        Activity $activity,
        ActivityReallocationTracker $reallocationTracker
    ) {
        $this->assertActivityInScope($activity);

        $request->validate([
            'previous_reallocation_attempt_id' => 'required|uuid',
        ]);

        try {
            $previousReallocation = $reallocationTracker->resolvePreviousReallocation(
                $activity,
                (string) $request->input('previous_reallocation_attempt_id')
            );
        } catch (ActivityReallocationException $trackingError) {
            return back()->with('error', 'Return failed: '.$trackingError->getMessage());
        }

        $previousProject = Project::find($previousReallocation['source_project_id']);
        if (! $previousProject) {
            return back()->with('error', 'Return failed: the previous component is no longer available.');
        }

        $this->assertProjectInScope($previousProject);

        try {
            $returnAttempt = $reallocationTracker->beginReturnToPrevious(
                $activity,
                $previousProject,
                $previousReallocation['attempt']
            );
        } catch (ActivityReallocationException $trackingError) {
            return back()->with('error', 'Return failed: '.$trackingError->getMessage());
        } catch (\Throwable $trackingError) {
            report($trackingError);

            return back()->with('error', 'Return failed: the operation could not be recorded safely.');
        }

        try {
            $result = $this->moveActivityToProject(
                $activity,
                $previousProject,
                false,
                fn (array $snapshot) => $reallocationTracker->captureSnapshot($returnAttempt, $snapshot)
            );
        } catch (\Throwable $error) {
            report($error);
            $message = $this->safeReallocationErrorMessage($error);

            try {
                $reallocationTracker->fail($returnAttempt, $message);
            } catch (\Throwable $trackingError) {
                report($trackingError);
            }

            return back()->with('error', 'Return failed: '.$message);
        }

        $successMessage = 'The activity, its '
            .number_format($result['amount'], 2)
            .' budget envelope, and all sub-activities were returned to '
            .$previousProject->name
            .'.';

        try {
            $reallocationTracker->succeed($returnAttempt, $successMessage);
            $reallocationTracker->markReverted(
                collect([$previousReallocation['attempt']]),
                $returnAttempt
            );
        } catch (\Throwable $trackingError) {
            report($trackingError);
        }

        return back()->with('success', $successMessage);
    }

    /**
     * Finish an interrupted/legacy move when the activity relationship already
     * points at the destination but its budget envelope is still at the
     * verified source component.
     */
    public function completeReallocation(
        Request $request,
        Activity $activity,
        ActivityReallocationTracker $reallocationTracker
    ) {
        $this->assertActivityInScope($activity);

        $request->validate([
            'previous_reallocation_attempt_id' => 'required|uuid',
        ]);

        try {
            $incompleteReallocation = $reallocationTracker->resolveCompletableReallocation(
                $activity,
                (string) $request->input('previous_reallocation_attempt_id')
            );
        } catch (ActivityReallocationException $trackingError) {
            return back()->with('error', 'Completion failed: '.$trackingError->getMessage());
        }

        $sourceProject = Project::find($incompleteReallocation['source_project_id']);
        $targetProject = $activity->project()->first();
        if (! $sourceProject || ! $targetProject) {
            return back()->with('error', 'Completion failed: the source or destination component is no longer available.');
        }

        if ((string) $sourceProject->id === (string) $targetProject->id) {
            return back()->with('error', 'Completion failed: the source and destination components must be different.');
        }

        $this->assertProjectInScope($sourceProject);
        $this->assertProjectInScope($targetProject);

        try {
            $completionAttempt = $reallocationTracker->beginCompleteToCurrent(
                $activity,
                $sourceProject,
                $targetProject,
                $incompleteReallocation['attempt']
            );
        } catch (ActivityReallocationException $trackingError) {
            return back()->with('error', 'Completion failed: '.$trackingError->getMessage());
        } catch (\Throwable $trackingError) {
            report($trackingError);

            return back()->with('error', 'Completion failed: the operation could not be recorded safely.');
        }

        try {
            $result = $this->moveActivityToProject(
                $activity,
                $targetProject,
                true,
                fn (array $snapshot) => $reallocationTracker->captureSnapshot($completionAttempt, $snapshot),
                null,
                $sourceProject
            );
        } catch (\Throwable $error) {
            report($error);
            $message = $this->safeReallocationErrorMessage($error);

            try {
                $reallocationTracker->fail($completionAttempt, $message);
            } catch (\Throwable $trackingError) {
                report($trackingError);
            }

            return back()->with('error', 'Completion failed: '.$message);
        }

        $successMessage = 'Reallocation completed. The activity, all of its sub-activities, and the full '
            .number_format($result['amount'], 2)
            .' budget envelope are now attached to '
            .$targetProject->name
            .'. The source component decreased and the destination component increased by the same amount.';

        try {
            $reallocationTracker->succeed($completionAttempt, $successMessage);
            $reallocationTracker->markEnvelopeCompleted(
                $incompleteReallocation['attempt'],
                $completionAttempt
            );
        } catch (\Throwable $trackingError) {
            report($trackingError);
        }

        return back()->with('success', $successMessage);
    }

    private function moveActivityToProject(
        Activity $activity,
        Project $targetProject,
        bool $repairExistingMove = false,
        ?callable $beforeMove = null,
        ?array $restoreState = null,
        ?Project $repairBudgetSource = null
    ): array {
        return DB::transaction(function () use (
            $activity,
            $targetProject,
            $repairExistingMove,
            $beforeMove,
            $restoreState,
            $repairBudgetSource
        ) {
            $activityToMove = Activity::query()
                ->lockForUpdate()
                ->findOrFail($activity->id);
            $activityToMove->setRelation(
                'allocations',
                $activityToMove->allocations()->lockForUpdate()->get()
            );
            $lockedSubActivities = $activityToMove->subActivities()
                ->lockForUpdate()
                ->get();

            $sourceProjectId = $repairBudgetSource?->id ?? $activityToMove->project_id;
            $projectIdsToLock = collect([
                $activityToMove->project_id,
                $targetProject->id,
                $sourceProjectId,
            ])->filter()->unique()->values();

            $lockedProjects = Project::query()
                ->whereIn('id', $projectIdsToLock)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Project $project) => (string) $project->id);
            $lockedSourceProject = $lockedProjects->get((string) $sourceProjectId);
            $lockedTargetProject = $lockedProjects->get((string) $targetProject->id);

            if (! $lockedSourceProject || ! $lockedTargetProject) {
                throw new ActivityReallocationException('The source or target component could not be locked for reallocation.');
            }

            $alreadyInTarget = (string) $activityToMove->project_id === (string) $lockedTargetProject->id;
            if ($alreadyInTarget && ! $repairExistingMove) {
                throw new ActivityReallocationException('Activity already assigned to the selected project.');
            }
            if (
                $repairBudgetSource
                && (
                    ! $alreadyInTarget
                    || (string) $lockedSourceProject->id === (string) $lockedTargetProject->id
                )
            ) {
                throw new ActivityReallocationException(
                    'The incomplete reallocation no longer matches its verified source and destination.'
                );
            }

            $targetYears = collect($lockedTargetProject->years())->map(fn ($year) => (int) $year);
            $outsideTargetYears = $activityToMove->allocations
                ->filter(fn ($allocation) => (float) $allocation->amount > 0)
                ->pluck('year')
                ->map(fn ($year) => (int) $year)
                ->diff($targetYears)
                ->values();

            if ($outsideTargetYears->isNotEmpty()) {
                throw new ActivityReallocationException(
                    'The target project does not cover the activity allocation year(s): '
                    .$outsideTargetYears->implode(', ')
                    .'.'
                );
            }

            if ($restoreState) {
                $this->assertRevertStateCanBeRestored(
                    $activityToMove,
                    $lockedSubActivities,
                    $lockedSourceProject,
                    $lockedTargetProject,
                    $restoreState
                );
            }

            if ($beforeMove && (! $alreadyInTarget || $repairBudgetSource)) {
                $beforeMove($this->reallocationSnapshot(
                    $activityToMove,
                    $lockedSubActivities,
                    $lockedSourceProject,
                    $lockedTargetProject
                ));
            }

            $movedAmount = round((float) $activityToMove->allocations->sum('amount'), 2);
            $envelopeTransferred = false;
            if ($alreadyInTarget && $repairBudgetSource) {
                $this->transferActivityBudgetEnvelope(
                    $lockedSourceProject,
                    $lockedTargetProject,
                    $activityToMove
                );
                $envelopeTransferred = true;
            } elseif ($alreadyInTarget) {
                // The activity amount already belongs to this component through
                // its project relationship. Relationship repair must not add it
                // again or fail because of a separate component-envelope issue.
            } else {
                $this->transferActivityBudgetEnvelope(
                    $lockedSourceProject,
                    $lockedTargetProject,
                    $activityToMove
                );
                $envelopeTransferred = true;
            }

            $originalState = (array) ($restoreState['original'] ?? []);
            $activityToMove->project_id = $lockedTargetProject->id;
            $activityToMove->governance_node_id = $restoreState
                ? ($originalState['activity_governance_node_id'] ?? null)
                : $lockedTargetProject->governance_node_id;
            $activityToMove->save();

            $originalSubActivityNodes = (array) ($originalState['sub_activity_governance_nodes'] ?? []);
            foreach ($lockedSubActivities as $subActivity) {
                $governanceNodeId = $restoreState
                    ? ($originalSubActivityNodes[(string) $subActivity->id] ?? null)
                    : $lockedTargetProject->governance_node_id;

                if ((string) $subActivity->governance_node_id !== (string) $governanceNodeId) {
                    $subActivity->update(['governance_node_id' => $governanceNodeId]);
                }
            }

            return [
                'amount' => $movedAmount,
                'repaired' => $alreadyInTarget,
                'envelope_transferred' => $envelopeTransferred,
            ];
        });
    }

    private function reallocationSnapshot(
        Activity $activity,
        $subActivities,
        Project $sourceProject,
        Project $targetProject
    ): array {
        $sourceAllocations = $sourceProject->allocations()
            ->lockForUpdate()
            ->get();
        $targetAllocations = $targetProject->allocations()
            ->lockForUpdate()
            ->get();

        return [
            'version' => 1,
            'source_project_id' => (string) $sourceProject->id,
            'target_project_id' => (string) $targetProject->id,
            'activity_governance_node_id' => $activity->governance_node_id,
            'target_governance_node_id' => $targetProject->governance_node_id,
            'activity_allocation_by_year' => $this->allocationAmountsByYear($activity->allocations),
            'sub_activity_ids' => $subActivities
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->sort()
                ->values()
                ->all(),
            'sub_activity_governance_nodes' => $subActivities
                ->mapWithKeys(fn ($subActivity) => [(string) $subActivity->id => $subActivity->governance_node_id])
                ->all(),
            'budget_envelope_before' => [
                'source_total_budget' => round((float) $sourceProject->total_budget, 2),
                'target_total_budget' => round((float) $targetProject->total_budget, 2),
                'source_allocation_by_year' => $this->allocationAmountsByYear($sourceAllocations),
                'target_allocation_by_year' => $this->allocationAmountsByYear($targetAllocations),
            ],
        ];
    }

    private function assertRevertStateCanBeRestored(
        Activity $activity,
        $subActivities,
        Project $currentProject,
        Project $originalProject,
        array $restoreState
    ): void {
        $original = (array) ($restoreState['original'] ?? []);
        $current = (array) ($restoreState['current'] ?? []);

        if ($original === [] || $current === []) {
            throw new ActivityReallocationException('The original reallocation snapshot is incomplete.');
        }

        if (
            (string) ($current['target_project_id'] ?? '') !== (string) $currentProject->id
            || (string) ($original['source_project_id'] ?? '') !== (string) $originalProject->id
        ) {
            throw new ActivityReallocationException('The activity no longer matches the recorded reallocation route.');
        }

        if ($this->allocationAmountsByYear($activity->allocations) !== $this->normaliseAllocationAmounts(
            (array) ($current['activity_allocation_by_year'] ?? [])
        )) {
            throw new ActivityReallocationException(
                'The activity allocation has changed since it was reallocated and cannot be reverted safely.'
            );
        }

        $currentSubActivityIds = $subActivities
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->sort()
            ->values()
            ->all();
        $originalSubActivityIds = collect((array) ($original['sub_activity_ids'] ?? []))
            ->map(fn ($id) => (string) $id)
            ->sort()
            ->values()
            ->all();
        $latestSubActivityIds = collect((array) ($current['sub_activity_ids'] ?? []))
            ->map(fn ($id) => (string) $id)
            ->sort()
            ->values()
            ->all();

        if ($currentSubActivityIds !== $originalSubActivityIds || $currentSubActivityIds !== $latestSubActivityIds) {
            throw new ActivityReallocationException(
                'The activity sub-activities have changed since it was reallocated and cannot be reverted safely.'
            );
        }

        if (! array_key_exists('activity_governance_node_id', $original)) {
            throw new ActivityReallocationException('The original activity governance assignment is unavailable.');
        }

        if ((string) $activity->governance_node_id !== (string) ($current['target_governance_node_id'] ?? '')) {
            throw new ActivityReallocationException(
                'The activity governance assignment has changed since it was reallocated and cannot be reverted safely.'
            );
        }

        $originalSubActivityNodes = (array) ($original['sub_activity_governance_nodes'] ?? []);
        foreach ($subActivities as $subActivity) {
            if (! array_key_exists((string) $subActivity->id, $originalSubActivityNodes)) {
                throw new ActivityReallocationException('The original sub-activity governance assignment is unavailable.');
            }

            if ((string) $subActivity->governance_node_id !== (string) ($current['target_governance_node_id'] ?? '')) {
                throw new ActivityReallocationException(
                    'A sub-activity governance assignment has changed since reallocation and cannot be reverted safely.'
                );
            }
        }
    }

    private function allocationAmountsByYear($allocations): array
    {
        return collect($allocations)
            ->groupBy(fn ($allocation) => (int) $allocation->year)
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))
            ->sortKeys()
            ->mapWithKeys(fn ($amount, $year) => [(string) $year => $amount])
            ->all();
    }

    private function normaliseAllocationAmounts(array $allocations): array
    {
        return collect($allocations)
            ->mapWithKeys(fn ($amount, $year) => [(string) $year => round((float) $amount, 2)])
            ->sortKeys()
            ->all();
    }

    private function rebalanceTargetProjectYearlyAllocations(Project $targetProject, Activity $incomingActivity): void
    {
        $years = collect($targetProject->years())->map(fn ($year) => (int) $year)->values();
        $allocationModels = $targetProject->allocations()
            ->whereIn('year', $years)
            ->lockForUpdate()
            ->get()
            ->keyBy(fn ($allocation) => (int) $allocation->year);

        $missingYears = $years->reject(fn (int $year) => $allocationModels->has($year));
        if ($missingYears->isNotEmpty()) {
            throw new ActivityReallocationException(
                'The target component is missing yearly budget row(s) for: ' . $missingYears->implode(', ') . '.'
            );
        }

        $current = $years->mapWithKeys(fn (int $year) => [
            $year => round((float) optional($allocationModels->get($year))->amount, 2),
        ])->all();
        $required = $years->mapWithKeys(fn (int $year) => [$year => 0.0])->all();

        $targetProject->load('activities.allocations');
        foreach ($targetProject->activities as $activity) {
            foreach ($activity->allocations as $allocation) {
                $year = (int) $allocation->year;
                if (array_key_exists($year, $required)) {
                    $required[$year] += (float) $allocation->amount;
                }
            }
        }

        $incomingAlreadyIncluded = (string) $incomingActivity->project_id === (string) $targetProject->id;
        if (! $incomingAlreadyIncluded) {
            foreach ($incomingActivity->allocations as $allocation) {
                $year = (int) $allocation->year;
                if (array_key_exists($year, $required)) {
                    $required[$year] += (float) $allocation->amount;
                }
            }
        }

        $plan = $this->rebalanceAllocationPlan($current, $required);
        foreach ($plan as $year => $amount) {
            $allocation = $allocationModels->get((int) $year);
            if (! $allocation) {
                throw new ActivityReallocationException("The target project has no allocation row for {$year}.");
            }

            if (abs((float) $allocation->amount - $amount) >= 0.01) {
                $allocation->update(['amount' => $amount]);
            }
        }
    }

    private function transferActivityBudgetEnvelope(
        Project $sourceProject,
        Project $targetProject,
        Activity $activity
    ): float {
        $activityYearly = $activity->allocations
            ->groupBy(fn ($allocation) => (int) $allocation->year)
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))
            ->all();

        $sourceAllocations = $sourceProject->allocations()
            ->lockForUpdate()
            ->get()
            ->keyBy(fn ($allocation) => (int) $allocation->year);
        $targetAllocations = $targetProject->allocations()
            ->lockForUpdate()
            ->get()
            ->keyBy(fn ($allocation) => (int) $allocation->year);

        $sourceYearly = $sourceAllocations
            ->mapWithKeys(fn ($allocation, $year) => [(int) $year => round((float) $allocation->amount, 2)])
            ->all();
        $targetYearly = $targetAllocations
            ->mapWithKeys(fn ($allocation, $year) => [(int) $year => round((float) $allocation->amount, 2)])
            ->all();

        $plan = $this->budgetEnvelopeTransferPlan(
            (float) $sourceProject->total_budget,
            (float) $targetProject->total_budget,
            $sourceYearly,
            $targetYearly,
            $activityYearly
        );

        foreach ($plan['source_yearly'] as $year => $amount) {
            if (isset($activityYearly[$year]) && $sourceAllocations->has((int) $year)) {
                $sourceAllocations->get((int) $year)->update(['amount' => $amount]);
            }
        }

        foreach ($plan['target_yearly'] as $year => $amount) {
            if (isset($activityYearly[$year]) && $targetAllocations->has((int) $year)) {
                $targetAllocations->get((int) $year)->update(['amount' => $amount]);
            }
        }

        $sourceProject->update(['total_budget' => $plan['source_total']]);
        $targetProject->update(['total_budget' => $plan['target_total']]);

        return $plan['moved_amount'];
    }

    private function budgetEnvelopeTransferPlan(
        float $sourceTotal,
        float $targetTotal,
        array $sourceYearly,
        array $targetYearly,
        array $activityYearly
    ): array {
        $sourceYearly = collect($sourceYearly)
            ->mapWithKeys(fn ($amount, $year) => [(int) $year => round((float) $amount, 2)])
            ->all();
        $targetYearly = collect($targetYearly)
            ->mapWithKeys(fn ($amount, $year) => [(int) $year => round((float) $amount, 2)])
            ->all();
        $activityYearly = collect($activityYearly)
            ->mapWithKeys(fn ($amount, $year) => [(int) $year => round((float) $amount, 2)])
            ->all();

        $movedAmount = round(array_sum($activityYearly), 2);
        if ($movedAmount < 0) {
            throw new ActivityReallocationException('The activity budget cannot be negative.');
        }
        if ($sourceTotal + 0.01 < $movedAmount) {
            throw new ActivityReallocationException(
                'The source component budget is lower than the activity budget being moved.'
            );
        }

        foreach ($activityYearly as $year => $amount) {
            if ($amount < 0) {
                throw new ActivityReallocationException("The activity allocation for {$year} cannot be negative.");
            }
            if ($amount <= 0) {
                continue;
            }
            if (! array_key_exists($year, $sourceYearly)) {
                throw new ActivityReallocationException("The source component has no yearly budget row for {$year}.");
            }
            if (! array_key_exists($year, $targetYearly)) {
                throw new ActivityReallocationException("The target component has no yearly budget row for {$year}.");
            }
            if ($sourceYearly[$year] + 0.01 < $amount) {
                throw new ActivityReallocationException(
                    "The source component does not have enough budget in {$year} to move this activity."
                );
            }

            $sourceYearly[$year] = round($sourceYearly[$year] - $amount, 2);
            $targetYearly[$year] = round($targetYearly[$year] + $amount, 2);
        }

        return [
            'moved_amount' => $movedAmount,
            'source_total' => round($sourceTotal - $movedAmount, 2),
            'target_total' => round($targetTotal + $movedAmount, 2),
            'source_yearly' => $sourceYearly,
            'target_yearly' => $targetYearly,
        ];
    }

    private function rebalanceAllocationPlan(array $current, array $required): array
    {
        $current = collect($current)
            ->mapWithKeys(fn ($amount, $year) => [(int) $year => round((float) $amount, 2)])
            ->sortKeys()
            ->all();
        $required = collect($required)
            ->mapWithKeys(fn ($amount, $year) => [(int) $year => round((float) $amount, 2)])
            ->sortKeys()
            ->all();

        $availableTotal = round(array_sum($current), 2);
        $requiredTotal = round(array_sum($required), 2);
        if ($requiredTotal > $availableTotal + 0.01) {
            throw new ActivityReallocationException(
                'The target project does not have enough available budget. '
                . 'Available: ' . number_format($availableTotal, 2)
                . '; required after reallocation: ' . number_format($requiredTotal, 2)
                . '.'
            );
        }

        $plan = $current;
        $deficits = [];
        $surpluses = [];

        foreach ($required as $year => $requiredAmount) {
            $difference = round(($current[$year] ?? 0) - $requiredAmount, 2);
            if ($difference < 0) {
                $deficits[$year] = abs($difference);
            } elseif ($difference > 0) {
                $surpluses[$year] = $difference;
            }
        }

        krsort($surpluses);
        foreach ($deficits as $deficitYear => $needed) {
            foreach ($surpluses as $surplusYear => $available) {
                if ($needed <= 0.001) {
                    break;
                }

                $moved = round(min($needed, $available), 2);
                if ($moved <= 0) {
                    continue;
                }

                $plan[$deficitYear] = round(($plan[$deficitYear] ?? 0) + $moved, 2);
                $plan[$surplusYear] = round(($plan[$surplusYear] ?? 0) - $moved, 2);
                $needed = round($needed - $moved, 2);
                $surpluses[$surplusYear] = round($available - $moved, 2);
            }

            if ($needed > 0.01) {
                throw new ActivityReallocationException('Unable to rebalance the target project yearly budget.');
            }
        }

        ksort($plan);

        return $plan;
    }

    private function safeReallocationErrorMessage(\Throwable $error): string
    {
        if ($error instanceof ActivityReallocationException) {
            return $error->getMessage();
        }

        return 'The move could not be completed. The activity remains in its previous component and the attempt has been saved for retry.';
    }

    private function assertProjectInScope(Project $project): void
    {
        $currentUser = Auth::user();
        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            if (! $this->projectIsInAssignedPortfolio($project, $currentUser)) {
                abort(403, 'You do not have access to this project.');
            }

            return;
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$project->governance_node_id || !in_array($project->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this project.');
        }
    }

    private function assertActivityInScope(Activity $activity): void
    {
        $currentUser = Auth::user();
        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            if (! $this->activityIsInAssignedPortfolio($activity, $currentUser)) {
                abort(403, 'You do not have access to this activity.');
            }

            return;
        }

        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        $nodeId = $activity->governance_node_id ?? $activity->project?->governance_node_id;
        if (!$nodeId || !in_array($nodeId, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this activity.');
        }
    }

    private function saveActivityAllocationsFromRequest(Request $request, Activity $activity)
    {
        $validator = Validator::make($request->all(), [
            'allocations' => 'required|array|min:1',
            'allocations.*' => 'required|numeric|min:0',
        ], [
            'allocations.required' => 'No allocation rows were submitted. Please enter the yearly activity amounts and try again.',
            'allocations.array' => 'The allocation data was not submitted in the expected format.',
            'allocations.min' => 'Please provide at least one yearly activity allocation.',
            'allocations.*.required' => 'Every activity year needs an allocation amount. Use 0 where there is no budget.',
            'allocations.*.numeric' => 'Activity allocation amounts must be valid numbers.',
            'allocations.*.min' => 'Activity allocation amounts cannot be negative.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Activity allocations were not saved. Please fix the highlighted yearly amounts.');
        }

        DB::beginTransaction();

        try {
            $allocationSave = $this->persistActivityAllocationsFromRequest($request, $activity);
            if ($allocationSave) {
                DB::rollBack();
                return $allocationSave;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()
                ->withInput()
                ->with('error', 'Activity allocations were not saved because the database update failed: ' . $e->getMessage());
        }

        return back()->with('success', 'Activity allocations updated successfully.');
    }

    private function persistActivityAllocationsFromRequest(Request $request, Activity $activity)
    {
        $allocations = $this->normalizeActivityAllocations((array) $request->input('allocations', []));
        $projectYears = collect($activity->project?->years() ?? [])->map(fn ($year) => (int) $year)->all();

        if (empty($projectYears)) {
            return back()
                ->withInput()
                ->with('error', 'Activity allocations were not saved because the parent project has no years configured.');
        }

        $unexpectedYears = array_diff(array_keys($allocations), $projectYears);
        if (!empty($unexpectedYears)) {
            return back()
                ->withInput()
                ->with('error', 'Activity allocations were not saved because these years do not belong to the parent project: ' . implode(', ', $unexpectedYears) . '.');
        }

        foreach ($projectYears as $year) {
            $allocations[$year] = $allocations[$year] ?? 0.0;
        }

        ksort($allocations);

        $projectAllocationTotal = round((float) $activity->project->allocations->sum('amount'), 2);
        $otherActivitiesTotal = round((float) $activity->project->activities
            ->where('id', '!=', $activity->id)
            ->sum(fn ($otherActivity) => $otherActivity->allocations->sum('amount')), 2);
        $newActivityTotal = round(array_sum($allocations), 2);
        $combinedActivityTotal = round($otherActivitiesTotal + $newActivityTotal, 2);

        if ($combinedActivityTotal > $projectAllocationTotal) {
            return back()
                ->withInput()
                ->with('error', 'Activity allocations were not saved because the combined activity total (' . number_format($combinedActivityTotal, 2) . ') exceeds the parent project allocation (' . number_format($projectAllocationTotal, 2) . '). Reduce this activity by at least ' . number_format($combinedActivityTotal - $projectAllocationTotal, 2) . ' or increase the project allocation first.');
        }

        $projectAllocationsByYear = $activity->project->allocations->keyBy(fn ($allocation) => (int) $allocation->year);
        foreach ($allocations as $year => $amount) {
            $projectYearBudget = round((float) optional($projectAllocationsByYear->get($year))->amount, 2);
            $otherActivitiesYearTotal = round((float) $activity->project->activities
                ->where('id', '!=', $activity->id)
                ->sum(function ($otherActivity) use ($year) {
                    return $otherActivity->allocations
                        ->where('year', $year)
                        ->sum('amount');
                }), 2);
            $combinedYearTotal = round($otherActivitiesYearTotal + (float) $amount, 2);

            if ($combinedYearTotal > $projectYearBudget) {
                return back()
                    ->withInput()
                    ->with('error', 'Activity allocations were not saved because year ' . $year . ' would exceed the parent project allocation. The project has ' . number_format($projectYearBudget, 2) . ' for that year, while activities would total ' . number_format($combinedYearTotal, 2) . '.');
            }
        }

        foreach ($allocations as $year => $amount) {
            $subActivityYearTotal = round((float) $activity->subActivities->sum(function ($subActivity) use ($year) {
                return $subActivity->allocations
                    ->where('year', $year)
                    ->sum('amount');
            }), 2);

            if ($subActivityYearTotal > round((float) $amount, 2)) {
                return back()
                    ->withInput()
                    ->with('error', 'Activity allocations were not saved because year ' . $year . ' already has sub-activity allocations totaling ' . number_format($subActivityYearTotal, 2) . ', but the activity allocation would be ' . number_format((float) $amount, 2) . '. Increase the activity allocation for that year or reduce the sub-activity allocations first.');
            }
        }

        ActivityAllocation::where('activity_id', $activity->id)
            ->whereNotIn('year', array_keys($allocations))
            ->delete();

        foreach ($allocations as $year => $amount) {
            ActivityAllocation::updateOrCreate(
                [
                    'activity_id' => $activity->id,
                    'year' => $year,
                ],
                [
                    'amount' => $amount,
                ]
            );
        }

        return null;
    }

    private function normalizeActivityAllocations(array $allocations): array
    {
        $normalized = [];

        foreach ($allocations as $year => $amount) {
            $normalized[(int) $year] = round((float) $amount, 2);
        }

        return $normalized;
    }


}
