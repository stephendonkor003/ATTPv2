<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Program;
use App\Models\ActivityAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ActivityController extends Controller
{
    /**
     * Display all activities (with project & program info)
     */
    public function index(Request $request)
{
    $search = $request->search;

    $scopedNodeIds = $this->scopedNodeIds();
    if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
        abort(403, 'You do not have access to activities.');
    }

    $programs = Program::with([
        'projects.activities.allocations' => function ($q) {
            $q->orderBy('year', 'asc');
        }
    ])
    ->when($scopedNodeIds !== null, function ($q) use ($scopedNodeIds) {
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

    return view('activities.index', compact('programs', 'search'));
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
        'project.program',
        'project.sector',
        'allocations' => function ($q) {
            $q->orderBy('year', 'asc');
        }
    ])->findOrFail($id);
    $this->assertActivityInScope($activity);

    $project = $activity->project;

    // Useful calculations for the blade
    $totalAllocation = $activity->allocations->sum('amount');
    $projectBudget   = $project->total_budget;
    $remainingBudget = $projectBudget - $totalAllocation;
    $percentageUsed  = $projectBudget > 0
                        ? ($totalAllocation / $projectBudget) * 100
                        : 0;

    return view('activities.show', compact(
        'activity',
        'project',
        'totalAllocation',
        'remainingBudget',
        'percentageUsed'
    ));
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

    private function assertProjectInScope(Project $project): void
    {
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
