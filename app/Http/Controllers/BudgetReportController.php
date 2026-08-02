<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Sector;
use App\Models\Program;
use App\Models\Project;
use App\Models\Activity;
use App\Models\BudgetCommitment;
use App\Models\ProgramFunding;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\SystemAuditLog;
use App\Services\ExecutionDashboardChartBuilder;

use App\Exports\ProgramExport;
use App\Exports\ProjectExport;
use App\Exports\ActivityExport;
use App\Exports\CommitmentReportExport;
use App\Exports\InterimFinancialReportExport;

use Maatwebsite\Excel\Facades\Excel;
use PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Throwable;

class BudgetReportController extends Controller
{
    use ScopesAssignedPortfolios;

    /* ================================
       SECTOR OVERVIEW
    ================================== */
    public function index(Request $request)
    {
        return view('budgetreport.index', $this->buildPortfolioBudgetOverviewData($request));
    }

    public function exportPortfolioPdf(Request $request)
    {
        $data = $this->buildPortfolioBudgetOverviewData($request);
        $filename = 'portfolio-budget-overview-' . now()->format('Ymd-His') . '.pdf';

        return PDF::loadView('budgetreport.portfolio_pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function buildPortfolioBudgetOverviewData(?Request $request = null): array
    {
        $sectors = $this->scopedSectorReportQuery()->with([
            'programs.approvedFundings',
            'programs.fundings',
            'programs.projects.allocations',
            'programs.projects.activities.allocations',
            'programs.projects.activities.subActivities.allocations'
        ])->orderBy('name')->get();

        $portfolioCurrency = $this->resolvePortfolioReportCurrency($sectors);

        $allProjects = $sectors
            ->flatMap(fn (Sector $sector) => $sector->programs->flatMap->projects)
            ->values();

        $sectorSummaries = $sectors->map(function (Sector $sector) {
            $projects = $sector->programs->flatMap->projects;
            $totalBudget = $sector->programs->sum(fn (Program $program) => $this->programBudgetAmount($program));
            $projectBudget = $projects->sum(fn (Project $project) => $this->projectBudgetAmount($project));
            $activityCount = $projects->sum(fn (Project $project) => $project->activities->count());

            return [
                'id' => (string) $sector->id,
                'name' => $sector->name,
                'currency' => $this->resolveSectorReportCurrency($sector),
                'programs' => $sector->programs->count(),
                'projects' => $projects->count(),
                'activities' => $activityCount,
                'total_budget' => round($totalBudget, 2),
                'average_project_budget' => $projects->count() > 0 ? round($projectBudget / $projects->count(), 2) : 0,
            ];
        })->values();

        $programSummaries = $sectors
            ->flatMap(fn (Sector $sector) => $sector->programs->map(function (Program $program) use ($sector) {
                $totalBudget = $this->programBudgetAmount($program);

                return [
                    'id' => (string) $program->id,
                    'name' => $program->name,
                    'sector' => $sector->name,
                    'currency' => $this->resolveSectorReportCurrency($sector),
                    'projects' => $program->projects->count(),
                    'total_budget' => round($totalBudget, 2),
                ];
            }))
            ->sortByDesc('total_budget')
            ->values();

        $projectSummaries = $sectors
            ->flatMap(fn (Sector $sector) => $sector->programs->flatMap(fn (Program $program) => $program->projects->map(function (Project $project) use ($program, $sector) {
                return [
                    'id' => (string) $project->id,
                    'name' => $project->name,
                    'program' => $program->name,
                    'sector' => $sector->name,
                    'currency' => $this->resolveSectorReportCurrency($sector),
                    'activities' => $project->activities->count(),
                    'total_budget' => $this->projectBudgetAmount($project),
                ];
            })))
            ->sortByDesc('total_budget')
            ->values();

        $annualTotals = $allProjects
            ->flatMap(fn (Project $project) => $project->allocations)
            ->groupBy('year')
            ->map(fn ($allocations) => round((float) $allocations->sum('amount'), 2))
            ->sortKeys();

        $topPrograms = $programSummaries->take(8)->values();
        $topProjects = $projectSummaries->take(10)->values();
        $totalBudget = round((float) $sectorSummaries->sum('total_budget'), 2);
        $projectBudgetTotal = round((float) $projectSummaries->sum('total_budget'), 2);
        $averageProjectBudget = $projectSummaries->count() > 0 ? round($projectBudgetTotal / $projectSummaries->count(), 2) : 0;
        $largestSector = $sectorSummaries->sortByDesc('total_budget')->first();
        $largestProgram = $programSummaries->sortByDesc('total_budget')->first();
        $projectBudgetBands = [
            'Modest' => 0,
            'Standard' => 0,
            'Major' => 0,
        ];

        foreach ($projectSummaries as $project) {
            if ($averageProjectBudget <= 0 || $project['total_budget'] < ($averageProjectBudget * 0.75)) {
                $projectBudgetBands['Modest']++;
            } elseif ($project['total_budget'] <= ($averageProjectBudget * 1.5)) {
                $projectBudgetBands['Standard']++;
            } else {
                $projectBudgetBands['Major']++;
            }
        }

        $portfolioStats = [
            'sectors' => $sectorSummaries->count(),
            'programs' => $programSummaries->count(),
            'projects' => $projectSummaries->count(),
            'activities' => $sectorSummaries->sum('activities'),
            'total_budget' => $totalBudget,
            'currency' => $portfolioCurrency,
            'average_project_budget' => $averageProjectBudget,
            'funded_sectors' => $sectorSummaries->where('total_budget', '>', 0)->count(),
            'largest_sector' => $largestSector['name'] ?? null,
            'largest_sector_share' => $totalBudget > 0 && $largestSector ? round(($largestSector['total_budget'] / $totalBudget) * 100, 1) : 0,
            'largest_program' => $largestProgram['name'] ?? null,
            'largest_program_share' => $totalBudget > 0 && $largestProgram ? round(($largestProgram['total_budget'] / $totalBudget) * 100, 1) : 0,
        ];

        $chartData = [
            'sectorLabels' => $sectorSummaries->pluck('name')->values(),
            'sectorTotals' => $sectorSummaries->pluck('total_budget')->values(),
            'sectorAverageProjects' => $sectorSummaries->pluck('average_project_budget')->values(),
            'sectorPrograms' => $sectorSummaries->pluck('programs')->values(),
            'sectorProjects' => $sectorSummaries->pluck('projects')->values(),
            'sectorActivities' => $sectorSummaries->pluck('activities')->values(),
            'yearLabels' => $annualTotals->keys()->values(),
            'yearTotals' => $annualTotals->values(),
            'topProgramLabels' => $topPrograms->pluck('name')->values(),
            'topProgramTotals' => $topPrograms->pluck('total_budget')->values(),
            'topProjectLabels' => $topProjects->pluck('name')->values(),
            'topProjectTotals' => $topProjects->pluck('total_budget')->values(),
            'projectBandLabels' => collect($projectBudgetBands)->keys()->values(),
            'projectBandCounts' => collect($projectBudgetBands)->values(),
            'projectScatter' => $projectSummaries->take(25)->map(fn (array $project) => [
                'x' => $project['activities'],
                'y' => $project['total_budget'],
                'r' => max(5, min(18, sqrt(max($project['total_budget'], 0)) / 150)),
                'label' => $project['name'],
                'sector' => $project['sector'],
            ])->values(),
            'currency' => $portfolioCurrency,
        ];

        $projectOptions = $projectSummaries
            ->map(fn (array $project) => [
                'id' => $project['id'],
                'name' => $project['name'],
                'program' => $project['program'],
                'sector' => $project['sector'],
                'total_budget' => $project['total_budget'],
            ])
            ->values();
        $selectedProjectId = $request?->input('project_id') ?: ($topProjects->first()['id'] ?? $projectOptions->first()['id'] ?? null);
        $selectedProject = $selectedProjectId
            ? $allProjects->first(fn (Project $project) => (string) $project->id === (string) $selectedProjectId)
            : null;
        $projectProgress = $this->buildSelectedProjectProgress($selectedProject);
        $activeReportTab = $request?->input('tab') === 'project-progress'
            ? 'project-progress'
            : 'portfolio';

        return compact(
            'sectors',
            'sectorSummaries',
            'programSummaries',
            'projectSummaries',
            'projectOptions',
            'selectedProjectId',
            'projectProgress',
            'activeReportTab',
            'portfolioStats',
            'chartData',
            'portfolioCurrency'
        );
    }

    private function programBudgetAmount(Program $program): float
    {
        return round((float) ($program->total_budget ?? 0), 2);
    }

    private function resolvePortfolioReportCurrency($sectors): string
    {
        $currencies = collect($sectors)
            ->flatMap(fn (Sector $sector) => [$this->resolveSectorReportCurrency($sector)])
            ->filter(fn ($currency) => filled($currency))
            ->map(fn ($currency) => strtoupper((string) $currency))
            ->unique()
            ->values();

        if ($currencies->count() === 0) {
            return 'USD';
        }

        if ($currencies->count() === 1) {
            return $currencies->first();
        }

        return 'Mixed: ' . $currencies->take(3)->implode(', ');
    }

    private function resolveSectorReportCurrency(Sector $sector): string
    {
        $programCurrencies = $sector->relationLoaded('programs')
            ? $sector->programs
                ->flatMap(function (Program $program) {
                    return [
                        $program->currency,
                        $program->approvedFundings?->first()?->currency,
                        $program->fundings?->first()?->currency,
                        $program->projects?->first()?->currency,
                    ];
                })
            : collect();

        return strtoupper((string) (
            $sector->currency
            ?: $programCurrencies->first(fn ($currency) => filled($currency))
            ?: 'USD'
        ));
    }

    private function projectBudgetAmount(Project $project): float
    {
        $budget = (float) ($project->total_budget ?? 0);

        if ($budget <= 0 && $project->relationLoaded('allocations')) {
            $budget = (float) $project->allocations->sum('amount');
        }

        return round($budget, 2);
    }

    private function buildSelectedProjectProgress(?Project $project): ?array
    {
        if (! $project) {
            return null;
        }

        $project->loadMissing([
            'program.sector',
            'allocations',
            'activities.allocations',
            'activities.subActivities.allocations',
        ]);

        $projectBudget = $this->projectBudgetAmount($project);

        $activityRows = $project->activities
            ->map(function (Activity $activity) use ($projectBudget) {
                $activityBudget = round((float) $activity->allocations->sum('amount'), 2);
                $subActivityBudget = round((float) $activity->subActivities->sum(fn ($subActivity) => (float) $subActivity->allocations->sum('amount')), 2);

                return [
                    'id' => (string) $activity->id,
                    'name' => $activity->name,
                    'sub_activities' => $activity->subActivities->count(),
                    'activity_budget' => $activityBudget,
                    'sub_activity_budget' => $subActivityBudget,
                    'remaining_to_sub_activities' => round(max($activityBudget - $subActivityBudget, 0), 2),
                    'sub_activity_overallocation' => round(max($subActivityBudget - $activityBudget, 0), 2),
                    'covered_by_sub_activities' => round(min($activityBudget, $subActivityBudget), 2),
                    'activity_share' => $projectBudget > 0 ? round(($activityBudget / $projectBudget) * 100, 1) : 0,
                    'sub_activity_progress' => $activityBudget > 0 ? round(min(100, ($subActivityBudget / $activityBudget) * 100), 1) : 0,
                ];
            })
            ->sortByDesc('activity_budget')
            ->values();

        $activityBudget = round((float) $activityRows->sum('activity_budget'), 2);
        $subActivityBudget = round((float) $activityRows->sum('sub_activity_budget'), 2);
        $remainingToSubActivities = round((float) $activityRows->sum('remaining_to_sub_activities'), 2);
        $subActivityOverallocation = round((float) $activityRows->sum('sub_activity_overallocation'), 2);
        $subActivityCoveredBudget = round((float) $activityRows->sum('covered_by_sub_activities'), 2);
        $years = collect($project->allocations->pluck('year'))
            ->merge($project->activities->flatMap(fn (Activity $activity) => $activity->allocations->pluck('year')))
            ->merge($project->activities->flatMap(fn (Activity $activity) => $activity->subActivities->flatMap(fn ($subActivity) => $subActivity->allocations->pluck('year'))))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return [
            'project' => [
                'id' => (string) $project->id,
                'name' => $project->name,
                'code' => $project->project_id,
                'program' => $project->program?->name,
                'currency' => $project->program?->sector?->currency ?: $project->currency ?: $project->program?->currency ?: 'USD',
                'start_year' => $project->start_year,
                'end_year' => $project->end_year,
            ],
            'summary' => [
                'project_budget' => $projectBudget,
                'activity_budget' => $activityBudget,
                'sub_activity_budget' => $subActivityBudget,
                'remaining_to_activities' => round(max($projectBudget - $activityBudget, 0), 2),
                'remaining_to_sub_activities' => $remainingToSubActivities,
                'sub_activity_overallocation' => $subActivityOverallocation,
                'activity_count' => $project->activities->count(),
                'sub_activity_count' => $project->activities->sum(fn (Activity $activity) => $activity->subActivities->count()),
                'activity_progress' => $projectBudget > 0 ? round(min(100, ($activityBudget / $projectBudget) * 100), 1) : 0,
                'sub_activity_progress' => $activityBudget > 0 ? round(($subActivityCoveredBudget / $activityBudget) * 100, 2) : 0,
            ],
            'activity_rows' => $activityRows,
            'chart' => [
                'labels' => $years,
                'project' => $years->map(fn ($year) => round((float) $project->allocations->where('year', $year)->sum('amount'), 2))->values(),
                'activities' => $years->map(fn ($year) => round((float) $project->activities->sum(fn (Activity $activity) => (float) $activity->allocations->where('year', $year)->sum('amount')), 2))->values(),
                'subActivities' => $years->map(fn ($year) => round((float) $project->activities->sum(fn (Activity $activity) => (float) $activity->subActivities->sum(fn ($subActivity) => (float) $subActivity->allocations->where('year', $year)->sum('amount'))), 2))->values(),
            ],
        ];
    }

    /* ================================
       PROGRAM REPORT
    ================================== */
    public function programReport($id)
    {
        $program = Program::with([
            'sector',
            'projects.allocations',
            'projects.activities.subActivities'
        ])->findOrFail($id);
        $this->assertProgramReportScope($program);

        return view('budgetreport.program', compact('program'));
    }

    /* ================================
       PROJECT REPORT
    ================================== */
    public function projectReport($id)
    {
        $project = Project::with([
            'program',
            'allocations',
            'activities.allocations',
            'activities.subActivities'
        ])->findOrFail($id);
        $this->assertProjectReportScope($project);

        return view('budgetreport.project', compact('project'));
    }

    /* ================================
       ACTIVITY REPORT
    ================================== */
    public function activityReport($id)
    {
        $activity = Activity::with([
            'project.program',
            'allocations',
            'subActivities.allocations'
        ])->findOrFail($id);
        $this->assertActivityReportScope($activity);

        return view('budgetreport.activity', compact('activity'));
    }


    /* ================================
       EXPORT: PDF
    ================================== */
    public function exportPDF($type, $id)
    {
        if ($type === 'program') {
            $data = Program::with('projects.activities.subActivities')->findOrFail($id);
            $this->assertProgramReportScope($data);
            $view = 'exports.program_pdf';
        }

        if ($type === 'project') {
            $data = Project::with('activities.subActivities')->findOrFail($id);
            $this->assertProjectReportScope($data);
            $view = 'exports.project_pdf';
        }

        if ($type === 'activity') {
            $data = Activity::with('subActivities')->findOrFail($id);
            $this->assertActivityReportScope($data);
            $view = 'exports.activity_pdf';
        }

        $pdf = PDF::loadView($view, compact('data'))->setPaper('a4', 'portrait');

        return $pdf->download("$type-report-$id.pdf");
    }


    /* ================================
       EXPORT: EXCEL
    ================================== */
    public function exportExcel($type, $id)
    {
        if ($type === 'program') {
            $this->assertProgramReportScope(Program::findOrFail($id));
            return Excel::download(new ProgramExport($id), "program-$id.xlsx");
        }

        if ($type === 'project') {
            $this->assertProjectReportScope(Project::findOrFail($id));
            return Excel::download(new ProjectExport($id), "project-$id.xlsx");
        }

        if ($type === 'activity') {
            $this->assertActivityReportScope(Activity::findOrFail($id));
            return Excel::download(new ActivityExport($id), "activity-$id.xlsx");
        }
    }


    /* ================================
       (OPTIONAL) DASHBOARD
    ================================== */
    public function dashboard()
    {
        $sectors = $this->scopedSectorReportQuery()->with([
            'programs.projects.allocations',
            'programs.projects.activities'
        ])->get();

        return view('budgetreport.dashboard', compact('sectors'));
    }

    /* ================================
       COMMITMENT REPORT
    ================================== */
    public function commitmentReport(Request $request)
    {
        $programs = $this->scopedProgramReportQuery()->orderBy('name')->get();
        $programId = $request->input('program_id');

        $report = null;
        $chartData = null;
        $summary = null;
        $totals = null;
        $filters = $this->resolveCommitmentFilter($request, null);
        $program = null;
        $funders = collect();

        if ($programId) {
            $program = Program::with([
                'sector',
                'projects.activities.subActivities.allocations',
                'approvedFundings.funder',
                'fundings.funder',
            ])->findOrFail($programId);
            $this->assertProgramReportScope($program);

            $filters = $this->resolveCommitmentFilter($request, $program);

            $fundings = $program->approvedFundings;
            if ($fundings->isEmpty()) {
                $fundings = $program->fundings;
            }
            if ($fundings->isEmpty()) {
                $fundings = ProgramFunding::query()
                    ->where('program_name', $program->name)
                    ->get();
            }

            $funders = $fundings->pluck('funder')->filter()->unique('id')->values();

            $fundingIds = $fundings->pluck('id')->all();

            $commitments = BudgetCommitment::with('purchaseRequest')
                ->whereIn('program_funding_id', $fundingIds)
                ->where('allocation_level', 'sub_activity')
                ->get();

            $filteredCommitments = $commitments->filter(function ($commitment) use ($filters) {
                $date = $this->resolveCommitmentDate($commitment);
                if (!$filters['start_date'] || !$filters['end_date']) {
                    return true;
                }

                return $date->between($filters['start_date'], $filters['end_date']);
            });

            $commitmentBySub = $filteredCommitments
                ->groupBy('allocation_id')
                ->map(fn ($rows) => round((float) $rows->sum('commitment_amount'), 2))
                ->all();

            $commitmentReferencesBySub = $filteredCommitments
                ->groupBy('allocation_id')
                ->map(function ($rows) {
                    return $rows->map(function ($commitment) {
                        return $commitment->purchaseRequest?->reference_no;
                    })->filter()->unique()->values()->all();
                })
                ->all();

            $commitmentBySubYear = [];
            foreach ($filteredCommitments as $commitment) {
                $year = $this->resolveCommitmentDate($commitment)->year;
                if (!in_array($year, $filters['year_range'], true)) {
                    continue;
                }
                $commitmentBySubYear[$commitment->allocation_id][$year] = ($commitmentBySubYear[$commitment->allocation_id][$year] ?? 0)
                    + (float) $commitment->commitment_amount;
            }

            $report = $this->buildCommitmentHierarchy(
                $program,
                $commitmentBySub,
                $commitmentReferencesBySub,
                $commitmentBySubYear,
                $filters['year_range']
            );
            $totals = $this->summarizeCommitmentTotals($report);
            $chartData = $this->buildCommitmentCharts(
                $filteredCommitments,
                $program,
                $filters['year_range'],
                $filters['mode'],
                $filters['start_date'],
                $filters['end_date']
            );
            $summary = $this->buildCommitmentSummary($totals, $report, $filters['label']);
        }

        return view('budgetreport.commitments', [
            'programs' => $programs,
            'program' => $program,
            'funders' => $funders,
            'report' => $report,
            'summary' => $summary,
            'totals' => $totals,
            'chartData' => $chartData,
            'filters' => $filters,
            'query' => $request->query(),
        ]);
    }

    public function exportCommitmentPdf(Request $request)
    {
        $data = $this->buildCommitmentExportData($request);
        $data['chartImages'] = [
            'line' => $request->input('chart_line'),
            'bar' => $request->input('chart_bar'),
            'bubble' => $request->input('chart_bubble'),
        ];
        $pdf = PDF::loadView('budgetreport.commitments_pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->download('commitment-report-' . ($data['program']?->id ?? 'program') . '.pdf');
    }

    public function exportCommitmentExcel(Request $request)
    {
        $data = $this->buildCommitmentExportData($request);
        $export = new CommitmentReportExport($data['rows'], $data['totals'], $data['program'], $data['filters']['year_range']);

        return Excel::download($export, 'commitment-report-' . ($data['program']?->id ?? 'program') . '.xlsx');
    }

    private function buildCommitmentExportData(Request $request): array
    {
        $programId = $request->input('program_id');
        if (!$programId) {
            abort(400, 'Program is required for export.');
        }

        $program = Program::with([
            'sector',
            'projects.activities.subActivities.allocations',
            'approvedFundings.funder',
            'fundings.funder',
        ])->findOrFail($programId);
        $this->assertProgramReportScope($program);

        $filters = $this->resolveCommitmentFilter($request, $program);

        $fundings = $program->approvedFundings;
        if ($fundings->isEmpty()) {
            $fundings = $program->fundings;
        }
        if ($fundings->isEmpty()) {
            $fundings = ProgramFunding::query()
                ->where('program_name', $program->name)
                ->get();
        }

        $fundingIds = $fundings->pluck('id')->all();

        $commitments = BudgetCommitment::with('purchaseRequest.items')
            ->whereIn('program_funding_id', $fundingIds)
            ->where('allocation_level', 'sub_activity')
            ->get();

        $filteredCommitments = $commitments->filter(function ($commitment) use ($filters) {
            $date = $this->resolveCommitmentDate($commitment);
            if (!$filters['start_date'] || !$filters['end_date']) {
                return true;
            }

            return $date->between($filters['start_date'], $filters['end_date']);
        });

        $commitmentBySub = $filteredCommitments
            ->groupBy('allocation_id')
            ->map(fn ($rows) => round((float) $rows->sum('commitment_amount'), 2))
            ->all();

        $commitmentReferencesBySub = $filteredCommitments
            ->groupBy('allocation_id')
            ->map(function ($rows) {
                return $rows->map(function ($commitment) {
                    return $commitment->purchaseRequest?->reference_no;
                })->filter()->unique()->values()->all();
            })
            ->all();

        $commitmentBySubYear = [];
        foreach ($filteredCommitments as $commitment) {
            $year = $this->resolveCommitmentDate($commitment)->year;
            if (!in_array($year, $filters['year_range'], true)) {
                continue;
            }
            $commitmentBySubYear[$commitment->allocation_id][$year] = ($commitmentBySubYear[$commitment->allocation_id][$year] ?? 0)
                + (float) $commitment->commitment_amount;
        }

        $rows = $this->buildCommitmentHierarchy(
            $program,
            $commitmentBySub,
            $commitmentReferencesBySub,
            $commitmentBySubYear,
            $filters['year_range']
        );
        $totals = $this->summarizeCommitmentTotals($rows);
        $chartData = $this->buildCommitmentCharts(
            $filteredCommitments,
            $program,
            $filters['year_range'],
            $filters['mode'],
            $filters['start_date'],
            $filters['end_date']
        );
        $summary = $this->buildCommitmentSummary($totals, $rows, $filters['label']);

        return [
            'program' => $program,
            'rows' => $rows,
            'totals' => $totals,
            'filters' => $filters,
            'chartData' => $chartData,
            'summary' => $summary,
        ];
    }

    /* ================================
       IFR REPORT
    ================================== */
    public function ifrReport(Request $request)
    {
        $programs = $this->scopedProgramReportQuery()->orderBy('name')->get();
        $programId = $request->input('program_id');

        $report = null;
        $chartData = null;
        $summary = null;
        $totals = null;
        $filters = $this->resolveCommitmentFilter($request, null);
        $program = null;
        $funders = collect();

        if ($programId) {
            $program = Program::with([
                'sector',
                'projects.activities.subActivities.allocations',
                'approvedFundings.funder',
                'fundings.funder',
            ])->findOrFail($programId);
            $this->assertProgramReportScope($program);

            $filters = $this->resolveCommitmentFilter($request, $program);

            $fundings = $program->approvedFundings;
            if ($fundings->isEmpty()) {
                $fundings = $program->fundings;
            }
            if ($fundings->isEmpty()) {
                $fundings = ProgramFunding::query()
                    ->where('program_name', $program->name)
                    ->get();
            }

            $funders = $fundings->pluck('funder')->filter()->unique('id')->values();
            $fundingIds = $fundings->pluck('id')->all();

            $subActivityIds = $program->projects
                ->flatMap(fn ($project) => $project->activities
                    ->flatMap(fn ($activity) => $activity->subActivities->pluck('id')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $commitments = $this->buildIfrCommitmentFacts($program, $fundingIds, $subActivityIds);

            $disbursements = empty($subActivityIds)
                ? collect()
                : ProcurementDisbursement::whereIn('sub_activity_id', $subActivityIds)
                    ->recognizedPayment()
                    ->get();

            $filteredDisbursements = $disbursements->filter(function ($disbursement) use ($filters) {
                $date = $this->resolveDisbursementDate($disbursement);
                if (!$filters['start_date'] || !$filters['end_date']) {
                    return true;
                }

                return $date->between($filters['start_date'], $filters['end_date']);
            });

            $globalCommitmentBySub = $this->buildIfrGlobalBudgetBySubActivity($program);
            $plannedCommitmentBySub = $commitments
                ->groupBy('sub_activity_id')
                ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))
                ->all();

            $commitmentReferencesBySub = $commitments
                ->groupBy('sub_activity_id')
                ->map(function ($rows) {
                    return $rows->map(function ($commitment) {
                        return $commitment['reference'];
                    })->filter()->unique()->values()->all();
                })
                ->all();

            $disbursementBySub = $filteredDisbursements
                ->groupBy('sub_activity_id')
                ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))
                ->all();

            $disbursementBySubYear = [];
            foreach ($filteredDisbursements as $disbursement) {
                $year = $this->resolveDisbursementDate($disbursement)->year;
                if (!in_array($year, $filters['year_range'], true)) {
                    continue;
                }
                $disbursementBySubYear[$disbursement->sub_activity_id][$year] = ($disbursementBySubYear[$disbursement->sub_activity_id][$year] ?? 0)
                    + (float) $disbursement->amount;
            }

            $report = $this->buildIfrHierarchy(
                $program,
                $globalCommitmentBySub,
                $plannedCommitmentBySub,
                $disbursementBySub,
                $commitmentReferencesBySub,
                $disbursementBySubYear,
                $filters['year_range']
            );
            $totals = $this->summarizeIfrTotals($report);
            $chartData = $this->buildIfrCharts(
                $globalCommitmentBySub,
                $plannedCommitmentBySub,
                $filteredDisbursements,
                $program,
                $filters['year_range'],
                $filters['start_date'],
                $filters['end_date']
            );
            $summary = $this->buildIfrSummary($totals, $report, $filters['label']);
        }

        return view('budgetreport.ifr', [
            'programs' => $programs,
            'program' => $program,
            'funders' => $funders,
            'report' => $report,
            'summary' => $summary,
            'totals' => $totals,
            'chartData' => $chartData,
            'filters' => $filters,
            'query' => $request->query(),
        ]);
    }

    public function commitmentDisbursementReport(Request $request)
    {
        return $this->ifrReport($request)
            ->with('reportMeta', $this->commitmentDisbursementReportMeta());
    }

    /* ================================
       PROJECT FINANCIAL POSITION
    ================================== */
    public function projectFinancialPosition(Request $request)
    {
        $data = $this->buildProjectFinancialPositionReportData($request);

        if ($data['program']) {
            $this->auditReportAction('budget.project_financial_position.viewed', 'Project financial position report viewed', [
                'program_id' => $data['program']->id,
                'program_name' => $data['program']->name,
                'filters' => collect($data['filters'])->except(['start_date', 'end_date'])->all(),
            ]);
        }

        return view('budgetreport.project-financial-position', $data);
    }

    public function exportProjectFinancialPositionPdf(Request $request)
    {
        $data = $this->buildProjectFinancialPositionReportData($request);

        abort_if(! $data['program'] || ! $data['position'], 400, 'Select a program before exporting the financial position report.');

        $this->auditReportAction('budget.project_financial_position.pdf_exported', 'Project financial position PDF exported', [
            'program_id' => $data['program']->id,
            'program_name' => $data['program']->name,
            'filters' => collect($data['filters'])->except(['start_date', 'end_date'])->all(),
        ]);

        $filename = 'project-financial-position-'
            . ($data['program']->program_id ?: $data['program']->id)
            . '-' . $data['reportGeneratedAt']->format('Ymd-His') . '.pdf';

        return PDF::loadView('budgetreport.project-financial-position-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function buildProjectFinancialPositionReportData(Request $request): array
    {
        $requestedReportTimezone = substr(
            trim((string) $request->input('report_timezone', config('app.timezone', 'UTC'))),
            0,
            120
        );
        try {
            $reportTimezone = (new \DateTimeZone($requestedReportTimezone ?: 'UTC'))->getName();
        } catch (Throwable) {
            $reportTimezone = 'UTC';
        }

        $reportGeneratedAt = Carbon::now('UTC')->setTimezone($reportTimezone);

        $programs = $this->scopedProgramReportQuery()->orderBy('name')->get();
        $selectedProgramId = $request->input('program_id') ?: $programs->first()?->id;
        $program = null;
        $position = null;
        $executionDashboard = [];
        $funders = collect();
        $fundingOptions = collect();
        $structureOptions = [
            'projects' => collect(),
            'activities' => collect(),
            'subActivities' => collect(),
        ];
        $structureFilterLabel = 'All projects, activities, and sub-activities';
        $filters = $this->resolveProjectFinancialPositionFilters($request, null);

        if ($selectedProgramId) {
            $program = Program::with([
                'sector',
                'projects.allocations',
                'projects.activities.allocations',
                'projects.activities.subActivities.allocations',
            'approvedFundings.funder',
            'fundings.funder',
        ])->findOrFail($selectedProgramId);
            $this->assertProgramReportScope($program);

            $filters = $this->resolveProjectFinancialPositionFilters($request, $program);
            $structureOptions = $this->buildProjectFinancialPositionStructureOptions($program);
            $structureFilterLabel = $this->projectFinancialPositionStructureFilterLabel($program, $filters);

            $fundingOptions = $program->approvedFundings;
            if ($fundingOptions->isEmpty()) {
                $fundingOptions = $program->fundings;
            }
            if ($fundingOptions->isEmpty()) {
                $fundingOptions = ProgramFunding::query()
                    ->where('program_name', $program->name)
                    ->get();
            }

            $fundings = $fundingOptions;
            if (! empty($filters['funding_id'])) {
                $fundings = $fundings->where('id', $filters['funding_id'])->values();
            }

            $funders = $fundings->pluck('funder')->filter()->unique('id')->values();
            $dashboardRequest = Request::create(
                '/finance/execution/dashboard',
                'GET',
                ['program_id' => $program->id]
            );
            $dashboardRequest->setUserResolver(fn () => $request->user());
            $executionDashboard = app(MasterDashboard::class)
                ->executionDashboardPayload($dashboardRequest);
            $executionDashboard['executionChartImages'] = app(ExecutionDashboardChartBuilder::class)
                ->buildFromDataset(
                    $executionDashboard['executionChartData'] ?? [],
                    $executionDashboard['currency'] ?? 'USD'
                );
            $position = $this->buildProjectFinancialPosition(
                $program,
                $fundings->pluck('id')->all(),
                $filters,
                $executionDashboard
            );
        }

        return [
            'programs' => $programs,
            'selectedProgramId' => $selectedProgramId,
            'program' => $program,
            'position' => $position,
            'executionDashboard' => $executionDashboard,
            'funders' => $funders,
            'fundingOptions' => $fundingOptions,
            'structureOptions' => $structureOptions,
            'structureFilterLabel' => $structureFilterLabel,
            'filters' => $filters,
            'query' => $request->query(),
            'reportGeneratedAt' => $reportGeneratedAt,
            'reportTimezone' => $reportTimezone,
        ];
    }

    public function exportIfrPdf(Request $request)
    {
        $data = $this->buildIfrExportData($request);
        $data['chartImages'] = [
            'line' => $request->input('chart_line'),
            'bar' => $request->input('chart_bar'),
            'bubble' => $request->input('chart_bubble'),
        ];
        $pdf = PDF::loadView('budgetreport.ifr_pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->download('ifr-report-' . ($data['program']?->id ?? 'program') . '.pdf');
    }

    public function exportIfrExcel(Request $request)
    {
        $data = $this->buildIfrExportData($request);
        $export = new InterimFinancialReportExport($data['rows'], $data['totals'], $data['program'], $data['filters']['year_range']);

        return Excel::download($export, 'ifr-report-' . ($data['program']?->id ?? 'program') . '.xlsx');
    }

    public function exportCommitmentDisbursementPdf(Request $request)
    {
        $data = $this->buildIfrExportData($request);
        $data['chartImages'] = [
            'line' => $request->input('chart_line'),
            'bar' => $request->input('chart_bar'),
            'bubble' => $request->input('chart_bubble'),
        ];
        $data['reportMeta'] = $this->commitmentDisbursementReportMeta();

        $pdf = PDF::loadView('budgetreport.ifr_pdf', $data)->setPaper('a4', 'landscape');

        return $pdf->download('commitment-disbursement-report-' . ($data['program']?->id ?? 'program') . '.pdf');
    }

    public function exportCommitmentDisbursementExcel(Request $request)
    {
        $data = $this->buildIfrExportData($request);
        $export = new InterimFinancialReportExport($data['rows'], $data['totals'], $data['program'], $data['filters']['year_range']);

        return Excel::download($export, 'commitment-disbursement-report-' . ($data['program']?->id ?? 'program') . '.xlsx');
    }

    private function commitmentDisbursementReportMeta(): array
    {
        return [
            'title' => 'Commitment and Disbursement Report',
            'description' => 'Global commitments from the budget structure, planned commitments from purchase requests, and fully paid disbursement trends by program structure.',
            'form_route' => 'budget.reports.commitment-disbursement',
            'pdf_route' => 'budget.reports.commitment-disbursement.export.pdf',
            'excel_route' => 'budget.reports.commitment-disbursement.export.excel',
            'empty_message' => 'Select a program and filter range to generate the commitment and disbursement report.',
            'section_title' => 'Section 1: Commitment and Disbursement Balance Sheet',
            'period_label' => 'Commitment and Disbursement Report',
            'summary_title' => 'Section 3: Commitment and Disbursement Summary',
        ];
    }

    private function buildIfrExportData(Request $request): array
    {
        $programId = $request->input('program_id');
        if (!$programId) {
            abort(400, 'Program is required for export.');
        }

        $program = Program::with([
            'sector',
            'projects.activities.subActivities.allocations',
            'approvedFundings.funder',
            'fundings.funder',
        ])->findOrFail($programId);
        $this->assertProgramReportScope($program);

        $filters = $this->resolveCommitmentFilter($request, $program);

        $fundings = $program->approvedFundings;
        if ($fundings->isEmpty()) {
            $fundings = $program->fundings;
        }
        if ($fundings->isEmpty()) {
            $fundings = ProgramFunding::query()
                ->where('program_name', $program->name)
                ->get();
        }

        $fundingIds = $fundings->pluck('id')->all();

        $subActivityIds = $program->projects
            ->flatMap(fn ($project) => $project->activities
                ->flatMap(fn ($activity) => $activity->subActivities->pluck('id')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $commitments = $this->buildIfrCommitmentFacts($program, $fundingIds, $subActivityIds);

        $disbursements = empty($subActivityIds)
            ? collect()
            : ProcurementDisbursement::whereIn('sub_activity_id', $subActivityIds)
                ->recognizedPayment()
                ->get();

        $filteredDisbursements = $disbursements->filter(function ($disbursement) use ($filters) {
            $date = $this->resolveDisbursementDate($disbursement);
            if (!$filters['start_date'] || !$filters['end_date']) {
                return true;
            }

            return $date->between($filters['start_date'], $filters['end_date']);
        });

        $globalCommitmentBySub = $this->buildIfrGlobalBudgetBySubActivity($program);
        $plannedCommitmentBySub = $commitments
            ->groupBy('sub_activity_id')
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))
            ->all();

        $commitmentReferencesBySub = $commitments
            ->groupBy('sub_activity_id')
            ->map(function ($rows) {
                return $rows->map(function ($commitment) {
                    return $commitment['reference'];
                })->filter()->unique()->values()->all();
            })
            ->all();

        $disbursementBySub = $filteredDisbursements
            ->groupBy('sub_activity_id')
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))
            ->all();

        $disbursementBySubYear = [];
        foreach ($filteredDisbursements as $disbursement) {
            $year = $this->resolveDisbursementDate($disbursement)->year;
            if (!in_array($year, $filters['year_range'], true)) {
                continue;
            }
            $disbursementBySubYear[$disbursement->sub_activity_id][$year] = ($disbursementBySubYear[$disbursement->sub_activity_id][$year] ?? 0)
                + (float) $disbursement->amount;
        }

        $rows = $this->buildIfrHierarchy(
            $program,
            $globalCommitmentBySub,
            $plannedCommitmentBySub,
            $disbursementBySub,
            $commitmentReferencesBySub,
            $disbursementBySubYear,
            $filters['year_range']
        );
        $totals = $this->summarizeIfrTotals($rows);
        $chartData = $this->buildIfrCharts(
            $globalCommitmentBySub,
            $plannedCommitmentBySub,
            $filteredDisbursements,
            $program,
            $filters['year_range'],
            $filters['start_date'],
            $filters['end_date']
        );
        $summary = $this->buildIfrSummary($totals, $rows, $filters['label']);

        return [
            'program' => $program,
            'rows' => $rows,
            'totals' => $totals,
            'filters' => $filters,
            'chartData' => $chartData,
            'summary' => $summary,
        ];
    }

    private function resolveCommitmentFilter(Request $request, ?Program $program): array
    {
        $mode = $request->input('filter_mode', 'multi_year');
        $startDate = null;
        $endDate = null;
        $label = '';

        // Derive sensible defaults from program / data
        $projectYears = collect($program?->projects ?? [])->flatMap(function ($p) {
            return [$p->start_year, $p->end_year];
        })->filter()->values();

        $allocationYears = collect($program?->projects ?? [])
            ->flatMap(fn($p) => $p->activities)
            ->flatMap(fn($a) => $a->subActivities)
            ->flatMap(fn($s) => $s->allocations->pluck('year'))
            ->filter()
            ->values();

        $defaultStartYear = $program?->start_year
            ?? $projectYears->min()
            ?? $allocationYears->min()
            ?? now()->year;

        $defaultEndYear = $program?->end_year
            ?? $projectYears->max()
            ?? $allocationYears->max()
            ?? $defaultStartYear;

        if ($mode === 'range') {
            $startDate = $request->input('start_date')
                ? Carbon::parse($request->input('start_date'))->startOfDay()
                : Carbon::create($defaultStartYear, 1, 1);
            $endDate = $request->input('end_date')
                ? Carbon::parse($request->input('end_date'))->endOfDay()
                : Carbon::create($defaultEndYear, 12, 31);
            $label = $startDate->format('M j, Y') . ' - ' . $endDate->format('M j, Y');
        } elseif ($mode === 'yearly') {
            $year = (int) $request->input('year', $defaultStartYear);
            $startDate = Carbon::create($year, 1, 1);
            $endDate = Carbon::create($year, 12, 31);
            $label = 'Year ' . $year;
        } elseif ($mode === 'quarterly') {
            $year = (int) $request->input('year', $defaultStartYear);
            $quarter = (int) $request->input('quarter', 1);
            $month = (($quarter - 1) * 3) + 1;
            $startDate = Carbon::create($year, $month, 1);
            $endDate = $startDate->copy()->addMonths(3)->subDay();
            $label = 'Q' . $quarter . ' ' . $year;
        } elseif ($mode === 'semiannual') {
            $year = (int) $request->input('year', $defaultStartYear);
            $half = (int) $request->input('half', 1);
            $month = $half === 2 ? 7 : 1;
            $startDate = Carbon::create($year, $month, 1);
            $endDate = $startDate->copy()->addMonths(6)->subDay();
            $label = ($half === 2 ? 'H2 ' : 'H1 ') . $year;
        } else {
            $startYear = (int) $request->input('start_year', $defaultStartYear);
            $endYear = (int) $request->input('end_year', $defaultEndYear);

            if ($endYear < $startYear) {
                [$startYear, $endYear] = [$endYear, $startYear];
            }

            $startDate = Carbon::create($startYear, 1, 1);
            $endDate = Carbon::create($endYear, 12, 31);
            $label = $startYear === $endYear
                ? 'Year ' . $startYear
                : $startYear . ' - ' . $endYear;
            $mode = 'multi_year';
        }

        $yearRange = range($startDate->year, $endDate->year);

        return [
            'mode' => $mode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'label' => $label,
            'year_range' => $yearRange,
            'start_year' => $startDate->year,
            'end_year' => $endDate->year,
        ];
    }

    private function resolveCommitmentDate(BudgetCommitment $commitment): Carbon
    {
        if (!empty($commitment->commitment_year)) {
            return Carbon::create((int) $commitment->commitment_year, 1, 1);
        }

        if (!empty($commitment->purchaseRequest?->start_year)) {
            return Carbon::create((int) $commitment->purchaseRequest->start_year, 1, 1);
        }

        if ($commitment->purchaseRequest?->commitment_date) {
            return Carbon::parse($commitment->purchaseRequest->commitment_date)->startOfDay();
        }

        if ($commitment->approved_at) {
            return Carbon::parse($commitment->approved_at)->startOfDay();
        }

        if ($commitment->purchaseRequest?->approved_at) {
            return Carbon::parse($commitment->purchaseRequest->approved_at)->startOfDay();
        }

        if ($commitment->purchaseRequest?->created_at) {
            return Carbon::parse($commitment->purchaseRequest->created_at)->startOfDay();
        }

        return now()->startOfDay();
    }

    private function buildIfrCommitmentFacts(Program $program, array $fundingIds, array $subActivityIds)
    {
        if (empty($fundingIds) && empty($subActivityIds)) {
            return collect();
        }

        $subActivityLookup = collect($subActivityIds)
            ->mapWithKeys(fn ($id) => [(string) $id => true])
            ->all();

        $commitments = BudgetCommitment::with('purchaseRequest.items')
            ->where(function ($query) use ($fundingIds, $subActivityIds) {
                $query->whereRaw('1 = 0');

                if (!empty($fundingIds)) {
                    $query->orWhereIn('program_funding_id', $fundingIds);
                }

                if (!empty($subActivityIds)) {
                    $query->orWhere(function ($subQuery) use ($subActivityIds) {
                        $subQuery->where('allocation_level', 'sub_activity')
                            ->whereIn('allocation_id', $subActivityIds);
                    });

                    $query->orWhereHas('purchaseRequest', function ($requestQuery) use ($subActivityIds) {
                        $requestQuery->where('allocation_level', 'sub_activity')
                            ->whereIn('allocation_id', $subActivityIds);
                    });
                }
            })
            ->where(function ($query) {
                $query->whereNull('status')
                    ->orWhereNotIn('status', [BudgetCommitment::STATUS_CANCELLED, 'rejected', 'void']);
            })
            ->get();

        $purchaseRequests = empty($subActivityIds)
            ? collect()
            : PurchaseRequest::with(['commitments', 'items'])
                ->where('allocation_level', 'sub_activity')
                ->whereIn('allocation_id', $subActivityIds)
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhereNotIn('status', ['cancelled', 'void', 'rejected', 'failed']);
                })
                ->get();

        $purchaseRequestIds = $purchaseRequests
            ->pluck('id')
            ->merge($commitments->pluck('purchase_request_id'))
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
        $commitmentIds = $commitments
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $purchaseOrders = (empty($commitmentIds) && empty($purchaseRequestIds) && empty($subActivityIds))
            ? collect()
            : ProcurementPurchaseOrder::with(['budgetCommitment.purchaseRequest', 'purchaseRequest'])
                ->where(function ($query) use ($commitmentIds, $purchaseRequestIds, $subActivityIds) {
                    $query->whereRaw('1 = 0');

                    if (!empty($commitmentIds)) {
                        $query->orWhereIn('budget_commitment_id', $commitmentIds);
                    }

                    if (!empty($purchaseRequestIds)) {
                        $query->orWhereIn('purchase_request_id', $purchaseRequestIds);
                    }

                    if (!empty($subActivityIds)) {
                        $query->orWhereIn('sub_activity_id', $subActivityIds)
                            ->orWhereHas('budgetCommitment', function ($commitmentQuery) use ($subActivityIds) {
                                $commitmentQuery->where('allocation_level', 'sub_activity')
                                    ->whereIn('allocation_id', $subActivityIds);
                            })
                            ->orWhereHas('purchaseRequest', function ($requestQuery) use ($subActivityIds) {
                                $requestQuery->where('allocation_level', 'sub_activity')
                                    ->whereIn('allocation_id', $subActivityIds);
                            })
                            ->orWhereHas('budgetCommitment.purchaseRequest', function ($requestQuery) use ($subActivityIds) {
                                $requestQuery->where('allocation_level', 'sub_activity')
                                    ->whereIn('allocation_id', $subActivityIds);
                            });
                    }
                })
                ->where(function ($query) {
                    $query->whereNull('status')
                        ->orWhereNotIn('status', ['cancelled', 'void', 'rejected']);
                })
                ->get();

        $facts = [];
        $coveredCommitmentIds = [];
        $coveredPurchaseRequestIds = [];
        $positivePurchaseRequestIds = $commitments
            ->filter(fn (BudgetCommitment $commitment) => (float) ($commitment->commitment_amount ?? 0) > 0 && !empty($commitment->purchase_request_id))
            ->pluck('purchase_request_id')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->all();
        $positivePurchaseRequestLookup = array_fill_keys($positivePurchaseRequestIds, true);

        foreach ($commitments as $commitment) {
            $subActivityId = $this->resolveIfrCommitmentSubActivityId($commitment);
            if (!$this->ifrSubActivityInScope($subActivityId, $subActivityLookup)) {
                continue;
            }

            $purchaseRequestId = $commitment->purchase_request_id ? (string) $commitment->purchase_request_id : null;
            $amount = (float) ($commitment->commitment_amount ?? 0);

            if ($amount <= 0 && $purchaseRequestId && isset($positivePurchaseRequestLookup[$purchaseRequestId])) {
                continue;
            }

            if ($amount <= 0) {
                $amount = (float) ($commitment->purchaseRequest?->total_amount ?? 0);
            }

            if ($amount <= 0 && $commitment->purchaseRequest?->relationLoaded('items')) {
                $amount = (float) $commitment->purchaseRequest->items->sum('amount');
            }

            if ($amount <= 0) {
                continue;
            }

            $this->addIfrCommitmentFact(
                $facts,
                'commitment',
                $commitment->id,
                $subActivityId,
                $amount,
                $this->resolveCommitmentDate($commitment),
                $commitment->purchaseRequest?->reference_no
            );

            $coveredCommitmentIds[(string) $commitment->id] = true;
            if ($purchaseRequestId) {
                $coveredPurchaseRequestIds[$purchaseRequestId] = true;
            }
        }

        foreach ($purchaseRequests as $purchaseRequest) {
            $purchaseRequestId = (string) $purchaseRequest->id;
            if (isset($coveredPurchaseRequestIds[$purchaseRequestId])) {
                continue;
            }

            $subActivityId = $this->resolveIfrPurchaseRequestSubActivityId($purchaseRequest);
            if (!$this->ifrSubActivityInScope($subActivityId, $subActivityLookup)) {
                continue;
            }

            $amount = (float) ($purchaseRequest->total_amount ?? 0);
            if ($amount <= 0 && $purchaseRequest->relationLoaded('items')) {
                $amount = (float) $purchaseRequest->items->sum('amount');
            }

            if ($amount <= 0) {
                continue;
            }

            $this->addIfrCommitmentFact(
                $facts,
                'purchase_request',
                $purchaseRequest->id,
                $subActivityId,
                $amount,
                $this->resolveIfrPurchaseRequestDate($purchaseRequest),
                $purchaseRequest->reference_no
            );

            $coveredPurchaseRequestIds[$purchaseRequestId] = true;
        }

        foreach ($purchaseOrders as $purchaseOrder) {
            $commitmentId = $purchaseOrder->budget_commitment_id ? (string) $purchaseOrder->budget_commitment_id : null;
            $purchaseRequest = $purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest;
            $purchaseRequestId = $purchaseRequest?->id ? (string) $purchaseRequest->id : null;

            if (($commitmentId && isset($coveredCommitmentIds[$commitmentId]))
                || ($purchaseRequestId && isset($coveredPurchaseRequestIds[$purchaseRequestId]))) {
                continue;
            }

            $subActivityId = $this->resolveIfrPurchaseOrderSubActivityId($purchaseOrder);
            if (!$this->ifrSubActivityInScope($subActivityId, $subActivityLookup)) {
                continue;
            }

            $amount = (float) ($purchaseOrder->amount ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $this->addIfrCommitmentFact(
                $facts,
                'purchase_order',
                $purchaseOrder->id,
                $subActivityId,
                $amount,
                $this->resolveIfrPurchaseOrderDate($purchaseOrder),
                $purchaseOrder->reference_no ?: $purchaseRequest?->reference_no
            );

            if ($commitmentId) {
                $coveredCommitmentIds[$commitmentId] = true;
            }
            if ($purchaseRequestId) {
                $coveredPurchaseRequestIds[$purchaseRequestId] = true;
            }
        }

        return collect($facts)->values();
    }

    private function addIfrCommitmentFact(
        array &$facts,
        string $sourceType,
        $sourceId,
        ?string $subActivityId,
        float $amount,
        Carbon $date,
        ?string $reference
    ): void {
        if ($sourceId === null || $subActivityId === null || $amount <= 0) {
            return;
        }

        $key = $sourceType . ':' . (string) $sourceId;
        if (isset($facts[$key])) {
            return;
        }

        $facts[$key] = [
            'source_type' => $sourceType,
            'source_id' => (string) $sourceId,
            'sub_activity_id' => (string) $subActivityId,
            'amount' => round($amount, 2),
            'date' => $date->copy()->startOfDay(),
            'reference' => $reference,
        ];
    }

    private function resolveIfrCommitmentSubActivityId(BudgetCommitment $commitment): ?string
    {
        if ($commitment->allocation_level === 'sub_activity' && !empty($commitment->allocation_id)) {
            return (string) $commitment->allocation_id;
        }

        return $this->resolveIfrPurchaseRequestSubActivityId($commitment->purchaseRequest);
    }

    private function resolveIfrPurchaseRequestSubActivityId(?PurchaseRequest $purchaseRequest): ?string
    {
        if ($purchaseRequest?->allocation_level === 'sub_activity' && !empty($purchaseRequest->allocation_id)) {
            return (string) $purchaseRequest->allocation_id;
        }

        return null;
    }

    private function resolveIfrPurchaseOrderSubActivityId(ProcurementPurchaseOrder $purchaseOrder): ?string
    {
        if (!empty($purchaseOrder->sub_activity_id)) {
            return (string) $purchaseOrder->sub_activity_id;
        }

        if ($purchaseOrder->budgetCommitment?->allocation_level === 'sub_activity' && !empty($purchaseOrder->budgetCommitment->allocation_id)) {
            return (string) $purchaseOrder->budgetCommitment->allocation_id;
        }

        return $this->resolveIfrPurchaseRequestSubActivityId($purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest);
    }

    private function ifrSubActivityInScope(?string $subActivityId, array $subActivityLookup): bool
    {
        return $subActivityId !== null && isset($subActivityLookup[(string) $subActivityId]);
    }

    private function resolveIfrPurchaseRequestDate(?PurchaseRequest $purchaseRequest): Carbon
    {
        if (!$purchaseRequest) {
            return now()->startOfDay();
        }

        if (!empty($purchaseRequest->start_year)) {
            return Carbon::create((int) $purchaseRequest->start_year, 1, 1);
        }

        if ($purchaseRequest->commitment_date) {
            return Carbon::parse($purchaseRequest->commitment_date)->startOfDay();
        }

        if ($purchaseRequest->approved_at) {
            return Carbon::parse($purchaseRequest->approved_at)->startOfDay();
        }

        if ($purchaseRequest->created_at) {
            return Carbon::parse($purchaseRequest->created_at)->startOfDay();
        }

        return now()->startOfDay();
    }

    private function resolveIfrPurchaseOrderDate(ProcurementPurchaseOrder $purchaseOrder): Carbon
    {
        if ($purchaseOrder->budgetCommitment) {
            return $this->resolveCommitmentDate($purchaseOrder->budgetCommitment);
        }

        $purchaseRequest = $purchaseOrder->purchaseRequest;
        if ($purchaseRequest) {
            return $this->resolveIfrPurchaseRequestDate($purchaseRequest);
        }

        if ($purchaseOrder->issued_at) {
            return Carbon::parse($purchaseOrder->issued_at)->startOfDay();
        }

        if ($purchaseOrder->created_at) {
            return Carbon::parse($purchaseOrder->created_at)->startOfDay();
        }

        return now()->startOfDay();
    }

    private function resolveProjectFinancialPositionFilters(Request $request, ?Program $program): array
    {
        $mode = $request->input('filter_mode', 'life_to_date');
        $allowedModes = ['life_to_date', 'multi_year', 'yearly', 'quarterly', 'semiannual', 'range'];
        if (! in_array($mode, $allowedModes, true)) {
            $mode = 'life_to_date';
        }

        $projectYears = collect($program?->projects ?? [])->flatMap(fn ($project) => [$project->start_year, $project->end_year]);
        $allocationYears = collect($program?->projects ?? [])
            ->flatMap(fn ($project) => $project->activities)
            ->flatMap(fn ($activity) => $activity->subActivities)
            ->flatMap(fn ($subActivity) => $subActivity->allocations->pluck('year'));

        $defaultStartYear = (int) ($program?->start_year
            ?? $projectYears->filter()->min()
            ?? $allocationYears->filter()->min()
            ?? now()->year);
        $defaultEndYear = (int) ($program?->end_year
            ?? $projectYears->filter()->max()
            ?? $allocationYears->filter()->max()
            ?? $defaultStartYear);

        $startDate = null;
        $endDate = null;
        $label = 'Life to date';
        $yearRange = range(min($defaultStartYear, $defaultEndYear), max($defaultStartYear, $defaultEndYear));

        if ($mode === 'life_to_date') {
            $startDate = Carbon::create(min($defaultStartYear, $defaultEndYear), 1, 1)->startOfDay();
            $endDate = Carbon::create(max($defaultStartYear, $defaultEndYear), 12, 31)->endOfDay();
            $label = min($defaultStartYear, $defaultEndYear) . ' - ' . max($defaultStartYear, $defaultEndYear)
                . ' | Executive dashboard period';
        } elseif ($mode === 'multi_year') {
            $startYear = (int) $request->input('start_year', $defaultStartYear);
            $endYear = (int) $request->input('end_year', $defaultEndYear);
            if ($endYear < $startYear) {
                [$startYear, $endYear] = [$endYear, $startYear];
            }
            $startDate = Carbon::create($startYear, 1, 1)->startOfDay();
            $endDate = Carbon::create($endYear, 12, 31)->endOfDay();
            $label = $startYear === $endYear ? 'Year ' . $startYear : $startYear . ' - ' . $endYear;
            $yearRange = range($startYear, $endYear);
        } elseif ($mode === 'yearly') {
            $year = (int) $request->input('year', $defaultStartYear);
            $startDate = Carbon::create($year, 1, 1)->startOfDay();
            $endDate = Carbon::create($year, 12, 31)->endOfDay();
            $label = 'Year ' . $year;
            $yearRange = [$year];
        } elseif ($mode === 'quarterly') {
            $year = (int) $request->input('year', $defaultStartYear);
            $quarter = max(1, min(4, (int) $request->input('quarter', 1)));
            $startDate = Carbon::create($year, (($quarter - 1) * 3) + 1, 1)->startOfDay();
            $endDate = $startDate->copy()->addMonths(3)->subDay()->endOfDay();
            $label = 'Q' . $quarter . ' ' . $year;
            $yearRange = [$year];
        } elseif ($mode === 'semiannual') {
            $year = (int) $request->input('year', $defaultStartYear);
            $half = (int) $request->input('half', 1) === 2 ? 2 : 1;
            $startDate = Carbon::create($year, $half === 2 ? 7 : 1, 1)->startOfDay();
            $endDate = $startDate->copy()->addMonths(6)->subDay()->endOfDay();
            $label = ($half === 2 ? 'H2 ' : 'H1 ') . $year;
            $yearRange = [$year];
        } elseif ($mode === 'range') {
            $startDate = $request->input('start_date')
                ? Carbon::parse($request->input('start_date'))->startOfDay()
                : Carbon::create($defaultStartYear, 1, 1)->startOfDay();
            $endDate = $request->input('end_date')
                ? Carbon::parse($request->input('end_date'))->endOfDay()
                : Carbon::create($defaultEndYear, 12, 31)->endOfDay();
            if ($endDate->lessThan($startDate)) {
                [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
            }
            $label = $startDate->format('M j, Y') . ' - ' . $endDate->format('M j, Y');
            $yearRange = range($startDate->year, $endDate->year);
        }

        $depth = $request->input('depth', 'sub_activity');
        if (! in_array($depth, ['project', 'activity', 'sub_activity'], true)) {
            $depth = 'sub_activity';
        }

        $focus = $request->input('focus', 'all');
        if (! in_array($focus, ['all', 'unpaid', 'over_committed', 'with_disbursement', 'with_invoice', 'no_activity'], true)) {
            $focus = 'all';
        }

        return [
            'mode' => $mode,
            'label' => $label,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'year_range' => $yearRange,
            'start_year' => $startDate?->year ?? $defaultStartYear,
            'end_year' => $endDate?->year ?? $defaultEndYear,
            'funding_id' => $request->input('funding_id'),
            'project_id' => $request->input('project_id'),
            'activity_id' => $request->input('activity_id'),
            'sub_activity_id' => $request->input('sub_activity_id'),
            'focus' => $focus,
            'depth' => $depth,
            'search' => trim((string) $request->input('search', '')),
            'include_zero' => $request->boolean('include_zero', true),
        ];
    }

    private function buildProjectFinancialPositionStructureOptions(Program $program): array
    {
        $projects = $program->projects->sortBy('name')->values();

        $activities = $projects
            ->flatMap(function (Project $project) {
                return $project->activities
                    ->sortBy('name')
                    ->map(fn (Activity $activity) => [
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                    ]);
            })
            ->values();

        $subActivities = $projects
            ->flatMap(function (Project $project) {
                return $project->activities
                    ->sortBy('name')
                    ->flatMap(function (Activity $activity) use ($project) {
                        return $activity->subActivities
                            ->sortBy('name')
                            ->map(fn ($subActivity) => [
                                'id' => $subActivity->id,
                                'name' => $subActivity->name,
                                'project_id' => $project->id,
                                'project_name' => $project->name,
                                'activity_id' => $activity->id,
                                'activity_name' => $activity->name,
                            ]);
                    });
            })
            ->values();

        return compact('projects', 'activities', 'subActivities');
    }

    private function projectFinancialPositionStructureFilterLabel(Program $program, array $filters): string
    {
        $parts = [];

        if (! empty($filters['project_id'])) {
            $project = $program->projects->first(fn (Project $project) => (string) $project->id === (string) $filters['project_id']);
            $parts[] = 'Project: ' . ($project?->name ?? 'Selected project');
        }

        if (! empty($filters['activity_id'])) {
            $activity = $program->projects
                ->flatMap(fn (Project $project) => $project->activities)
                ->first(fn (Activity $activity) => (string) $activity->id === (string) $filters['activity_id']);
            $parts[] = 'Activity: ' . ($activity?->name ?? 'Selected activity');
        }

        if (! empty($filters['sub_activity_id'])) {
            $subActivity = $program->projects
                ->flatMap(fn (Project $project) => $project->activities)
                ->flatMap(fn (Activity $activity) => $activity->subActivities)
                ->first(fn ($subActivity) => (string) $subActivity->id === (string) $filters['sub_activity_id']);
            $parts[] = 'Sub-Activity: ' . ($subActivity?->name ?? 'Selected sub-activity');
        }

        return empty($parts)
            ? 'All projects, activities, and sub-activities'
            : implode(' / ', $parts);
    }

    private function projectFinancialPositionStructureScope(Program $program, array $filters): array
    {
        $projectIds = collect();
        $activityIds = collect();
        $subActivityIds = collect();

        foreach ($program->projects as $project) {
            if (! $this->projectMatchesProjectPositionStructureFilters($project, $filters)) {
                continue;
            }

            $projectIds->push((string) $project->id);

            foreach ($project->activities as $activity) {
                if (! $this->activityMatchesProjectPositionStructureFilters($activity, $project, $filters)) {
                    continue;
                }

                $activityIds->push((string) $activity->id);

                foreach ($activity->subActivities as $subActivity) {
                    if ($this->subActivityMatchesProjectPositionStructureFilters($subActivity, $activity, $project, $filters)) {
                        $subActivityIds->push((string) $subActivity->id);
                    }
                }
            }
        }

        return [
            'project_ids' => $projectIds->filter()->unique()->values()->all(),
            'activity_ids' => $activityIds->filter()->unique()->values()->all(),
            'sub_activity_ids' => $subActivityIds->filter()->unique()->values()->all(),
        ];
    }

    private function projectMatchesProjectPositionStructureFilters(Project $project, array $filters): bool
    {
        if (! empty($filters['project_id']) && (string) $project->id !== (string) $filters['project_id']) {
            return false;
        }

        if (! empty($filters['activity_id'])) {
            return $project->activities->contains(fn (Activity $activity) => (string) $activity->id === (string) $filters['activity_id']);
        }

        if (! empty($filters['sub_activity_id'])) {
            return $project->activities->contains(function (Activity $activity) use ($filters) {
                return $activity->subActivities->contains(fn ($subActivity) => (string) $subActivity->id === (string) $filters['sub_activity_id']);
            });
        }

        return true;
    }

    private function activityMatchesProjectPositionStructureFilters(Activity $activity, Project $project, array $filters): bool
    {
        if (! empty($filters['project_id']) && (string) $project->id !== (string) $filters['project_id']) {
            return false;
        }

        if (! empty($filters['activity_id']) && (string) $activity->id !== (string) $filters['activity_id']) {
            return false;
        }

        if (! empty($filters['sub_activity_id'])) {
            return $activity->subActivities->contains(fn ($subActivity) => (string) $subActivity->id === (string) $filters['sub_activity_id']);
        }

        return true;
    }

    private function subActivityMatchesProjectPositionStructureFilters($subActivity, Activity $activity, Project $project, array $filters): bool
    {
        if (! empty($filters['project_id']) && (string) $project->id !== (string) $filters['project_id']) {
            return false;
        }

        if (! empty($filters['activity_id']) && (string) $activity->id !== (string) $filters['activity_id']) {
            return false;
        }

        if (! empty($filters['sub_activity_id']) && (string) $subActivity->id !== (string) $filters['sub_activity_id']) {
            return false;
        }

        return true;
    }

    private function buildProjectFinancialPosition(
        Program $program,
        array $programFundingIds,
        array $filters = [],
        array $executionDashboard = []
    ): array
    {
        $fundings = ! empty($programFundingIds)
            ? ProgramFunding::whereIn('id', $programFundingIds)->get()
            : collect();

        if ($fundings->isEmpty()) {
            $fundings = ProgramFunding::query()
                ->where(function ($query) use ($program) {
                    $query->where('program_id', $program->id)
                        ->orWhere('program_name', $program->name);
                })
                ->get();
        }

        $fundingIds = $fundings->pluck('id')->filter()->values()->all();
        $approvedFunding = (float) $fundings->where('status', 'approved')->sum('approved_amount');
        if ($approvedFunding <= 0) {
            $approvedFunding = (float) $fundings->sum('approved_amount');
        }

        if (! empty($filters['project_id'])) {
            $selectedProject = $program->projects->first(
                fn (Project $project): bool => (string) $project->id === (string) $filters['project_id']
            );
            $approvedFunding = (float) ($selectedProject?->total_budget ?? 0);
        }

        $structureScope = $this->projectFinancialPositionStructureScope($program, $filters);
        $projectIds = $structureScope['project_ids'];
        $activityIds = $structureScope['activity_ids'];
        $subActivityIds = $structureScope['sub_activity_ids'];
        $hasActivityFilter = ! empty($filters['activity_id']);
        $hasSubActivityFilter = ! empty($filters['sub_activity_id']);
        $hasStructureScope = ! empty($projectIds) || ! empty($activityIds) || ! empty($subActivityIds);

        $commitments = empty($fundingIds) || ! $hasStructureScope
            ? collect()
            : BudgetCommitment::with('purchaseRequest')
                ->whereIn('program_funding_id', $fundingIds)
                ->whereIn('status', [
                    BudgetCommitment::STATUS_SUBMITTED,
                    BudgetCommitment::STATUS_APPROVED,
                ])
                ->where(function ($query) use ($projectIds, $activityIds, $subActivityIds, $hasActivityFilter, $hasSubActivityFilter) {
                    if (! $hasActivityFilter && ! $hasSubActivityFilter && ! empty($projectIds)) {
                        $query->orWhere(function ($projectQuery) use ($projectIds) {
                            $projectQuery->where('allocation_level', 'project')
                                ->whereIn('allocation_id', $projectIds);
                        });
                    }

                    if (! $hasSubActivityFilter && ! empty($activityIds)) {
                        $query->orWhere(function ($activityQuery) use ($activityIds) {
                            $activityQuery->where('allocation_level', 'activity')
                                ->whereIn('allocation_id', $activityIds);
                        });
                    }

                    if (! empty($subActivityIds)) {
                        $query->orWhere(function ($subActivityQuery) use ($subActivityIds) {
                            $subActivityQuery->where('allocation_level', 'sub_activity')
                                ->whereIn('allocation_id', $subActivityIds);
                        });
                    }
                })
                ->get()
                ->filter(fn (BudgetCommitment $commitment) => $this->withinProjectPositionPeriod($this->resolveCommitmentDate($commitment), $filters))
                ->values();

        $commitmentIds = $commitments->pluck('id')->filter()->unique()->values()->all();

        $purchaseOrders = (empty($commitmentIds) && empty($subActivityIds))
            ? collect()
            : ProcurementPurchaseOrder::with('invoice')
                ->where(function ($query) use ($commitmentIds, $subActivityIds) {
                    if (! empty($commitmentIds)) {
                        $query->whereIn('budget_commitment_id', $commitmentIds);
                    }

                    if (! empty($subActivityIds)) {
                        $method = empty($commitmentIds) ? 'whereIn' : 'orWhereIn';
                        $query->{$method}('sub_activity_id', $subActivityIds);
                    }
                })
                ->whereNotIn('status', ['cancelled', 'void', 'rejected'])
                ->get()
                ->filter(fn (ProcurementPurchaseOrder $purchaseOrder) => $this->withinProjectPositionPeriod($purchaseOrder->issued_at ?: $purchaseOrder->created_at, $filters))
                ->values();

        $purchaseOrderIds = $purchaseOrders->pluck('id')->filter()->unique()->values()->all();
        $invoiceIdsFromPurchaseOrders = $purchaseOrders->pluck('invoice_id')->filter()->unique()->values()->all();
        $dashboardAligned = ($filters['mode'] ?? 'life_to_date') === 'life_to_date'
            && empty($filters['funding_id'])
            && empty($filters['project_id'])
            && empty($filters['activity_id'])
            && empty($filters['sub_activity_id']);
        $dashboardComponents = $dashboardAligned
            ? collect($executionDashboard['componentBreakdownRows'] ?? [])
                ->filter(fn ($row) => ! empty($row['component_id']))
                ->keyBy(fn ($row) => (string) $row['component_id'])
            : collect();

        $invoices = (empty($invoiceIdsFromPurchaseOrders) && empty($subActivityIds))
            ? collect()
            : ProcurementInvoice::query()
                ->recognizedInvoice()
                ->where(function ($query) use ($invoiceIdsFromPurchaseOrders, $subActivityIds) {
                    if (! empty($invoiceIdsFromPurchaseOrders)) {
                        $query->whereIn('id', $invoiceIdsFromPurchaseOrders);
                    }

                    if (! empty($subActivityIds)) {
                        $method = empty($invoiceIdsFromPurchaseOrders) ? 'whereIn' : 'orWhereIn';
                        $query->{$method}('sub_activity_id', $subActivityIds);
                    }
                })
                ->whereNotIn('status', ['cancelled', 'void', 'rejected'])
                ->get()
                ->filter(fn (ProcurementInvoice $invoice) => $this->withinProjectPositionPeriod($invoice->invoice_month ?: $invoice->created_at, $filters))
                ->values();

        $disbursements = (empty($purchaseOrderIds) && empty($subActivityIds))
            ? collect()
            : ProcurementDisbursement::with('purchaseOrder.invoice')
                ->recognizedPayment()
                ->where(function ($query) use ($purchaseOrderIds, $subActivityIds) {
                    if (! empty($purchaseOrderIds)) {
                        $query->whereIn('purchase_order_id', $purchaseOrderIds);
                    }

                    if (! empty($subActivityIds)) {
                        $method = empty($purchaseOrderIds) ? 'whereIn' : 'orWhereIn';
                        $query->{$method}('sub_activity_id', $subActivityIds);
                    }
                })
                ->get()
                ->filter(fn (ProcurementDisbursement $disbursement) => $this->withinProjectPositionPeriod($this->resolveDisbursementDate($disbursement), $filters))
                ->values();

        $projectRows = collect();
        $totals = $this->emptyProjectPositionTotals();

        foreach ($program->projects->sortBy('name') as $project) {
            if (! $this->projectMatchesProjectPositionStructureFilters($project, $filters)) {
                continue;
            }

            $activityRows = collect();
            $projectChildren = $this->emptyProjectPositionTotals();

            foreach ($project->activities->sortBy('name') as $activity) {
                if (! $this->activityMatchesProjectPositionStructureFilters($activity, $project, $filters)) {
                    continue;
                }

                $subRows = collect();
                $activityChildren = $this->emptyProjectPositionTotals();

                foreach ($activity->subActivities->sortBy('name') as $subActivity) {
                    if (! $this->subActivityMatchesProjectPositionStructureFilters($subActivity, $activity, $project, $filters)) {
                        continue;
                    }

                    $budget = $this->projectPositionAllocationAmount($subActivity->allocations, $filters);
                    $direct = $this->directProjectPositionMetrics('sub_activity', (string) $subActivity->id, $commitments, $purchaseOrders, $invoices, $disbursements);
                    $subRow = $this->projectPositionNode($subActivity->name, 'sub_activity', $budget, $direct, $this->emptyProjectPositionTotals());

                    $subRows->push($subRow);
                    $activityChildren = $this->addProjectPositionTotals($activityChildren, $subRow);
                }

                $activityDirectBudget = $hasSubActivityFilter
                    ? 0
                    : $this->projectPositionAllocationAmount($activity->allocations, $filters);
                $activityDirect = $hasSubActivityFilter
                    ? $this->emptyDirectProjectPositionMetrics()
                    : $this->directProjectPositionMetrics('activity', (string) $activity->id, $commitments, $purchaseOrders, $invoices, $disbursements);
                $activityRow = $this->projectPositionNode(
                    $activity->name,
                    'activity',
                    max($activityDirectBudget, $activityChildren['budget']),
                    $activityDirect,
                    $activityChildren
                );
                $activityRow['children'] = $subRows;

                $activityRows->push($activityRow);
                $projectChildren = $this->addProjectPositionTotals($projectChildren, $activityRow);
            }

            $projectDirectBudget = ($hasActivityFilter || $hasSubActivityFilter)
                ? 0
                : $this->projectPositionAllocationAmount($project->allocations, $filters);
            if (! $hasActivityFilter && ! $hasSubActivityFilter && ($filters['mode'] ?? 'life_to_date') === 'life_to_date') {
                $projectDirectBudget = max((float) ($project->total_budget ?? 0), $projectDirectBudget);
            }
            $projectDirect = ($hasActivityFilter || $hasSubActivityFilter)
                ? $this->emptyDirectProjectPositionMetrics()
                : $this->directProjectPositionMetrics('project', (string) $project->id, $commitments, $purchaseOrders, $invoices, $disbursements);
            $projectRow = $this->projectPositionNode(
                $project->name,
                'project',
                max($projectDirectBudget, $projectChildren['budget']),
                $projectDirect,
                $projectChildren
            );
            $projectRow['children'] = $activityRows;

            if ($dashboardComponent = $dashboardComponents->get((string) $project->id)) {
                $projectRow['budget'] = round((float) ($dashboardComponent['allocation'] ?? 0), 2);
                $projectRow['committed'] = round((float) ($dashboardComponent['commitment'] ?? 0), 2);
                $projectRow['disbursed'] = round((float) ($dashboardComponent['disbursement'] ?? 0), 2);
                $projectRow['uncommitted_budget'] = round($projectRow['budget'] - $projectRow['committed'], 2);
                $projectRow['unpaid_commitments'] = round(max($projectRow['purchase_orders'] - $projectRow['disbursed'], 0), 2);
                $projectRow['po_balance'] = round($projectRow['purchase_orders'] - $projectRow['disbursed'], 2);
                $projectRow['invoice_balance'] = round($projectRow['invoiced'] - $projectRow['disbursed'], 2);
                $projectRow['commitment_rate'] = $projectRow['budget'] > 0
                    ? round(($projectRow['committed'] / $projectRow['budget']) * 100, 1)
                    : 0;
                $projectRow['disbursement_rate'] = $projectRow['budget'] > 0
                    ? round(($projectRow['disbursed'] / $projectRow['budget']) * 100, 1)
                    : 0;
            }

            $projectRows->push($projectRow);
            $totals = $this->addProjectPositionTotals($totals, $projectRow);
        }

        $displayRows = $this->filterProjectPositionRows($projectRows, $filters);

        $scheduledAllocation = $dashboardAligned
            ? round((float) data_get($executionDashboard, 'executionSummary.scheduled_allocation', $totals['budget']), 2)
            : round((float) $totals['budget'], 2);
        $budgetEnvelope = $dashboardAligned
            ? round((float) ($executionDashboard['totalAllocation'] ?? $scheduledAllocation), 2)
            : $this->projectFinancialPositionBudgetEnvelope(
                $program,
                $filters,
                $approvedFunding,
                $scheduledAllocation
            );
        $invoiceComposition = $invoices
            ->groupBy(fn (ProcurementInvoice $invoice): string => strtolower((string) ($invoice->status ?: 'unspecified')))
            ->map(fn ($rows, string $status): array => [
                'status' => $status,
                'count' => $rows->count(),
                'amount' => round((float) $rows->sum('amount'), 2),
            ])
            ->sortByDesc('amount')
            ->values();
        $unlinkedDisbursements = $disbursements
            ->filter(fn (ProcurementDisbursement $disbursement): bool => ! $disbursement->purchaseOrder?->invoice_id)
            ->values();
        $invoiceExceptions = $this->projectFinancialPositionInvoiceExceptions($unlinkedDisbursements);

        $totals['scheduled_allocation'] = $scheduledAllocation;
        $totals['budget'] = round($budgetEnvelope, 2);
        if ($dashboardAligned) {
            $totals['committed'] = round((float) ($executionDashboard['totalCommitment'] ?? $totals['committed']), 2);
            $totals['disbursed'] = round((float) ($executionDashboard['totalDisbursements'] ?? $totals['disbursed']), 2);
        }
        $totals['approved_funding'] = round($approvedFunding, 2);
        $totals['funding_balance'] = round($approvedFunding - $totals['disbursed'], 2);
        $totals['allocation_balance'] = round($budgetEnvelope - $scheduledAllocation, 2);
        $totals['uncommitted_budget'] = round($budgetEnvelope - $totals['committed'], 2);
        $totals['approved_funding_less_scheduled_allocation'] = round($approvedFunding - $scheduledAllocation, 2);
        $totals['funding_utilization_gap'] = round($approvedFunding - $totals['committed'], 2);
        $totals['unprocessed_purchase_requests'] = round(max($totals['committed'] - $totals['purchase_orders'], 0), 2);
        $totals['unpaid_commitments'] = round(max($totals['purchase_orders'] - $totals['disbursed'], 0), 2);
        $totals['commitment_pipeline_balance'] = round(
            $totals['unprocessed_purchase_requests'] + $totals['unpaid_commitments'],
            2
        );
        $totals['invoice_balance'] = round($totals['invoiced'] - $totals['disbursed'], 2);
        $totals['commitment_rate'] = $approvedFunding > 0 ? round(($totals['committed'] / $approvedFunding) * 100, 2) : 0;
        $totals['disbursement_rate'] = $approvedFunding > 0 ? round(($totals['disbursed'] / $approvedFunding) * 100, 2) : 0;

        return [
            'currency' => $program->sector?->currency ?? $fundings->first()?->currency ?? $program->currency ?? 'USD',
            'dashboard_aligned' => $dashboardAligned,
            'execution_dashboard_snapshot' => data_get($executionDashboard, 'executionChartData.snapshot_hash'),
            'execution_dashboard_totals' => $executionDashboard['executionBreakdownTotals'] ?? null,
            'rows' => $displayRows,
            'all_rows' => $projectRows,
            'totals' => $totals,
            'controls' => [
                'commitment_processing_rate' => $totals['committed'] > 0
                    ? round(($totals['unprocessed_purchase_requests'] / $totals['committed']) * 100, 2)
                    : 0,
                'commitment_realization_rate' => $totals['committed'] > 0
                    ? round(($totals['purchase_orders'] / $totals['committed']) * 100, 2)
                    : 0,
                'disbursement_backlog_rate' => $totals['purchase_orders'] > 0
                    ? round(($totals['unpaid_commitments'] / $totals['purchase_orders']) * 100, 2)
                    : 0,
                'disbursement_efficiency_rate' => $totals['purchase_orders'] > 0
                    ? round(($totals['disbursed'] / $totals['purchase_orders']) * 100, 2)
                    : 0,
                'funding_utilization_integrity_gap_rate' => $totals['approved_funding'] > 0
                    ? round(($totals['commitment_pipeline_balance'] / $totals['approved_funding']) * 100, 2)
                    : 0,
                'procurement_pipeline_utilization_gap' => round(
                    $totals['committed'] - ($totals['purchase_orders'] + $totals['unprocessed_purchase_requests']),
                    2
                ),
                'invoice_coverage_rate' => $totals['disbursed'] > 0
                    ? round(min(100, ($totals['invoiced'] / $totals['disbursed']) * 100), 1)
                    : 100.0,
                'invoice_gap' => round(max($totals['disbursed'] - $totals['invoiced'], 0), 2),
                'unlinked_disbursement_count' => $unlinkedDisbursements->count(),
                'unlinked_disbursement_amount' => round((float) $unlinkedDisbursements->sum('amount'), 2),
                'invoice_exception_count' => $invoiceExceptions->count(),
            ],
            'invoice_composition' => $invoiceComposition,
            'invoice_exceptions' => $invoiceExceptions,
            'counts' => [
                'projects' => $projectRows->count(),
                'activities' => $projectRows->sum(fn ($row) => collect($row['children'] ?? [])->count()),
                'sub_activities' => $projectRows->sum(fn ($projectRow) => collect($projectRow['children'] ?? [])
                    ->sum(fn ($activityRow) => collect($activityRow['children'] ?? [])->count())),
                'commitments' => $commitments->count(),
                'purchase_orders' => $purchaseOrders->count(),
                'invoices' => $invoices->count(),
                'disbursements' => $disbursements->count(),
            ],
            'chart' => [
                'labels' => $displayRows->pluck('label')->values(),
                'budget' => $displayRows->pluck('budget')->values(),
                'committed' => $displayRows->pluck('committed')->values(),
                'disbursed' => $displayRows->pluck('disbursed')->values(),
            ],
        ];
    }

    private function projectFinancialPositionBudgetEnvelope(
        Program $program,
        array $filters,
        float $approvedFunding,
        float $scheduledAllocation
    ): float {
        if (! empty($filters['funding_id'])) {
            return round($approvedFunding > 0 ? $approvedFunding : $scheduledAllocation, 2);
        }

        if (! empty($filters['project_id'])) {
            $project = $program->projects->first(
                fn (Project $project): bool => (string) $project->id === (string) $filters['project_id']
            );

            return round(
                (float) ($project?->total_budget ?? 0) > 0
                    ? (float) $project->total_budget
                    : $scheduledAllocation,
                2
            );
        }

        if (
            ! empty($filters['activity_id'])
            || ! empty($filters['sub_activity_id'])
            || ($filters['mode'] ?? 'life_to_date') !== 'life_to_date'
        ) {
            return round($scheduledAllocation, 2);
        }

        $declaredEnvelope = (float) ($program->total_budget ?? 0);

        return round(
            $declaredEnvelope > 0
                ? $declaredEnvelope
                : ($approvedFunding > 0 ? $approvedFunding : $scheduledAllocation),
            2
        );
    }

    private function projectFinancialPositionInvoiceExceptions($disbursements)
    {
        return collect($disbursements)
            ->groupBy(fn (ProcurementDisbursement $disbursement): string => $disbursement->purchase_order_id
                ? 'purchase-order:'.$disbursement->purchase_order_id
                : 'direct:'.($disbursement->sub_activity_id ?: $disbursement->id))
            ->map(function ($rows): array {
                /** @var ProcurementDisbursement $first */
                $first = $rows->first();
                $purchaseOrder = $first->purchaseOrder;

                return [
                    'purchase_order_reference' => $purchaseOrder?->reference_no ?: 'No purchase order',
                    'purchase_order_status' => $purchaseOrder?->status ?: 'unlinked',
                    'purchase_order_amount' => round((float) ($purchaseOrder?->amount ?? 0), 2),
                    'payment_count' => $rows->count(),
                    'paid_amount' => round((float) $rows->sum('amount'), 2),
                    'payment_references' => $this->formatReferenceDisplay(
                        $rows->pluck('reference_no')->filter()->unique()->values()->all()
                    ),
                ];
            })
            ->sortByDesc('paid_amount')
            ->values();
    }

    private function withinProjectPositionPeriod($date, array $filters): bool
    {
        if (empty($filters['start_date']) || empty($filters['end_date'])) {
            return true;
        }

        if (! $date) {
            return false;
        }

        return Carbon::parse($date)->between($filters['start_date'], $filters['end_date']);
    }

    private function projectPositionAllocationAmount($allocations, array $filters): float
    {
        if (($filters['mode'] ?? 'life_to_date') === 'life_to_date') {
            return (float) $allocations->sum('amount');
        }

        $years = collect($filters['year_range'] ?? [])->map(fn ($year) => (int) $year)->all();

        return (float) $allocations
            ->filter(fn ($allocation) => in_array((int) $allocation->year, $years, true))
            ->sum('amount');
    }

    private function emptyDirectProjectPositionMetrics(): array
    {
        return [
            'committed' => 0.0,
            'purchase_orders' => 0.0,
            'invoiced' => 0.0,
            'disbursed' => 0.0,
            'references' => [
                'pr' => $this->formatReferenceDisplay([]),
                'po' => $this->formatReferenceDisplay([]),
                'invoice' => $this->formatReferenceDisplay([]),
                'disbursement' => $this->formatReferenceDisplay([]),
            ],
        ];
    }

    private function directProjectPositionMetrics(string $level, string $id, $commitments, $purchaseOrders, $invoices, $disbursements): array
    {
        $nodeCommitments = $commitments
            ->where('allocation_level', $level)
            ->filter(fn ($commitment) => (string) $commitment->allocation_id === $id)
            ->values();

        $commitmentIds = $nodeCommitments->pluck('id')->map(fn ($value) => (string) $value)->all();

        $nodePurchaseOrders = $purchaseOrders->filter(function ($purchaseOrder) use ($level, $id, $commitmentIds) {
            $matchesCommitment = $purchaseOrder->budget_commitment_id
                && in_array((string) $purchaseOrder->budget_commitment_id, $commitmentIds, true);
            $matchesSubActivity = $level === 'sub_activity'
                && (string) $purchaseOrder->sub_activity_id === $id;

            return $matchesCommitment || $matchesSubActivity;
        })->unique('id')->values();

        $purchaseOrderIds = $nodePurchaseOrders->pluck('id')->map(fn ($value) => (string) $value)->all();
        $invoiceIds = $nodePurchaseOrders->pluck('invoice_id')->filter()->map(fn ($value) => (string) $value)->all();

        $nodeInvoices = $invoices->filter(function ($invoice) use ($level, $id, $invoiceIds) {
            $matchesPurchaseOrder = in_array((string) $invoice->id, $invoiceIds, true);
            $matchesSubActivity = $level === 'sub_activity'
                && (string) $invoice->sub_activity_id === $id;

            return $matchesPurchaseOrder || $matchesSubActivity;
        })->unique('id')->values();

        $nodeDisbursements = $disbursements->filter(function ($disbursement) use ($level, $id, $purchaseOrderIds) {
            $matchesPurchaseOrder = $disbursement->purchase_order_id
                && in_array((string) $disbursement->purchase_order_id, $purchaseOrderIds, true);
            $matchesSubActivity = $level === 'sub_activity'
                && (string) $disbursement->sub_activity_id === $id;

            return $matchesPurchaseOrder || $matchesSubActivity;
        })->unique('id')->values();

        return [
            'committed' => (float) $nodeCommitments->sum('commitment_amount'),
            'purchase_orders' => (float) $nodePurchaseOrders->sum('amount'),
            'invoiced' => (float) $nodeInvoices->sum('amount'),
            'disbursed' => (float) $nodeDisbursements->sum('amount'),
            'references' => [
                'pr' => $this->formatReferenceDisplay($nodeCommitments->map(fn ($commitment) => $commitment->purchaseRequest?->reference_no)->filter()->unique()->values()->all()),
                'po' => $this->formatReferenceDisplay($nodePurchaseOrders->pluck('reference_no')->filter()->unique()->values()->all()),
                'invoice' => $this->formatReferenceDisplay($nodeInvoices->pluck('reference_no')->filter()->unique()->values()->all()),
                'disbursement' => $this->formatReferenceDisplay($nodeDisbursements->pluck('reference_no')->filter()->unique()->values()->all()),
            ],
        ];
    }

    private function projectPositionNode(string $label, string $level, float $budget, array $direct, array $children): array
    {
        $committed = (float) $direct['committed'] + (float) $children['committed'];
        $purchaseOrders = (float) $direct['purchase_orders'] + (float) $children['purchase_orders'];
        $invoiced = (float) $direct['invoiced'] + (float) $children['invoiced'];
        $disbursed = (float) $direct['disbursed'] + (float) $children['disbursed'];
        return [
            'label' => $label,
            'level' => $level,
            'budget' => round($budget, 2),
            'committed' => round($committed, 2),
            'purchase_orders' => round($purchaseOrders, 2),
            'invoiced' => round($invoiced, 2),
            'disbursed' => round($disbursed, 2),
            'uncommitted_budget' => round($budget - $committed, 2),
            'unpaid_commitments' => round(max($purchaseOrders - $disbursed, 0), 2),
            'po_balance' => round($purchaseOrders - $disbursed, 2),
            'invoice_balance' => round($invoiced - $disbursed, 2),
            'commitment_rate' => $budget > 0 ? round(($committed / $budget) * 100, 1) : 0,
            'disbursement_rate' => $budget > 0 ? round(($disbursed / $budget) * 100, 1) : 0,
            'references' => $direct['references'] ?? [],
            'children' => collect(),
        ];
    }

    private function emptyProjectPositionTotals(): array
    {
        return [
            'budget' => 0.0,
            'committed' => 0.0,
            'purchase_orders' => 0.0,
            'invoiced' => 0.0,
            'disbursed' => 0.0,
        ];
    }

    private function addProjectPositionTotals(array $totals, array $row): array
    {
        foreach (['budget', 'committed', 'purchase_orders', 'invoiced', 'disbursed'] as $key) {
            $totals[$key] = round((float) ($totals[$key] ?? 0) + (float) ($row[$key] ?? 0), 2);
        }

        return $totals;
    }

    private function filterProjectPositionRows($rows, array $filters)
    {
        $maxDepth = [
            'project' => 0,
            'activity' => 1,
            'sub_activity' => 2,
        ][$filters['depth'] ?? 'sub_activity'] ?? 2;

        return collect($rows)
            ->map(fn ($row) => $this->filterProjectPositionRow($row, $filters, 0, $maxDepth))
            ->filter()
            ->values();
    }

    private function filterProjectPositionRow(array $row, array $filters, int $depth, int $maxDepth): ?array
    {
        $children = collect();
        if ($depth < $maxDepth) {
            $children = collect($row['children'] ?? [])
                ->map(fn ($child) => $this->filterProjectPositionRow($child, $filters, $depth + 1, $maxDepth))
                ->filter()
                ->values();
        }

        $row['children'] = $children;

        $matchesSearch = $this->projectPositionRowMatchesSearch($row, $filters['search'] ?? '');
        $matchesFocus = $this->projectPositionRowMatchesFocus($row, $filters['focus'] ?? 'all');
        $hasVisibleChildren = $children->isNotEmpty();
        $hasAnyMoney = collect(['budget', 'committed', 'purchase_orders', 'invoiced', 'disbursed'])
            ->contains(fn ($key) => abs((float) ($row[$key] ?? 0)) > 0.00001);
        $includeZero = (bool) ($filters['include_zero'] ?? true);

        if (! $includeZero && ! $hasAnyMoney && ! $hasVisibleChildren) {
            return null;
        }

        if (($filters['search'] ?? '') !== '' && ! $matchesSearch && ! $hasVisibleChildren) {
            return null;
        }

        if (($filters['focus'] ?? 'all') !== 'all' && ! $matchesFocus && ! $hasVisibleChildren) {
            return null;
        }

        return $row;
    }

    private function projectPositionRowMatchesSearch(array $row, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        $haystack = strtolower((string) ($row['label'] ?? ''));
        foreach (($row['references'] ?? []) as $reference) {
            $haystack .= ' ' . strtolower((string) ($reference['display'] ?? ''));
            $haystack .= ' ' . strtolower((string) ($reference['full'] ?? ''));
        }

        return str_contains($haystack, strtolower($search));
    }

    private function projectPositionRowMatchesFocus(array $row, string $focus): bool
    {
        return match ($focus) {
            'unpaid' => (float) ($row['unpaid_commitments'] ?? 0) > 0,
            'over_committed' => (float) ($row['uncommitted_budget'] ?? 0) < 0,
            'with_disbursement' => (float) ($row['disbursed'] ?? 0) > 0,
            'with_invoice' => (float) ($row['invoiced'] ?? 0) > 0,
            'no_activity' => (float) ($row['committed'] ?? 0) <= 0
                && (float) ($row['purchase_orders'] ?? 0) <= 0
                && (float) ($row['invoiced'] ?? 0) <= 0
                && (float) ($row['disbursed'] ?? 0) <= 0,
            default => true,
        };
    }

    private function buildCommitmentHierarchy(
        Program $program,
        array $commitmentBySub,
        array $commitmentReferencesBySub,
        array $commitmentBySubYear,
        array $yearRange
    ): array
    {
        $rows = [];

        foreach ($program->projects->sortBy('name') as $project) {
            $projectTotalAllocated = 0;
            $projectTotalCommitted = 0;
            $projectYearlyAllocated = array_fill_keys($yearRange, 0.0);
            $projectYearlyCommitted = array_fill_keys($yearRange, 0.0);
            $activities = [];

            foreach ($project->activities->sortBy('name') as $activity) {
                $activityTotalAllocated = 0;
                $activityTotalCommitted = 0;
                $activityYearlyAllocated = array_fill_keys($yearRange, 0.0);
                $activityYearlyCommitted = array_fill_keys($yearRange, 0.0);
                $subRows = [];

                foreach ($activity->subActivities->sortBy('name') as $subActivity) {
                    $allocatedByYear = array_fill_keys($yearRange, 0.0);
                    foreach ($subActivity->allocations as $allocation) {
                        $year = (int) $allocation->year;
                        if (array_key_exists($year, $allocatedByYear)) {
                            $allocatedByYear[$year] += (float) $allocation->amount;
                        }
                    }
                    $allocated = array_sum($allocatedByYear);
                    $committed = (float) ($commitmentBySub[$subActivity->id] ?? 0);
                    $references = $commitmentReferencesBySub[$subActivity->id] ?? [];
                    $referenceLabel = $this->formatReferenceDisplay($references);
                    $committedByYear = array_fill_keys($yearRange, 0.0);
                    if (isset($commitmentBySubYear[$subActivity->id])) {
                        foreach ($commitmentBySubYear[$subActivity->id] as $year => $amount) {
                            if (array_key_exists($year, $committedByYear)) {
                                $committedByYear[$year] += (float) $amount;
                            }
                        }
                    }
                    $varianceByYear = [];
                    foreach ($yearRange as $year) {
                        $varianceByYear[$year] = round($allocatedByYear[$year] - $committedByYear[$year], 2);
                        $activityYearlyAllocated[$year] += $allocatedByYear[$year];
                        $activityYearlyCommitted[$year] += $committedByYear[$year];
                    }
                    $variance = round($allocated - $committed, 2);
                    $utilization = $allocated > 0 ? round(($committed / $allocated) * 100, 2) : 0;

                    $subRows[] = [
                        'subActivity' => $subActivity,
                        'references' => $referenceLabel['display'],
                        'references_full' => $referenceLabel['full'],
                        'allocated' => round($allocated, 2),
                        'committed' => round($committed, 2),
                        'variance' => $variance,
                        'utilization' => $utilization,
                        'yearly' => [
                            'allocated' => array_map(fn ($v) => round((float) $v, 2), $allocatedByYear),
                            'committed' => array_map(fn ($v) => round((float) $v, 2), $committedByYear),
                            'variance' => $varianceByYear,
                        ],
                    ];

                    $activityTotalAllocated += $allocated;
                    $activityTotalCommitted += $committed;
                }

                foreach ($yearRange as $year) {
                    $projectYearlyAllocated[$year] += $activityYearlyAllocated[$year];
                    $projectYearlyCommitted[$year] += $activityYearlyCommitted[$year];
                }

                $activityVarianceByYear = [];
                foreach ($yearRange as $year) {
                    $activityVarianceByYear[$year] = round($activityYearlyAllocated[$year] - $activityYearlyCommitted[$year], 2);
                }

                $activities[] = [
                    'activity' => $activity,
                    'references' => '',
                    'allocated' => round($activityTotalAllocated, 2),
                    'committed' => round($activityTotalCommitted, 2),
                    'variance' => round($activityTotalAllocated - $activityTotalCommitted, 2),
                    'utilization' => $activityTotalAllocated > 0
                        ? round(($activityTotalCommitted / $activityTotalAllocated) * 100, 2)
                        : 0,
                    'yearly' => [
                        'allocated' => array_map(fn ($v) => round((float) $v, 2), $activityYearlyAllocated),
                        'committed' => array_map(fn ($v) => round((float) $v, 2), $activityYearlyCommitted),
                        'variance' => $activityVarianceByYear,
                    ],
                    'subActivities' => $subRows,
                ];

                $projectTotalAllocated += $activityTotalAllocated;
                $projectTotalCommitted += $activityTotalCommitted;
            }

            $projectVarianceByYear = [];
            foreach ($yearRange as $year) {
                $projectVarianceByYear[$year] = round($projectYearlyAllocated[$year] - $projectYearlyCommitted[$year], 2);
            }

            $rows[] = [
                'project' => $project,
                'references' => '',
                'allocated' => round($projectTotalAllocated, 2),
                'committed' => round($projectTotalCommitted, 2),
                'variance' => round($projectTotalAllocated - $projectTotalCommitted, 2),
                'utilization' => $projectTotalAllocated > 0
                    ? round(($projectTotalCommitted / $projectTotalAllocated) * 100, 2)
                    : 0,
                'yearly' => [
                    'allocated' => array_map(fn ($v) => round((float) $v, 2), $projectYearlyAllocated),
                    'committed' => array_map(fn ($v) => round((float) $v, 2), $projectYearlyCommitted),
                    'variance' => $projectVarianceByYear,
                ],
                'activities' => $activities,
            ];
        }

        return $rows;
    }

    private function summarizeCommitmentTotals(array $rows): array
    {
        $allocated = 0;
        $committed = 0;

        foreach ($rows as $projectRow) {
            $allocated += $projectRow['allocated'];
            $committed += $projectRow['committed'];
        }

        $variance = round($allocated - $committed, 2);
        $utilization = $allocated > 0 ? round(($committed / $allocated) * 100, 2) : 0;

        return [
            'allocated' => round($allocated, 2),
            'committed' => round($committed, 2),
            'variance' => $variance,
            'utilization' => $utilization,
        ];
    }

    private function buildCommitmentCharts($commitments, Program $program, array $yearRange, string $mode, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $periodTotals = [];
        $diffMonths = $startDate && $endDate ? $startDate->diffInMonths($endDate) : 0;
        $useMonthly = $mode === 'range' && $diffMonths <= 18;

        foreach ($commitments as $commitment) {
            $date = $this->resolveCommitmentDate($commitment);
            $key = $useMonthly
                ? $date->format('M Y')
                : $this->periodKey($date, $mode);
            $periodTotals[$key] = ($periodTotals[$key] ?? 0) + (float) $commitment->commitment_amount;
        }

        ksort($periodTotals);

        $lineLabels = array_keys($periodTotals);
        $lineData = array_map(fn ($value) => round((float) $value, 2), array_values($periodTotals));

        $allocationByYear = array_fill_keys($yearRange, 0);
        $commitmentByYear = array_fill_keys($yearRange, 0);

        foreach ($program->projects as $project) {
            foreach ($project->activities as $activity) {
                foreach ($activity->subActivities as $subActivity) {
                    foreach ($subActivity->allocations as $allocation) {
                        $year = (int) $allocation->year;
                        if (array_key_exists($year, $allocationByYear)) {
                            $allocationByYear[$year] += (float) $allocation->amount;
                        }
                    }
                }
            }
        }

        foreach ($commitments as $commitment) {
            $date = $this->resolveCommitmentDate($commitment);
            $year = $date->year;
            if (array_key_exists($year, $commitmentByYear)) {
                $commitmentByYear[$year] += (float) $commitment->commitment_amount;
            }
        }

        $barLabels = array_map('strval', array_keys($allocationByYear));
        $barAllocations = array_map(fn ($value) => round((float) $value, 2), array_values($allocationByYear));
        $barCommitments = array_map(fn ($value) => round((float) $value, 2), array_values($commitmentByYear));

        $bubbleData = [];
        foreach ($program->projects as $project) {
            foreach ($project->activities as $activity) {
                foreach ($activity->subActivities as $subActivity) {
                    $allocated = (float) $subActivity->allocations
                        ->whereIn('year', $yearRange)
                        ->sum('amount');
                    $committed = (float) $commitments
                        ->where('allocation_id', $subActivity->id)
                        ->sum('commitment_amount');
                    if ($allocated <= 0 && $committed <= 0) {
                        continue;
                    }
                    $bubbleData[] = [
                        'x' => round($allocated, 2),
                        'y' => round($committed, 2),
                        'r' => max(4, min(18, sqrt(max($committed, 1)))),
                        'label' => $subActivity->name,
                    ];
                }
            }
        }

        return [
            'line' => [
                'labels' => $lineLabels,
                'data' => $lineData,
            ],
            'bar' => [
                'labels' => $barLabels,
                'allocations' => $barAllocations,
                'commitments' => $barCommitments,
            ],
            'bubble' => $bubbleData,
        ];
    }

    private function periodKey(Carbon $date, string $mode): string
    {
        if ($mode === 'quarterly') {
            return 'Q' . $date->quarter . ' ' . $date->year;
        }

        if ($mode === 'semiannual') {
            $half = $date->month <= 6 ? 1 : 2;
            return 'H' . $half . ' ' . $date->year;
        }

        return (string) $date->year;
    }

    private function buildCommitmentSummary(array $totals, array $rows, string $label): array
    {
        $allocated = $totals['allocated'];
        $committed = $totals['committed'];
        $utilization = $totals['utilization'];
        $cappedUtilization = min(100, max(0, $utilization));
        $variance = $totals['variance'];

        $summary = [];
        $summary[] = "Coverage period: {$label}.";

        if ($allocated <= 0) {
            $summary[] = 'No allocations were found for the selected period, so commitments cannot be compared.';
        } else {
            $summary[] = sprintf(
                'Total allocated is %s, while actual commitments are %s (%s%% utilization).',
                number_format($allocated, 2),
                number_format($committed, 2),
                number_format($cappedUtilization, 2)
            );

            if ($utilization < 50) {
                $summary[] = 'Commitments are low compared to allocations. Consider accelerating planned activities.';
            } elseif ($utilization < 80) {
                $summary[] = 'Commitments are moderate; there is still room to utilize available allocations.';
            } elseif ($utilization <= 100) {
                $summary[] = 'Commitments are strong and within allocated limits.';
            } else {
                $summary[] = 'Commitments exceed allocations. Review spending controls for the highlighted sub-activities.';
            }
        }

        $overCommitted = [];
        foreach ($rows as $projectRow) {
            foreach ($projectRow['activities'] as $activityRow) {
                foreach ($activityRow['subActivities'] as $subRow) {
                    if ($subRow['allocated'] > 0 && $subRow['committed'] > $subRow['allocated']) {
                        $overCommitted[] = $subRow;
                    }
                }
            }
        }

        if (!empty($overCommitted)) {
            $top = collect($overCommitted)
                ->sortByDesc('utilization')
                ->take(3)
                ->map(function ($row) {
                    $utilization = min(100, max(0, (float) $row['utilization']));
                    return $row['subActivity']->name . ' (' . number_format($utilization, 2) . '%)';
                })
                ->implode(', ');
            $summary[] = 'Top over-committed sub-activities: ' . $top . '.';
        }

        if ($variance > 0) {
            $summary[] = 'Remaining allocation: ' . number_format($variance, 2) . '.';
        }

        return $summary;
    }

    private function resolveDisbursementDate(ProcurementDisbursement $disbursement): Carbon
    {
        if ($disbursement->paid_at) {
            return Carbon::parse($disbursement->paid_at)->startOfDay();
        }

        if ($disbursement->created_at) {
            return Carbon::parse($disbursement->created_at)->startOfDay();
        }

        return now()->startOfDay();
    }

    private function buildIfrGlobalBudgetBySubActivity(Program $program): array
    {
        $budgets = [];

        foreach ($program->projects as $project) {
            foreach ($project->activities as $activity) {
                foreach ($activity->subActivities as $subActivity) {
                    $budgets[(string) $subActivity->id] = round((float) $subActivity->allocations->sum('amount'), 2);
                }
            }
        }

        return $budgets;
    }

    private function buildIfrHierarchy(
        Program $program,
        array $globalCommitmentBySub,
        array $plannedCommitmentBySub,
        array $disbursementBySub,
        array $commitmentReferencesBySub,
        array $disbursementBySubYear,
        array $yearRange
    ): array
    {
        $rows = [];

        foreach ($program->projects->sortBy('name') as $project) {
            $projectTotalGlobalCommitment = 0;
            $projectTotalPlannedCommitment = 0;
            $projectTotalDisbursed = 0;
            $projectYearlyGlobalCommitment = array_fill_keys($yearRange, 0.0);
            $projectYearlyDisbursed = array_fill_keys($yearRange, 0.0);
            $activities = [];

            foreach ($project->activities->sortBy('name') as $activity) {
                $activityTotalGlobalCommitment = 0;
                $activityTotalPlannedCommitment = 0;
                $activityTotalDisbursed = 0;
                $activityYearlyGlobalCommitment = array_fill_keys($yearRange, 0.0);
                $activityYearlyDisbursed = array_fill_keys($yearRange, 0.0);
                $subRows = [];

                foreach ($activity->subActivities->sortBy('name') as $subActivity) {
                    $subActivityId = (string) $subActivity->id;
                    $globalCommitment = (float) ($globalCommitmentBySub[$subActivityId] ?? 0);
                    $plannedCommitment = (float) ($plannedCommitmentBySub[$subActivityId] ?? 0);
                    $disbursed = (float) ($disbursementBySub[$subActivityId] ?? 0);
                    $references = $commitmentReferencesBySub[$subActivityId] ?? [];
                    $referenceLabel = $this->formatReferenceDisplay($references);

                    $globalCommitmentByYear = array_fill_keys($yearRange, round($globalCommitment, 2));

                    $disbursedByYear = array_fill_keys($yearRange, 0.0);
                    if (isset($disbursementBySubYear[$subActivityId])) {
                        foreach ($disbursementBySubYear[$subActivityId] as $year => $amount) {
                            if (array_key_exists($year, $disbursedByYear)) {
                                $disbursedByYear[$year] += (float) $amount;
                            }
                        }
                    }

                    $varianceByYear = [];
                    $runningDisbursement = 0.0;
                    foreach ($yearRange as $year) {
                        $runningDisbursement += (float) $disbursedByYear[$year];
                        $disbursedByYear[$year] = round($runningDisbursement, 2);
                        $varianceByYear[$year] = round($globalCommitmentByYear[$year] - $disbursedByYear[$year], 2);
                        $activityYearlyGlobalCommitment[$year] += $globalCommitmentByYear[$year];
                        $activityYearlyDisbursed[$year] += $disbursedByYear[$year];
                    }

                    $variance = round($globalCommitment - $disbursed, 2);
                    $commitmentRate = $globalCommitment > 0 ? round(($plannedCommitment / $globalCommitment) * 100, 2) : 0;
                    $disbursementRate = $globalCommitment > 0 ? round(($disbursed / $globalCommitment) * 100, 2) : 0;

                    $subRows[] = [
                        'subActivity' => $subActivity,
                        'references' => $referenceLabel['display'],
                        'references_full' => $referenceLabel['full'],
                        'committed' => round($globalCommitment, 2),
                        'global_commitment' => round($globalCommitment, 2),
                        'planned_commitment' => round($plannedCommitment, 2),
                        'disbursed' => round($disbursed, 2),
                        'variance' => $variance,
                        'commitment_rate' => $commitmentRate,
                        'disbursement_rate' => $disbursementRate,
                        'utilization' => $disbursementRate,
                        'yearly' => [
                            'committed' => array_map(fn ($v) => round((float) $v, 2), $globalCommitmentByYear),
                            'global_commitment' => array_map(fn ($v) => round((float) $v, 2), $globalCommitmentByYear),
                            'disbursed' => array_map(fn ($v) => round((float) $v, 2), $disbursedByYear),
                            'variance' => $varianceByYear,
                        ],
                    ];

                    $activityTotalGlobalCommitment += $globalCommitment;
                    $activityTotalPlannedCommitment += $plannedCommitment;
                    $activityTotalDisbursed += $disbursed;
                }

                foreach ($yearRange as $year) {
                    $projectYearlyGlobalCommitment[$year] += $activityYearlyGlobalCommitment[$year];
                    $projectYearlyDisbursed[$year] += $activityYearlyDisbursed[$year];
                }

                $activityVarianceByYear = [];
                foreach ($yearRange as $year) {
                    $activityVarianceByYear[$year] = round($activityYearlyGlobalCommitment[$year] - $activityYearlyDisbursed[$year], 2);
                }

                $activityCommitmentRate = $activityTotalGlobalCommitment > 0
                    ? round(($activityTotalPlannedCommitment / $activityTotalGlobalCommitment) * 100, 2)
                    : 0;
                $activityDisbursementRate = $activityTotalGlobalCommitment > 0
                    ? round(($activityTotalDisbursed / $activityTotalGlobalCommitment) * 100, 2)
                    : 0;

                $activities[] = [
                    'activity' => $activity,
                    'references' => '',
                    'committed' => round($activityTotalGlobalCommitment, 2),
                    'global_commitment' => round($activityTotalGlobalCommitment, 2),
                    'planned_commitment' => round($activityTotalPlannedCommitment, 2),
                    'disbursed' => round($activityTotalDisbursed, 2),
                    'variance' => round($activityTotalGlobalCommitment - $activityTotalDisbursed, 2),
                    'commitment_rate' => $activityCommitmentRate,
                    'disbursement_rate' => $activityDisbursementRate,
                    'utilization' => $activityDisbursementRate,
                    'yearly' => [
                        'committed' => array_map(fn ($v) => round((float) $v, 2), $activityYearlyGlobalCommitment),
                        'global_commitment' => array_map(fn ($v) => round((float) $v, 2), $activityYearlyGlobalCommitment),
                        'disbursed' => array_map(fn ($v) => round((float) $v, 2), $activityYearlyDisbursed),
                        'variance' => $activityVarianceByYear,
                    ],
                    'subActivities' => $subRows,
                ];

                $projectTotalGlobalCommitment += $activityTotalGlobalCommitment;
                $projectTotalPlannedCommitment += $activityTotalPlannedCommitment;
                $projectTotalDisbursed += $activityTotalDisbursed;
            }

            $projectVarianceByYear = [];
            foreach ($yearRange as $year) {
                $projectVarianceByYear[$year] = round($projectYearlyGlobalCommitment[$year] - $projectYearlyDisbursed[$year], 2);
            }

            $projectCommitmentRate = $projectTotalGlobalCommitment > 0
                ? round(($projectTotalPlannedCommitment / $projectTotalGlobalCommitment) * 100, 2)
                : 0;
            $projectDisbursementRate = $projectTotalGlobalCommitment > 0
                ? round(($projectTotalDisbursed / $projectTotalGlobalCommitment) * 100, 2)
                : 0;

            $rows[] = [
                'project' => $project,
                'references' => '',
                'committed' => round($projectTotalGlobalCommitment, 2),
                'global_commitment' => round($projectTotalGlobalCommitment, 2),
                'planned_commitment' => round($projectTotalPlannedCommitment, 2),
                'disbursed' => round($projectTotalDisbursed, 2),
                'variance' => round($projectTotalGlobalCommitment - $projectTotalDisbursed, 2),
                'commitment_rate' => $projectCommitmentRate,
                'disbursement_rate' => $projectDisbursementRate,
                'utilization' => $projectDisbursementRate,
                'yearly' => [
                    'committed' => array_map(fn ($v) => round((float) $v, 2), $projectYearlyGlobalCommitment),
                    'global_commitment' => array_map(fn ($v) => round((float) $v, 2), $projectYearlyGlobalCommitment),
                    'disbursed' => array_map(fn ($v) => round((float) $v, 2), $projectYearlyDisbursed),
                    'variance' => $projectVarianceByYear,
                ],
                'activities' => $activities,
            ];
        }

        return $rows;
    }

    private function summarizeIfrTotals(array $rows): array
    {
        $globalCommitment = 0;
        $plannedCommitment = 0;
        $disbursed = 0;

        foreach ($rows as $projectRow) {
            $globalCommitment += $projectRow['global_commitment'] ?? $projectRow['committed'];
            $plannedCommitment += $projectRow['planned_commitment'] ?? 0;
            $disbursed += $projectRow['disbursed'];
        }

        $variance = round($globalCommitment - $disbursed, 2);
        $commitmentRate = $globalCommitment > 0 ? round(($plannedCommitment / $globalCommitment) * 100, 2) : 0;
        $disbursementRate = $globalCommitment > 0 ? round(($disbursed / $globalCommitment) * 100, 2) : 0;

        return [
            'committed' => round($globalCommitment, 2),
            'global_commitment' => round($globalCommitment, 2),
            'planned_commitment' => round($plannedCommitment, 2),
            'disbursed' => round($disbursed, 2),
            'variance' => $variance,
            'commitment_rate' => $commitmentRate,
            'disbursement_rate' => $disbursementRate,
            'utilization' => $disbursementRate,
        ];
    }

    private function reportUsesMonthlyPeriods(?Carbon $startDate, ?Carbon $endDate): bool
    {
        return $startDate && $endDate && $startDate->diffInMonths($endDate) <= 18;
    }

    private function reportPeriodLabels(array $yearRange, ?Carbon $startDate, ?Carbon $endDate): array
    {
        if ($this->reportUsesMonthlyPeriods($startDate, $endDate)) {
            $labels = [];
            $cursor = $startDate->copy()->startOfMonth();
            $lastMonth = $endDate->copy()->startOfMonth();

            while ($cursor->lte($lastMonth)) {
                $labels[] = $cursor->format('M Y');
                $cursor->addMonth();
            }

            return $labels;
        }

        return array_map('strval', $yearRange);
    }

    private function reportPeriodKey(Carbon $date, ?Carbon $startDate, ?Carbon $endDate): string
    {
        return $this->reportUsesMonthlyPeriods($startDate, $endDate)
            ? $date->format('M Y')
            : (string) $date->year;
    }

    private function buildIfrCharts(array $globalCommitmentBySub, array $plannedCommitmentBySub, $disbursements, Program $program, array $yearRange, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $periodLabels = $this->reportPeriodLabels($yearRange, $startDate, $endDate);
        $periodDisbursements = array_fill_keys($periodLabels, 0.0);
        $totalGlobalCommitment = round(array_sum($globalCommitmentBySub), 2);
        $totalPlannedCommitment = round(array_sum($plannedCommitmentBySub), 2);

        foreach ($disbursements as $disbursement) {
            $date = $this->resolveDisbursementDate($disbursement);
            $key = $this->reportPeriodKey($date, $startDate, $endDate);
            if (array_key_exists($key, $periodDisbursements)) {
                $periodDisbursements[$key] += (float) $disbursement->amount;
            }
        }

        $lineLabels = $periodLabels;
        $lineGlobalCommitments = array_fill(0, count($lineLabels), $totalGlobalCommitment);
        $linePlannedCommitments = array_fill(0, count($lineLabels), $totalPlannedCommitment);
        $runningDisbursement = 0.0;
        $lineDisbursements = array_map(function ($key) use (&$runningDisbursement, $periodDisbursements) {
            $runningDisbursement += (float) ($periodDisbursements[$key] ?? 0);
            return round($runningDisbursement, 2);
        }, $lineLabels);

        $barLabels = $lineLabels;
        $barGlobalCommitments = $lineGlobalCommitments;
        $barPlannedCommitments = $linePlannedCommitments;
        $barDisbursements = $lineDisbursements;

        $bubbleData = [];
        foreach ($program->projects as $project) {
            foreach ($project->activities as $activity) {
                foreach ($activity->subActivities as $subActivity) {
                    $subActivityId = (string) $subActivity->id;
                    $globalCommitment = (float) ($globalCommitmentBySub[$subActivityId] ?? 0);
                    $plannedCommitment = (float) ($plannedCommitmentBySub[$subActivityId] ?? 0);
                    $disbursed = (float) $disbursements
                        ->where('sub_activity_id', $subActivity->id)
                        ->sum('amount');
                    if ($globalCommitment <= 0 && $plannedCommitment <= 0 && $disbursed <= 0) {
                        continue;
                    }
                    $bubbleData[] = [
                        'x' => round($globalCommitment, 2),
                        'y' => round($disbursed, 2),
                        'r' => max(4, min(18, sqrt(max($disbursed, 1)))),
                        'label' => $subActivity->name,
                        'planned' => round($plannedCommitment, 2),
                    ];
                }
            }
        }

        return [
            'line' => [
                'labels' => $lineLabels,
                'commitments' => $lineGlobalCommitments,
                'global_commitments' => $lineGlobalCommitments,
                'planned_commitments' => $linePlannedCommitments,
                'disbursements' => $lineDisbursements,
            ],
            'bar' => [
                'labels' => $barLabels,
                'commitments' => $barGlobalCommitments,
                'global_commitments' => $barGlobalCommitments,
                'planned_commitments' => $barPlannedCommitments,
                'disbursements' => $barDisbursements,
            ],
            'bubble' => $bubbleData,
        ];
    }

    private function buildIfrSummary(array $totals, array $rows, string $label): array
    {
        $globalCommitment = $totals['global_commitment'] ?? $totals['committed'];
        $plannedCommitment = $totals['planned_commitment'] ?? 0;
        $disbursed = $totals['disbursed'];
        $commitmentRate = $totals['commitment_rate'] ?? 0;
        $disbursementRate = $totals['disbursement_rate'] ?? ($totals['utilization'] ?? 0);
        $variance = $totals['variance'];

        $summary = [];
        $summary[] = "Coverage period: {$label}.";

        if ($globalCommitment <= 0) {
            $summary[] = 'No global commitments were found for the selected budget structure, so planned commitments and disbursements cannot be compared.';
        } else {
            $summary[] = sprintf(
                'Global commitments are %s and planned commitments are %s, giving a commitment rate of %s%%.',
                number_format($globalCommitment, 2),
                number_format($plannedCommitment, 2),
                number_format($commitmentRate, 2)
            );
            $summary[] = sprintf(
                'Recorded fully paid disbursements are %s, giving a disbursement rate of %s%% against global commitments.',
                number_format($disbursed, 2),
                number_format($disbursementRate, 2)
            );

            if ($disbursementRate < 50) {
                $summary[] = 'Cumulative disbursements are low compared to the whole-period budget. Monitor delivery progress and payment schedules.';
            } elseif ($disbursementRate < 80) {
                $summary[] = 'Cumulative disbursements are moderate; there is still room to execute the remaining budget balance.';
            } elseif ($disbursementRate <= 100) {
                $summary[] = 'Cumulative disbursements are on track and within the whole-period budget.';
            } else {
                $summary[] = 'Cumulative disbursements exceed the whole-period budget. Investigate overpayments or unapproved disbursement activity.';
            }
        }

        $overDisbursed = [];
        foreach ($rows as $projectRow) {
            foreach ($projectRow['activities'] as $activityRow) {
                foreach ($activityRow['subActivities'] as $subRow) {
                    if ($subRow['committed'] > 0 && $subRow['disbursed'] > $subRow['committed']) {
                        $overDisbursed[] = $subRow;
                    }
                }
            }
        }

        if (!empty($overDisbursed)) {
            $top = collect($overDisbursed)
                ->sortByDesc('utilization')
                ->take(3)
                ->map(function ($row) {
                    $utilization = (float) $row['utilization'];
                    return $row['subActivity']->name . ' (' . number_format($utilization, 2) . '%)';
                })
                ->implode(', ');
            $summary[] = 'Top over-disbursed sub-activities: ' . $top . '.';
        }

        if ($variance > 0) {
            $summary[] = 'Remaining global commitment balance: ' . number_format($variance, 2) . '.';
        }

        return $summary;
    }

    private function formatReferenceDisplay(array $references): array
    {
        $references = array_values(array_filter($references));
        if (empty($references)) {
            return [
                'display' => '-',
                'full' => '',
            ];
        }

        if (count($references) === 1) {
            return [
                'display' => $references[0],
                'full' => $references[0],
            ];
        }

        return [
            'display' => $references[0] . ' (+' . (count($references) - 1) . ')',
            'full' => implode(', ', $references),
        ];
    }

    private function scopedSectorReportQuery()
    {
        $query = Sector::query();
        $currentUser = request()->user();

        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            $this->applyAssignedPortfolioScopeToSectors($query, $currentUser);
        }

        return $query;
    }

    private function scopedProgramReportQuery()
    {
        $query = Program::query();
        $currentUser = request()->user();

        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            $this->applyAssignedPortfolioScopeToPrograms($query, $currentUser);
        }

        return $query;
    }

