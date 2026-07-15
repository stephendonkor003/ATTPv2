<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Program;
use App\Models\Project;
use App\Models\Activity;
use App\Models\SubActivity;
use Illuminate\Http\Request;

class AllocationSummaryController extends Controller
{
    use ScopesAssignedPortfolios;

    /**
     * =========================================================================
     *  BUDGET DASHBOARD
     * =========================================================================
     *  High-level KPIs + Program/Project/Activity allocation summaries
     */
    public function dashboard()
    {
        return view('reports.budget_dashboard', $this->budgetSummaryPayload());
    }

    public function exportDashboardPdf()
    {
        $data = $this->budgetSummaryPayload();
        $filename = 'budget-summary-dashboard-' . now()->format('Ymd-His') . '.pdf';

        return Pdf::loadView('reports.budget_dashboard_pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function budgetSummaryPayload(): array
    {
        // Load entire budget hierarchy
        $programs = $this->scopedProgramSummaryQuery()->with([
            'sector',
            'projects.allocations',
            'projects.activities.allocations',
            'projects.activities.subActivities.allocations'
        ])->orderBy('name')->get();

        // KPI totals
        $projects = $programs->flatMap->projects;
        $activities = $projects->flatMap->activities;
        $subActivities = $activities->flatMap->subActivities;
        $totalPrograms = $programs->count();
        $totalProjects = $projects->count();
        $totalActivities = $activities->count();
        $totalSubActivities = $subActivities->count();

        $programRows = $programs->map(function (Program $program) {
            $projects = $program->projects;
            $activities = $projects->flatMap->activities;
            $subActivities = $activities->flatMap->subActivities;

            $totalBudget = (float) $projects->sum(fn (Project $project) => $this->projectBudgetAmount($project));
            $projectAllocation = (float) $projects->sum(fn (Project $project) => $this->projectAllocationEnvelope($project));
            $activityAllocation = (float) $activities->sum(fn (Activity $activity) => $activity->allocations->sum('amount'));
            $subActivityAllocation = (float) $subActivities->sum(fn (SubActivity $subActivity) => $subActivity->allocations->sum('amount'));
            $totalAllocated = $projectAllocation;
            $remaining = $totalBudget - $totalAllocated;

            return [
                'program' => $program,
                'program_id' => (string) $program->id,
                'name' => $program->name,
                'sector' => $program->sector?->name ?? 'Unassigned',
                'projects' => $projects->count(),
                'activities' => $activities->count(),
                'sub_activities' => $subActivities->count(),
                'total_budget' => round($totalBudget, 2),
                'project_allocated' => round($projectAllocation, 2),
                'activity_allocated' => round($activityAllocation, 2),
                'sub_activity_allocated' => round($subActivityAllocation, 2),
                'total_allocated' => round($totalAllocated, 2),
                'remaining' => round($remaining, 2),
                'utilization' => $totalBudget > 0 ? round(($totalAllocated / $totalBudget) * 100, 1) : 0,
            ];
        })->values();

        $sectorRows = $programRows
            ->groupBy('sector')
            ->map(function ($rows, string $sector) {
                return [
                    'sector' => $sector,
                    'programs' => $rows->count(),
                    'projects' => $rows->sum('projects'),
                    'total_budget' => round((float) $rows->sum('total_budget'), 2),
                    'total_allocated' => round((float) $rows->sum('total_allocated'), 2),
                    'remaining' => round((float) $rows->sum('remaining'), 2),
                ];
            })
            ->sortByDesc('total_allocated')
            ->values();

        // Total budget from all projects
        $totalBudget = round((float) $programRows->sum('total_budget'), 2);

        $totalProjectAllocated = round((float) $programRows->sum('project_allocated'), 2);
        $totalActivityAllocated = round((float) $programRows->sum('activity_allocated'), 2);
        $totalSubAllocated = round((float) $programRows->sum('sub_activity_allocated'), 2);
        $grandAllocated = $totalProjectAllocated;
        $remainingBudget = round($totalBudget - $grandAllocated, 2);
        $allocationRate = $totalBudget > 0 ? round(($grandAllocated / $totalBudget) * 100, 1) : 0;
        $topProgram = $programRows->sortByDesc('total_allocated')->first();
        $topSector = $sectorRows->sortByDesc('total_allocated')->first();

        $summary = [
            'total_budget' => $totalBudget,
            'project_allocated' => $totalProjectAllocated,
            'activity_allocated' => $totalActivityAllocated,
            'sub_activity_allocated' => $totalSubAllocated,
            'total_allocated' => $grandAllocated,
            'remaining_budget' => $remainingBudget,
            'allocation_rate' => $allocationRate,
            'top_program' => $topProgram['name'] ?? null,
            'top_sector' => $topSector['sector'] ?? null,
        ];

        $chartData = [
            'programLabels' => $programRows->sortByDesc('total_allocated')->take(10)->pluck('name')->values(),
            'programAllocated' => $programRows->sortByDesc('total_allocated')->take(10)->pluck('total_allocated')->values(),
            'programBudget' => $programRows->sortByDesc('total_allocated')->take(10)->pluck('total_budget')->values(),
            'sectorLabels' => $sectorRows->pluck('sector')->values(),
            'sectorAllocated' => $sectorRows->pluck('total_allocated')->values(),
            'allocationSplitLabels' => collect(['Project', 'Activity', 'Sub-Activity']),
            'allocationSplit' => collect([$totalProjectAllocated, $totalActivityAllocated, $totalSubAllocated]),
        ];

        return compact(
            'programs',
            'totalPrograms',
            'totalProjects',
            'totalActivities',
            'totalSubActivities',
            'totalBudget',
            'totalActivityAllocated',
            'totalSubAllocated',
            'remainingBudget',
            'grandAllocated',
            'allocationRate',
            'programRows',
            'sectorRows',
            'summary',
            'chartData'
        );
    }


    /**
     * =========================================================================
     *  EXECUTIVE REPORTS
     * =========================================================================
     *  Rankings, comparisons, variance analysis, performance summaries
     */
    public function executiveReports()
    {
        return view('reports.executive_summary', $this->executiveSummaryPayload());
    }

    public function exportExecutivePdf()
    {
        $data = $this->executiveSummaryPayload();
        $filename = 'budget-executive-summary-' . now()->format('Ymd-His') . '.pdf';

        return Pdf::loadView('reports.executive_summary_pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function executiveSummaryPayload(): array
    {
        // Load hierarchy for reporting
        $programs = $this->scopedProgramSummaryQuery()->with([
            'sector',
            'projects.allocations',
            'projects.activities.allocations',
            'projects.activities.subActivities.allocations'
        ])->orderBy('name')->get();

        // Rank projects by total allocated amount
        $projectQuery = Project::with([
            'program.sector',
            'allocations',
            'activities.allocations',
            'activities.subActivities.allocations',
        ]);
        if ($this->userHasAssignedPortfolioScope(request()->user())) {
            $this->applyAssignedPortfolioScopeToProjects($projectQuery, request()->user());
        }

        $projectRankings = $projectQuery->get()
            ->map(function ($project) {
                $activityAllocated = (float) $project->activities->sum(
                    fn ($activity) => $activity->allocations->sum('amount')
                );
                $subActivityAllocated = (float) $project->activities->sum(
                    fn ($activity) => $activity->subActivities->sum(
                        fn ($subActivity) => $subActivity->allocations->sum('amount')
                    )
                );
                $projectAllocated = $this->projectAllocationEnvelope($project);
                $totalAllocated = $projectAllocated;
                $budget = $this->projectBudgetAmount($project);

                return [
                    'project' => $project,
                    'allocated' => round($totalAllocated, 2),
                    'project_allocated' => round($projectAllocated, 2),
                    'activity_allocated' => round($activityAllocated, 2),
                    'sub_activity_allocated' => round($subActivityAllocated, 2),
                    'budget' => round($budget, 2),
                    'remaining' => round($budget - $totalAllocated, 2),
                    'utilization' => $budget > 0 ? round(($totalAllocated / $budget) * 100, 1) : 0,
                ];
            })
            ->sortByDesc('allocated')
            ->values();

        // Rank activities by funding level
        $activityQuery = Activity::with('allocations', 'subActivities.allocations', 'project.program');
        if ($this->userHasAssignedPortfolioScope(request()->user())) {
            $this->applyAssignedPortfolioScopeToActivities($activityQuery, request()->user());
        }

        $activityRankings = $activityQuery->get()
            ->map(function ($activity) {
                $allocated = (float) $activity->allocations->sum('amount');
                $subAllocated = (float) $activity->subActivities->sum(
                    fn ($subActivity) => $subActivity->allocations->sum('amount')
                );

                return [
                    'activity' => $activity,
                    'project' => $activity->project,
                    'allocated' => round($allocated, 2),
                    'activity_allocated' => round($allocated, 2),
                    'sub_activity_allocated' => round($subAllocated, 2),
                ];
            })
            ->sortByDesc('allocated')
            ->values();

        // Compare sub-activity allocations
        $subActivityQuery = SubActivity::with('allocations', 'activity.project');
        if ($this->userHasAssignedPortfolioScope(request()->user())) {
            $this->applyAssignedPortfolioScopeToSubActivities($subActivityQuery, request()->user());
        }

        $subActivityRankings = $subActivityQuery->get()
            ->map(function ($sub) {
                return [
                    'sub' => $sub,
                    'activity' => $sub->activity,
                    'project' => $sub->activity->project,
                    'allocated' => $sub->allocations->sum('amount'),
                ];
            })
            ->sortByDesc('allocated')
            ->values();

        $totalProjectBudget = round((float) $projectRankings->sum('budget'), 2);
        $totalRankedAllocation = round((float) $projectRankings->sum('allocated'), 2);
        $averageProjectAllocation = $projectRankings->count() > 0
            ? round($totalRankedAllocation / $projectRankings->count(), 2)
            : 0;

        $programSheets = $programs->map(function (Program $program) {
            $projects = $program->projects;
            $activities = $projects->flatMap->activities;
            $subActivities = $activities->flatMap->subActivities;
            $activityAllocated = (float) $activities->sum(fn (Activity $activity) => $activity->allocations->sum('amount'));
            $subActivityAllocated = (float) $subActivities->sum(fn (SubActivity $subActivity) => $subActivity->allocations->sum('amount'));
            $allocated = (float) $projects->sum(fn (Project $project) => $this->projectAllocationEnvelope($project));
            $budget = (float) $projects->sum(fn (Project $project) => $this->projectBudgetAmount($project));

            return [
                'program' => $program,
                'name' => $program->name,
                'sector' => $program->sector?->name ?? 'Unassigned',
                'currency' => $program->currency ?? ($projects->first()?->currency ?? ''),
                'projects' => $projects->count(),
                'activities' => $activities->count(),
                'sub_activities' => $subActivities->count(),
                'budget' => round($budget, 2),
                'allocated' => round($allocated, 2),
                'activity_allocated' => round($activityAllocated, 2),
                'sub_activity_allocated' => round($subActivityAllocated, 2),
                'remaining' => round($budget - $allocated, 2),
                'utilization' => $budget > 0 ? round(($allocated / $budget) * 100, 1) : 0,
            ];
        })->sortByDesc('allocated')->values();

        $executiveStats = [
            'programs' => $programs->count(),
            'projects' => $projectRankings->count(),
            'activities' => $activityRankings->count(),
            'sub_activities' => $subActivityRankings->count(),
            'total_budget' => $totalProjectBudget,
            'total_allocated' => $totalRankedAllocation,
            'remaining' => round($totalProjectBudget - $totalRankedAllocation, 2),
            'average_project_allocation' => $averageProjectAllocation,
            'top_project' => $projectRankings->first()['project']->name ?? null,
            'top_activity' => $activityRankings->first()['activity']->name ?? null,
            'top_sub_activity' => $subActivityRankings->first()['sub']->name ?? null,
        ];

        $chartData = [
            'projectLabels' => $projectRankings->take(10)->map(fn ($item) => $item['project']->name)->values(),
            'projectAllocated' => $projectRankings->take(10)->pluck('allocated')->values(),
            'projectBudgets' => $projectRankings->take(10)->pluck('budget')->values(),
            'activityLabels' => $activityRankings->take(10)->map(fn ($item) => $item['activity']->name)->values(),
            'activityAllocated' => $activityRankings->take(10)->pluck('allocated')->values(),
            'subActivityLabels' => $subActivityRankings->take(10)->map(fn ($item) => $item['sub']->name)->values(),
            'subActivityAllocated' => $subActivityRankings->take(10)->pluck('allocated')->values(),
            'programLabels' => $programSheets->take(10)->pluck('name')->values(),
            'programAllocated' => $programSheets->take(10)->pluck('allocated')->values(),
        ];

        return compact(
            'programs',
            'projectRankings',
            'activityRankings',
            'subActivityRankings',
            'programSheets',
            'executiveStats',
            'chartData'
        );
    }

    private function projectBudgetAmount(Project $project): float
    {
        $budget = (float) ($project->total_budget ?? 0);

        if ($budget <= 0 && $project->relationLoaded('allocations')) {
            $budget = (float) $project->allocations->sum('amount');
        }

        return round($budget, 2);
    }

    private function projectAllocationEnvelope(Project $project): float
    {
        $budget = $this->projectBudgetAmount($project);

        if ($budget > 0) {
            return $budget;
        }

        if ($project->relationLoaded('allocations')) {
            return round((float) $project->allocations->sum('amount'), 2);
        }

        return 0.0;
    }

    private function scopedProgramSummaryQuery()
    {
        $query = Program::query();
        $currentUser = request()->user();

        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            $this->applyAssignedPortfolioScopeToPrograms($query, $currentUser);
        }

        return $query;
    }
}
