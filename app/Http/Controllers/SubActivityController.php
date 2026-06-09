<?php

 namespace App\Http\Controllers;

use App\Models\SubActivity;
use App\Models\Activity;
use App\Models\SubActivityAllocation;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SubActivityController extends Controller
{

 public function index(Request $request)
{
    $search = $request->search;

    $scopedNodeIds = $this->scopedNodeIds();
    if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
        abort(403, 'You do not have access to sub-activities.');
    }

    $programs = Program::with([
        'projects.activities.subActivities.allocations' => function ($q) {
            $q->orderBy('year', 'asc');
        },
        'projects.activities.allocations'
    ])
    ->when($scopedNodeIds !== null, function ($q) use ($scopedNodeIds) {
        $q->whereIn('governance_node_id', $scopedNodeIds)
          ->whereNotNull('governance_node_id');
    })
    ->when($search, function ($q) use ($search) {
        $q->where(function ($q2) use ($search) {
            $q2->where('name', 'like', "%$search%")
              ->orWhereHas('projects', function ($p) use ($search) {
                  $p->where('name', 'like', "%$search%");
              })
              ->orWhereHas('projects.activities', function ($a) use ($search) {
                  $a->where('name', 'like', "%$search%");
              })
              ->orWhereHas('projects.activities.subActivities', function ($s) use ($search) {
                  $s->where('name', 'like', "%$search%");
              });
        });
    })
    ->orderBy('name')
    ->get();

    return view('subactivities.index', compact('programs', 'search'));
}


public function create(Activity $activity)
{
    $this->assertActivityInScope($activity);
    $activity->load('project.program', 'allocations');

    return view('subactivities.create', compact('activity'));
}

public function show($id)
{
    $subActivity = SubActivity::findOrFail($id);
    $this->assertSubActivityInScope($subActivity);

    return redirect()->route('budget.subactivities.edit', $subActivity->id);
}



    public function store(Request $request)
{
    $request->validate([
        'activity_id' => 'required|exists:myb_activities,id',
        'name' => 'required|string|max:255',
        'expected_outcome_type' => 'required|in:percentage,text',
        'expected_outcome_percentage' => 'nullable|numeric|min:0|max:100',
        'expected_outcome_text' => 'nullable|string|max:2000',
    ]);

    $activity = Activity::findOrFail($request->activity_id);
    $this->assertActivityInScope($activity);

    $expectedOutcomeValue = $request->expected_outcome_type === 'percentage'
        ? (string) ($request->expected_outcome_percentage ?? '')
        : ($request->expected_outcome_text ?? '');

    if ($request->expected_outcome_type === 'percentage' && $expectedOutcomeValue === '') {
        return back()->withErrors(['expected_outcome_percentage' => 'Expected outcome percentage is required.'])->withInput();
    }

    if ($request->expected_outcome_type === 'text' && $expectedOutcomeValue === '') {
        return back()->withErrors(['expected_outcome_text' => 'Expected outcome description is required.'])->withInput();
    }

    // Create Sub-Activity
    $sub = SubActivity::create([
        'activity_id' => $request->activity_id,
        'governance_node_id' => $activity->governance_node_id,
        'name' => $request->name,
        'description' => $request->description,
        'expected_outcome_type' => $request->expected_outcome_type,
        'expected_outcome_value' => $expectedOutcomeValue,
        'created_by' => auth()->id(),
    ]);

    // Save allocations from the form
    foreach ($request->allocations as $year => $amount) {
        SubActivityAllocation::create([
            'sub_activity_id' => $sub->id,
            'year' => $year,
            'amount' => $amount ?? 0,
        ]);
    }

    // return redirect()
    //     ->route('activities.show', $activity->id)
    //     ->with('success', 'Sub-Activity created successfully.');
    return redirect()
    ->route('budget.activities.show', $activity->id)
    ->with('success', 'Sub-Activity created successfully.');

}

public function edit($id)
{
    $subActivity = SubActivity::with([
        'allocations',
        'activity.allocations',
        'activity.subActivities.allocations',
        'activity.project.program',
    ])->findOrFail($id);
    $this->assertSubActivityInScope($subActivity);

    return view('budget.subactivities.edit', compact('subActivity'));
}