    private function assertProgramReportScope(Program $program): void
    {
        $currentUser = request()->user();
        if (! $this->userHasAssignedPortfolioScope($currentUser)) {
            return;
        }

        abort_unless($this->programIsInAssignedPortfolio($program, $currentUser), 403, 'This report is not assigned to your portfolio.');
    }

    private function assertProjectReportScope(Project $project): void
    {
        $currentUser = request()->user();
        if (! $this->userHasAssignedPortfolioScope($currentUser)) {
            return;
        }

        abort_unless($this->projectIsInAssignedPortfolio($project, $currentUser), 403, 'This report is not assigned to your portfolio.');
    }

    private function assertActivityReportScope(Activity $activity): void
    {
        $currentUser = request()->user();
        if (! $this->userHasAssignedPortfolioScope($currentUser)) {
            return;
        }

        abort_unless($this->activityIsInAssignedPortfolio($activity, $currentUser), 403, 'This report is not assigned to your portfolio.');
    }

    private function auditReportAction(string $action, string $message, array $payload = []): void
    {
        try {
            SystemAuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'Reports & Analytics',
                'action' => $action,
                'action_message' => $message,
                'description' => $message,
                'method' => request()->method(),
                'url' => request()->fullUrl(),
                'route_name' => request()->route()?->getName(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'status_code' => 200,
                'payload' => $payload,
            ]);
        } catch (Throwable $exception) {
            // Reporting should remain available even if audit storage is temporarily unavailable.
        }
    }
}
