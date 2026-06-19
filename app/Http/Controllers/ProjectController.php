<?php

namespace App\Http\Controllers;

use App\Models\Program;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{
    /**
     * Display list of projects
     */
    public function index()
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds !== null && empty($scopedNodeIds)) {
            abort(403, 'You do not have access to projects.');
        }

        $projects = Project::with('program')
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->orderBy('id', 'desc')
            ->get();

        $programSummaries = Program::query()
            ->when($scopedNodeIds !== null, function ($query) use ($scopedNodeIds) {
                $query->whereIn('governance_node_id', $scopedNodeIds)
                    ->whereNotNull('governance_node_id');
            })
            ->withSum('projects', 'total_budget')
            ->orderBy('name')
            ->get()
            ->map(function ($program) {
                $used = (float) ($program->projects_sum_total_budget ?? 0);
                $total = (float) ($program->total_budget ?? 0);
                return (object) [
                    'program_id' => $program->program_id,
                    'name' => $program->name,
                    'currency' => $program->currency,
                    'total_budget' => $total,
                    'used_budget' => $used,
                    'remaining_budget' => max($total - $used, 0),
                ];
            });

        return view('budget.projects.index', compact('projects', 'programSummaries'));
    }

    /**
     * Show create project form
     */
    public function create()
    {
        $sectors = Sector::with(['programs:id,name,sector_id'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('budget.projects.create', compact('sectors'));
    }

    /**
     * Store new project
     */
    public function store(Request $request)
{
        $request->validate([
            'program_id'   => 'required|exists:myb_programs,id',
            'name'         => 'required|string|max:255',
            'expected_outcome_type' => 'required|in:percentage,text',
            'expected_outcome_percentage' => 'nullable|numeric|min:0|max:100',
        'expected_outcome_text' => 'nullable|string|max:2000',
        'start_year'   => 'required|integer',
        'end_year'     => 'required|integer|gte:start_year',
        'total_budget' => 'required|numeric|min:0',
        'description'  => 'nullable|string',
        'allocations'  => 'required|array',
            'allocations.*'=> 'numeric|min:0',
        ]);

    DB::beginTransaction();

    try {
        $program = Program::findOrFail($request->program_id);
        $this->assertProgramInScope($program);

        // Validate years
        if ($request->start_year < $program->start_year)
            return back()->with('error','Start year cannot be before program start year.')->withInput();

        if ($request->end_year > $program->end_year)
            return back()->with('error','End year cannot exceed program end year.')->withInput();

        if ($program->total_budget !== null && $request->total_budget > $program->total_budget) {
            return back()
                ->withErrors(['total_budget' => 'This project budget is higher than the program budget. Please enter an amount within the program total.'])
                ->withInput();
        }
        if ($program->total_budget !== null) {
            $existingTotal = Project::where('program_id', $program->id)->sum('total_budget');
            $remaining = $program->total_budget - $existingTotal;
            if (($existingTotal + (float) $request->total_budget) > $program->total_budget) {
                return back()
                    ->withErrors(['total_budget' => 'This program has only ' . ($program->currency ?? '') . ' ' . number_format(max($remaining, 0), 2) . ' remaining. Please lower the project budget.'])
                    ->withInput();
            }
        }

        $totalYears = $request->end_year - $request->start_year + 1;

        // Auto-generate Project ID
        $last = Project::where('program_id', $program->id)->latest('id')->first();
        $next = $last ? intval(substr($last->project_id, -2)) + 1 : 1;

        $projectId = $program->program_id . '-' . str_pad($next, 2, '0', STR_PAD_LEFT);

        // Create Project
        $expectedOutcomeValue = $request->expected_outcome_type === 'percentage'
            ? (string) ($request->expected_outcome_percentage ?? '')
            : ($request->expected_outcome_text ?? '');

        if ($request->expected_outcome_type === 'percentage' && $expectedOutcomeValue === '') {
            return back()->with('error', 'Expected outcome percentage is required.')->withInput();
        }

        if ($request->expected_outcome_type === 'text' && $expectedOutcomeValue === '') {
            return back()->with('error', 'Expected outcome description is required.')->withInput();
        }

        $project = Project::create([
            'program_id'   => $program->id,
            'project_id'   => $projectId,
            'governance_node_id' => $program->governance_node_id,
            'name'         => $request->name,
            'description'  => $request->description,
            'expected_outcome_type' => $request->expected_outcome_type,
            'expected_outcome_value' => $expectedOutcomeValue,
            'currency'     => $program->currency,
            'start_year'   => $request->start_year,
            'end_year'     => $request->end_year,
            'total_years'  => $totalYears,
            'total_budget' => $request->total_budget,
            'created_by'   => auth()->id(),
        ]);

        $allocations = $this->normalizeProjectAllocations(
            $project,
            (array) $request->input('allocations', [])
        );

        if (empty($allocations)) {
            $allocations = $this->buildEvenAllocations(
                (int) $request->start_year,
                (int) $request->end_year,
                (float) $request->total_budget
            );
        }

        $allocations = $this->completeProjectAllocations($project, $allocations);

        $allocationTotal = round(array_sum($allocations), 2);
        if ($allocationTotal - (float) $request->total_budget > 0.01) {
            throw new \Exception(
                'Yearly allocations (' . number_format($allocationTotal, 2) . ') exceed total budget (' . number_format((float) $request->total_budget, 2) . ').'
            );
        }

        $allocations = $this->balanceAllocationsToBudget($allocations, (float) $request->total_budget);
        $this->persistProjectAllocations($project, $allocations);

        DB::commit();
        return redirect()->route('budget.projects.index')->with('success', 'Project created successfully.');

    } catch (\Throwable $e) {
        DB::rollBack();
        return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
    }
}



    /**
     * Show project details
     */
    public function show($id)
    {
        $project = Project::with(
            'program.indicators.level',
            'program.indicators.frequency',
            'program.indicators.unit',
            'allocations',
            'activities.allocations',
            'indicators.level',
            'indicators.frequency',
            'indicators.unit',
            'indicators.parentIndicator'
        )->findOrFail($id);
        $this->assertProjectInScope($project);
        return view('budget.projects.show', compact('project'));
    }

    /**
     * Edit project
     */
    public function edit($id)
    {
        $project  = Project::with('allocations', 'activities.allocations', 'program')->findOrFail($id);
        $this->assertProjectInScope($project);
        $programs = $this->availablePrograms();

        return view('budget.projects.edit', compact(
            'project',
            'programs'
        ));
    }

    /**
     * Update project
     */
public function update(Request $request, $id)
{
    $project = Project::findOrFail($id);
    $this->assertProjectInScope($project);
    $program = Program::findOrFail($request->program_id);
    $this->assertProgramInScope($program);

    // Validation
    $request->validate([
        'program_id'   => 'required|exists:myb_programs,id',
        'name'         => 'required|string|max:255',
        'expected_outcome_type' => 'required|in:percentage,text',
        'expected_outcome_percentage' => 'nullable|numeric|min:0|max:100',
        'expected_outcome_text' => 'nullable|string|max:2000',
        'start_year'   => 'required|integer',
        'end_year'     => 'required|integer|gte:start_year',
        'total_budget' => 'required|numeric|min:0',
        'description'  => 'nullable|string',
        'allocations'  => 'nullable|array',
        'allocations.*'=> 'nullable|numeric|min:0',
    ]);

    // Validate years inside program range
    if ($request->start_year < $program->start_year) {
        return back()->with('error', 'Project start year cannot be earlier than program start year.')
            ->withInput();
    }

    if ($request->end_year > $program->end_year) {
        return back()->with('error', 'Project end year cannot exceed program end year.')
            ->withInput();
    }

    if ($program->total_budget !== null && $request->total_budget > $program->total_budget) {
        return back()
            ->withErrors(['total_budget' => 'This project budget is higher than the program budget. Please enter an amount within the program total.'])
            ->withInput();
    }
    if ($program->total_budget !== null) {
        $existingTotal = Project::where('program_id', $program->id)
            ->where('id', '!=', $project->id)
            ->sum('total_budget');
        $remaining = $program->total_budget - $existingTotal;
        if (($existingTotal + (float) $request->total_budget) > $program->total_budget) {
            return back()
                ->withErrors(['total_budget' => 'This program has only ' . ($program->currency ?? '') . ' ' . number_format(max($remaining, 0), 2) . ' remaining. Please lower the project budget.'])
            ->withInput();
        }
    }

    $allocations = $this->normalizeProjectAllocations(
        $project,
        (array) $request->input('allocations', [])
    );

    if (empty($allocations)) {
        $allocations = $this->buildEvenAllocations(
            (int) $request->start_year,
            (int) $request->end_year,
            (float) $request->total_budget
        );
    }

    if (empty($allocations)) {
        return back()->with('error', 'Unable to determine yearly allocations for this project.')->withInput();
    }

    $allocations = $this->completeProjectAllocations($project, $allocations);

    $allocationTotal = round(array_sum($allocations), 2);
    if ($allocationTotal - (float) $request->total_budget > 0.01) {
        return back()
            ->withErrors([
                'total_budget' => 'Yearly allocations (' . number_format($allocationTotal, 2) . ') exceed total budget (' . number_format((float) $request->total_budget, 2) . ').'
            ])
            ->withInput();
    }

    $allocations = $this->balanceAllocationsToBudget($allocations, (float) $request->total_budget);

    $childAllocationError = $this->projectChildAllocationError($project, $allocations);
    if ($childAllocationError) {
        return back()
            ->withInput()
            ->with('error', $childAllocationError);
    }

    DB::beginTransaction();

    try {
        // Update project
        $totalYears = $request->end_year - $request->start_year + 1;

        $expectedOutcomeValue = $request->expected_outcome_type === 'percentage'
            ? (string) ($request->expected_outcome_percentage ?? '')
            : ($request->expected_outcome_text ?? '');

        if ($request->expected_outcome_type === 'percentage' && $expectedOutcomeValue === '') {
            return back()->with('error', 'Expected outcome percentage is required.')->withInput();
        }

        if ($request->expected_outcome_type === 'text' && $expectedOutcomeValue === '') {
            return back()->with('error', 'Expected outcome description is required.')->withInput();
        }

        $project->update([
            'program_id'   => $program->id,
            'name'         => $request->name,
            'description'  => $request->description,
            'expected_outcome_type' => $request->expected_outcome_type,
            'expected_outcome_value' => $expectedOutcomeValue,
            'currency'     => $program->currency,
            'start_year'   => $request->start_year,
            'end_year'     => $request->end_year,
            'total_years'  => $totalYears,
            'total_budget' => $request->total_budget,
            'governance_node_id' => $program->governance_node_id,
        ]);

        $this->persistProjectAllocations($project, $allocations);

        DB::commit();
        return redirect()->route('budget.projects.index')->with('success', 'Project updated successfully.');

    } catch (\Throwable $e) {
        DB::rollBack();
        return back()->with('error', 'Error: ' . $e->getMessage())->withInput();
    }
}

    public function updateAllocations(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'allocations' => 'required|array|min:1',
            'allocations.*' => 'required|numeric|min:0',
        ], [
            'allocations.required' => 'No allocation rows were submitted. Please enter the yearly project amounts and try again.',
            'allocations.array' => 'The allocation data was not submitted in the expected format.',
            'allocations.min' => 'Please provide at least one yearly project allocation.',
            'allocations.*.required' => 'Every project year needs an allocation amount. Use 0 where there is no budget.',
            'allocations.*.numeric' => 'Project allocation amounts must be valid numbers.',
            'allocations.*.min' => 'Project allocation amounts cannot be negative.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput()
                ->with('error', 'Project allocations were not saved. Please fix the highlighted yearly amounts.');
        }

        $project = Project::with('activities.allocations')->findOrFail($id);
        $this->assertProjectInScope($project);

        $unexpectedYears = $this->unexpectedProjectAllocationYears(
            $project,
            (array) $request->input('allocations', [])
        );
        if (!empty($unexpectedYears)) {
            return back()
                ->withInput()
                ->with('error', 'Project allocations were not saved because these years are outside the project duration: ' . implode(', ', $unexpectedYears) . '.');
        }

        $allocations = $this->normalizeProjectAllocations(
            $project,
            (array) $request->input('allocations', [])
        );
        $allocations = $this->completeProjectAllocations($project, $allocations);

        $allocationTotal = round(array_sum($allocations), 2);
        if ($allocationTotal - (float) $project->total_budget > 0.01) {
            return back()
                ->withErrors([
                    'allocations' => 'Project allocations total (' . number_format($allocationTotal, 2) . ') cannot exceed project budget (' . number_format((float) $project->total_budget, 2) . ').',
                ])
                ->withInput()
                ->with('error', 'Project allocations were not saved because the yearly total is above the project budget.');
        }

        $allocations = $this->balanceAllocationsToBudget($allocations, (float) $project->total_budget);

        $childAllocationError = $this->projectChildAllocationError($project, $allocations);
        if ($childAllocationError) {
            return back()
                ->withInput()
                ->with('error', $childAllocationError);
        }

        DB::beginTransaction();
        try {
            $this->persistProjectAllocations($project, $allocations);
            DB::commit();

            return back()->with('success', 'Project allocations updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Project allocations were not saved because the database update failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete project
     */
    public function destroy($id)
    {
        $project = Project::findOrFail($id);
        $this->assertProjectInScope($project);

        DB::beginTransaction();

        try {
            $project->load('activities.subActivities.allocations', 'activities.allocations');

            // Remove descendants first.
            foreach ($project->activities as $activity) {
                foreach ($activity->subActivities as $subActivity) {
                    $subActivity->allocations()->delete();
                    \App\Models\Indicator::where('indicatorable_type', \App\Models\SubActivity::class)
                        ->where('indicatorable_id', $subActivity->id)
                        ->delete();
                    $subActivity->delete();
                }

                $activity->allocations()->delete();
                \App\Models\Indicator::where('indicatorable_type', \App\Models\Activity::class)
                    ->where('indicatorable_id', $activity->id)
                    ->delete();
                $activity->delete();
            }

            \App\Models\Indicator::where('indicatorable_type', \App\Models\Project::class)
                ->where('indicatorable_id', $project->id)
                ->delete();

            ProjectAllocation::where('project_id', $id)->delete();
            $project->delete();

            DB::commit();

            return redirect()->route('budget.projects.index')
                ->with('success', 'Project deleted successfully.');

        } catch (\Throwable $e) {

            DB::rollBack();
            return back()->with('error', 'Unable to delete project: ' . $e->getMessage());
        }
    }

    /**
     * Analytics Dashboard
     */
    public function analytics()
    {
        $totalProjects = Project::count();
        $totalPrograms = Program::count();
        $totalBudget   = Program::sum('total_budget');
        $totalAlloc    = ProjectAllocation::sum('amount');

        // Sector Distribution
        $sectorDistribution = Program::selectRaw('sector_id, SUM(total_budget) as budget')
            ->groupBy('sector_id')
            ->with('sector')
            ->get();

        // Yearly Trend using actual_year
        $yearlyTrend = ProjectAllocation::selectRaw('actual_year, SUM(amount) as total')
            ->groupBy('actual_year')
            ->orderBy('actual_year')
            ->get();

        // Top projects
        $topProjects = Project::orderBy('total_budget', 'DESC')
            ->take(5)
            ->get();

        return view('budget.projects.analytics', compact(
            'totalProjects',
            'totalPrograms',
            'totalBudget',
            'totalAlloc',
            'sectorDistribution',
            'yearlyTrend',
            'topProjects'
        ));
    }

    private function normalizeProjectAllocations(Project $project, array $rawAllocations): array
    {
        $normalized = [];
        $startYear = (int) $project->start_year;
        $endYear = (int) $project->end_year;
        $yearCount = max((int) ($project->total_years ?? 0), 1);

        foreach ($rawAllocations as $yearKey => $amount) {
            if ($amount === null || $amount === '' || !is_numeric($amount)) {
                continue;
            }

            $inputYear = (int) $yearKey;
            $actualYear = null;

            if ($inputYear >= $startYear && $inputYear <= $endYear) {
                $actualYear = $inputYear;
            } elseif ($inputYear >= 1 && $inputYear <= $yearCount) {
                $actualYear = $startYear + $inputYear - 1;
            }

            if ($actualYear === null) {
                continue;
            }

            $normalized[$actualYear] = round((float) $amount, 2);
        }

        ksort($normalized);

        return $normalized;
    }

    private function buildEvenAllocations(int $startYear, int $endYear, float $totalBudget): array
    {
        if ($endYear < $startYear) {
            return [];
        }

        $years = range($startYear, $endYear);
        $count = count($years);
        if ($count === 0) {
            return [];
        }

        $allocations = [];
        $baseAmount = floor(($totalBudget / $count) * 100) / 100;

        foreach ($years as $year) {
            $allocations[$year] = $baseAmount;
        }

        $difference = round($totalBudget - array_sum($allocations), 2);
        $lastYear = end($years);
        $allocations[$lastYear] = round(($allocations[$lastYear] ?? 0) + $difference, 2);

        return $allocations;
    }

    private function completeProjectAllocations(Project $project, array $allocations): array
    {
        foreach ($project->years() as $year) {
            $allocations[(int) $year] = $allocations[(int) $year] ?? 0.0;
        }

        ksort($allocations);

        return $allocations;
    }

    private function balanceAllocationsToBudget(array $allocations, float $totalBudget): array
    {
        if (empty($allocations)) {
            return $allocations;
        }

        ksort($allocations);

        $difference = round($totalBudget - array_sum($allocations), 2);
        if ($difference > 0) {
            $lastYear = array_key_last($allocations);
            $allocations[$lastYear] = round((float) ($allocations[$lastYear] ?? 0) + $difference, 2);
        }

        return $allocations;
    }

    private function unexpectedProjectAllocationYears(Project $project, array $rawAllocations): array
    {
        $projectYears = array_map('intval', $project->years());
        $unexpectedYears = [];

        foreach (array_keys($rawAllocations) as $year) {
            if (!in_array((int) $year, $projectYears, true)) {
                $unexpectedYears[] = (int) $year;
            }
        }

        return $unexpectedYears;
    }

    private function projectChildAllocationError(Project $project, array $allocations): ?string
    {
        $project->loadMissing('activities.allocations');

        foreach ($project->years() as $year) {
            $year = (int) $year;
            $activityTotalForYear = round((float) $project->activities->sum(function ($activity) use ($year) {
                return $activity->allocations
                    ->where('year', $year)
                    ->sum('amount');
            }), 2);
            $projectAllocationForYear = round((float) ($allocations[$year] ?? 0), 2);

            if ($activityTotalForYear > $projectAllocationForYear) {
                return 'Project allocations were not saved because year ' . $year . ' already has activity allocations totaling ' . number_format($activityTotalForYear, 2) . ', but the project allocation would be ' . number_format($projectAllocationForYear, 2) . '. Increase the project allocation for that year or reduce the activity allocations first.';
            }
        }

        return null;
    }

    private function persistProjectAllocations(Project $project, array $allocations): void
    {
        $years = array_keys($allocations);

        if (!empty($years)) {
            ProjectAllocation::where('project_id', $project->id)
                ->whereNotIn('year', $years)
                ->delete();
        }

        foreach ($allocations as $year => $amount) {
            $yearNumber = ((int) $year - (int) $project->start_year) + 1;

            ProjectAllocation::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'year' => $year,
                ],
                [
                    'year_number' => $yearNumber,
                    'actual_year' => $year,
                    'amount' => $amount ?? 0,
                ]
            );
        }
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

    private function availablePrograms()
    {
        $scopedNodeIds = $this->scopedNodeIds();

        $query = Program::orderBy('name');

        if ($scopedNodeIds !== null) {
            $query->whereIn('governance_node_id', $scopedNodeIds)
                ->whereNotNull('governance_node_id');
        }

        return $query->get();
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

    private function assertProgramInScope(Program $program): void
    {
        $scopedNodeIds = $this->scopedNodeIds();
        if ($scopedNodeIds === null) {
            return;
        }

        if (!$program->governance_node_id || !in_array($program->governance_node_id, $scopedNodeIds, true)) {
            abort(403, 'You do not have access to this program.');
        }
    }
}