public function update(Request $request, $id)
{
    $subActivity = SubActivity::with([
        'allocations',
        'activity.allocations',
        'activity.subActivities.allocations',
        'activity.project.program',
    ])->findOrFail($id);
    $this->assertSubActivityInScope($subActivity);

    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'expected_outcome_type' => 'required|in:percentage,text',
        'expected_outcome_percentage' => 'nullable|numeric|min:0|max:100',
        'expected_outcome_text' => 'nullable|string|max:2000',
        'allocations' => 'nullable|array',
        'allocations.*' => 'nullable|numeric|min:0',
    ], [
        'name.required' => 'Please enter the sub-activity name.',
        'expected_outcome_type.required' => 'Please select the expected outcome type.',
        'allocations.array' => 'The allocation data was not submitted in the expected format.',
        'allocations.*.numeric' => 'Sub-activity allocation amounts must be valid numbers.',
        'allocations.*.min' => 'Sub-activity allocation amounts cannot be negative.',
    ]);

    if ($validator->fails()) {
        return back()
            ->withErrors($validator)
            ->withInput()
            ->with('error', 'Sub-activity changes were not saved. Please fix the highlighted fields.');
    }

    $expectedOutcomeValue = $request->expected_outcome_type === 'percentage'
        ? (string) ($request->expected_outcome_percentage ?? '')
        : ($request->expected_outcome_text ?? '');

    if ($request->expected_outcome_type === 'percentage' && $expectedOutcomeValue === '') {
        return back()->withErrors(['expected_outcome_percentage' => 'Expected outcome percentage is required.'])->withInput();
    }

    if ($request->expected_outcome_type === 'text' && $expectedOutcomeValue === '') {
        return back()->withErrors(['expected_outcome_text' => 'Expected outcome description is required.'])->withInput();
    }

    DB::beginTransaction();

    try {
        $subActivity->update([
            'name' => $request->name,
            'description' => $request->description,
            'expected_outcome_type' => $request->expected_outcome_type,
            'expected_outcome_value' => $expectedOutcomeValue,
        ]);

        if ($request->has('allocations')) {
            $allocations = $this->normalizeAllocations((array) $request->input('allocations', []));
            $error = $this->validateSubActivityAllocations($subActivity, $allocations);
            if ($error) {
                DB::rollBack();
                return back()->withInput()->with('error', $error);
            }

            $this->persistSubActivityAllocations($subActivity, $allocations);
        }

        DB::commit();
    } catch (\Throwable $e) {
        DB::rollBack();

        return back()
            ->withInput()
            ->with('error', 'Sub-activity changes were not saved because the database update failed: ' . $e->getMessage());
    }

    return back()->with('success', 'Sub-activity updated successfully.');
}


    public function editAllocations($id)
{
    $sub = SubActivity::with([
        'allocations',
        'activity.allocations',
        'activity.subActivities.allocations',
        'activity.project.program',
    ])->findOrFail($id);
    $this->assertSubActivityInScope($sub);
    return view('subactivities.edit_allocations', compact('sub'));
}

public function updateAllocations(Request $request, $id)
{
    $sub = SubActivity::with([
        'allocations',
        'activity.allocations',
        'activity.subActivities.allocations',
        'activity.project',
    ])->findOrFail($id);
    $this->assertSubActivityInScope($sub);

    $validator = Validator::make($request->all(), [
        'allocations' => 'required|array|min:1',
        'allocations.*' => 'required|numeric|min:0',
    ], [
        'allocations.required' => 'No allocation rows were submitted. Please enter the yearly amounts and try again.',
        'allocations.array' => 'The allocation data was not submitted in the expected format.',
        'allocations.min' => 'Please provide at least one yearly allocation.',
        'allocations.*.required' => 'Every year needs an allocation amount. Use 0 where there is no budget.',
        'allocations.*.numeric' => 'Allocation amounts must be valid numbers.',
        'allocations.*.min' => 'Allocation amounts cannot be negative.',
    ]);

    if ($validator->fails()) {
        return back()
            ->withErrors($validator)
            ->withInput()
            ->with('error', 'Sub-activity allocations were not saved. Please fix the highlighted allocation amounts.');
    }

    $allocations = $this->normalizeAllocations((array) $request->input('allocations', []));
    $error = $this->validateSubActivityAllocations($sub, $allocations);
    if ($error) {
        return back()->withInput()->with('error', $error);
    }

    try {
        DB::transaction(fn () => $this->persistSubActivityAllocations($sub, $allocations));
    } catch (\Throwable $e) {
        return back()
            ->withInput()
            ->with('error', 'Sub-activity allocations were not saved because the database update failed: ' . $e->getMessage());
    }

    return back()->with('success', 'Sub-activity allocations updated successfully.');
}

public function destroy($id)
{
    $sub = SubActivity::findOrFail($id);
    $this->assertSubActivityInScope($sub);

    // Optional → delete allocations first if foreign key constraints apply
    $sub->allocations()->delete();

    $sub->delete();

    return back()->with('success', 'Sub-Activity deleted successfully.');
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

    private function assertSubActivityInScope(SubActivity $sub): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        $nodeId = $sub->governance_node_id ?? $sub->activity?->governance_node_id;
        if (!$nodeId || !in_array($nodeId, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this sub-activity.');
        }
    }

    private function validateSubActivityAllocations(SubActivity $sub, array &$allocations): ?string
    {
        $activityYears = collect($sub->activity?->years() ?? [])->map(fn ($year) => (int) $year)->all();

        if (empty($activityYears)) {
            return 'Sub-activity allocations were not saved because the parent activity has no project years configured.';
        }

        $unexpectedYears = array_diff(array_keys($allocations), $activityYears);
        if (!empty($unexpectedYears)) {
            return 'Sub-activity allocations were not saved because these years do not belong to the parent activity: ' . implode(', ', $unexpectedYears) . '.';
        }

        foreach ($activityYears as $year) {
            $allocations[$year] = $allocations[$year] ?? 0.0;
        }

        ksort($allocations);

        $activityTotal = round((float) $sub->activity->allocations->sum('amount'), 2);
        $otherSubActivitiesTotal = round((float) $sub->activity->subActivities
            ->where('id', '!=', $sub->id)
            ->sum(fn ($otherSubActivity) => $otherSubActivity->allocations->sum('amount')), 2);
        $newSubActivityTotal = round(array_sum($allocations), 2);
        $combinedTotal = round($otherSubActivitiesTotal + $newSubActivityTotal, 2);

        if ($combinedTotal > $activityTotal) {
            return 'Sub-activity allocations were not saved because the combined sub-activity total (' . number_format($combinedTotal, 2) . ') exceeds the parent activity budget (' . number_format($activityTotal, 2) . '). Reduce this sub-activity by at least ' . number_format($combinedTotal - $activityTotal, 2) . '.';
        }

        $activityAllocationsByYear = $sub->activity->allocations->keyBy(fn ($allocation) => (int) $allocation->year);
        foreach ($allocations as $year => $amount) {
            $activityYearBudget = round((float) optional($activityAllocationsByYear->get($year))->amount, 2);
            $otherSubActivitiesYearTotal = round((float) $sub->activity->subActivities
                ->where('id', '!=', $sub->id)
                ->sum(function ($otherSubActivity) use ($year) {
                    return $otherSubActivity->allocations
                        ->where('year', $year)
                        ->sum('amount');
                }), 2);
            $combinedYearTotal = round($otherSubActivitiesYearTotal + (float) $amount, 2);

            if ($combinedYearTotal > $activityYearBudget) {
                return 'Sub-activity allocations were not saved because year ' . $year . ' would exceed the parent activity budget. The parent activity has ' . number_format($activityYearBudget, 2) . ' for that year, while sub-activities would total ' . number_format($combinedYearTotal, 2) . '.';
            }
        }

        return null;
    }

    private function persistSubActivityAllocations(SubActivity $sub, array $allocations): void
    {
        SubActivityAllocation::where('sub_activity_id', $sub->id)
            ->whereNotIn('year', array_keys($allocations))
            ->delete();

        foreach ($allocations as $year => $amount) {
            SubActivityAllocation::updateOrCreate(
                [
                    'sub_activity_id' => $sub->id,
                    'year' => $year,
                ],
                [
                    'amount' => $amount,
                ]
            );
        }
    }

    private function normalizeAllocations(array $allocations): array
    {
        $normalized = [];

        foreach ($allocations as $year => $amount) {
            $normalized[(int) $year] = round((float) $amount, 2);
        }

        return $normalized;
    }


}
