<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use App\Models\Program;
use App\Models\Project;
use App\Models\Activity;
use App\Models\BudgetCommitment;
use App\Models\ProcurementDisbursement;

use App\Exports\ProgramExport;
use App\Exports\ProjectExport;
use App\Exports\ActivityExport;
use App\Exports\CommitmentReportExport;
use App\Exports\InterimFinancialReportExport;

use Maatwebsite\Excel\Facades\Excel;
use PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BudgetReportController extends Controller
{
    /* ================================
       SECTOR OVERVIEW
    ================================== */
    public function index()
    {
        $sectors = Sector::with([
            'programs.projects.allocations',
            'programs.projects.activities.subActivities'
        ])->get();

        return view('budgetreport.index', compact('sectors'));
    }

    /* ================================
       PROGRAM REPORT
    ================================== */
    public function programReport($id)
    {
        $program = Program::with([
            'projects.allocations',
            'projects.activities.subActivities'
        ])->findOrFail($id);

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

        return view('budgetreport.activity', compact('activity'));
    }


    /* ================================
       EXPORT: PDF
    ================================== */
    public function exportPDF($type, $id)
    {
        if ($type === 'program') {
            $data = Program::with('projects.activities.subActivities')->findOrFail($id);
            $view = 'exports.program_pdf';
        }

        if ($type === 'project') {
            $data = Project::with('activities.subActivities')->findOrFail($id);
            $view = 'exports.project_pdf';
        }

        if ($type === 'activity') {
            $data = Activity::with('subActivities')->findOrFail($id);
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
            return Excel::download(new ProgramExport($id), "program-$id.xlsx");
        }

        if ($type === 'project') {
            return Excel::download(new ProjectExport($id), "project-$id.xlsx");
        }

        if ($type === 'activity') {
            return Excel::download(new ActivityExport($id), "activity-$id.xlsx");
        }
    }


    /* ================================
       (OPTIONAL) DASHBOARD
    ================================== */
    public function dashboard()
    {
        $sectors = Sector::with([
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
        $programs = Program::orderBy('name')->get();
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
                'projects.activities.subActivities.allocations',
                'approvedFundings.funder',
                'fundings.funder',
            ])->findOrFail($programId);

            $filters = $this->resolveCommitmentFilter($request, $program);

            $fundings = $program->approvedFundings;
            if ($fundings->isEmpty()) {
                $fundings = $program->fundings;
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
            'projects.activities.subActivities.allocations',
            'approvedFundings.funder',
            'fundings.funder',
        ])->findOrFail($programId);

        $filters = $this->resolveCommitmentFilter($request, $program);

        $fundings = $program->approvedFundings;
        if ($fundings->isEmpty()) {
            $fundings = $program->fundings;
        }

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
        $programs = Program::orderBy('name')->get();
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
                'projects.activities.subActivities.allocations',
                'approvedFundings.funder',
                'fundings.funder',
            ])->findOrFail($programId);

            $filters = $this->resolveCommitmentFilter($request, $program);

            $fundings = $program->approvedFundings;
            if ($fundings->isEmpty()) {
                $fundings = $program->fundings;
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

            $subActivityIds = $program->projects
                ->flatMap(fn ($project) => $project->activities
                    ->flatMap(fn ($activity) => $activity->subActivities->pluck('id')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $disbursements = empty($subActivityIds)
                ? collect()
                : ProcurementDisbursement::whereIn('sub_activity_id', $subActivityIds)->get();

            $filteredDisbursements = $disbursements->filter(function ($disbursement) use ($filters) {
                $date = $this->resolveDisbursementDate($disbursement);
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
                $commitmentBySub,
                $disbursementBySub,
                $commitmentReferencesBySub,
                $commitmentBySubYear,
                $disbursementBySubYear,
                $filters['year_range']
            );
            $totals = $this->summarizeIfrTotals($report);
            $chartData = $this->buildIfrCharts(
                $filteredCommitments,
                $filteredDisbursements,
                $program,
                $filters['year_range'],
                $filters['mode'],
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

    private function buildIfrExportData(Request $request): array
    {
        $programId = $request->input('program_id');
        if (!$programId) {
            abort(400, 'Program is required for export.');
        }

        $program = Program::with([
            'projects.activities.subActivities.allocations',
            'approvedFundings.funder',
            'fundings.funder',
        ])->findOrFail($programId);

        $filters = $this->resolveCommitmentFilter($request, $program);

        $fundings = $program->approvedFundings;
        if ($fundings->isEmpty()) {
            $fundings = $program->fundings;
        }

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

        $subActivityIds = $program->projects
            ->flatMap(fn ($project) => $project->activities
                ->flatMap(fn ($activity) => $activity->subActivities->pluck('id')))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $disbursements = empty($subActivityIds)
            ? collect()
            : ProcurementDisbursement::whereIn('sub_activity_id', $subActivityIds)->get();

        $filteredDisbursements = $disbursements->filter(function ($disbursement) use ($filters) {
            $date = $this->resolveDisbursementDate($disbursement);
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
            $commitmentBySub,
            $disbursementBySub,
            $commitmentReferencesBySub,
            $commitmentBySubYear,
            $disbursementBySubYear,
            $filters['year_range']
        );
        $totals = $this->summarizeIfrTotals($rows);
        $chartData = $this->buildIfrCharts(
            $filteredCommitments,
            $filteredDisbursements,
            $program,
            $filters['year_range'],
            $filters['mode'],
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
            // Multi-year: always show the full program span when available
            if ($program && $program->start_year && $program->end_year) {
                $startYear = (int) $program->start_year;
                $endYear = (int) $program->end_year;
            } else {
                $startYear = (int) $request->input('start_year', $defaultStartYear);
                $endYear = (int) $request->input('end_year', $defaultEndYear);
            }

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
        if ($commitment->purchaseRequest?->commitment_date) {
            return Carbon::parse($commitment->purchaseRequest->commitment_date)->startOfDay();
        }

        if (!empty($commitment->commitment_year)) {
            return Carbon::create((int) $commitment->commitment_year, 1, 1);
        }

        return now()->startOfDay();
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

    private function buildIfrHierarchy(
        Program $program,
        array $commitmentBySub,
        array $disbursementBySub,
        array $commitmentReferencesBySub,
        array $commitmentBySubYear,
        array $disbursementBySubYear,
        array $yearRange
    ): array
    {
        $rows = [];

        foreach ($program->projects->sortBy('name') as $project) {
            $projectTotalCommitted = 0;
            $projectTotalDisbursed = 0;
            $projectYearlyCommitted = array_fill_keys($yearRange, 0.0);
            $projectYearlyDisbursed = array_fill_keys($yearRange, 0.0);
            $activities = [];

            foreach ($project->activities->sortBy('name') as $activity) {
                $activityTotalCommitted = 0;
                $activityTotalDisbursed = 0;
                $activityYearlyCommitted = array_fill_keys($yearRange, 0.0);
                $activityYearlyDisbursed = array_fill_keys($yearRange, 0.0);
                $subRows = [];

                foreach ($activity->subActivities->sortBy('name') as $subActivity) {
                    $committed = (float) ($commitmentBySub[$subActivity->id] ?? 0);
                    $disbursed = (float) ($disbursementBySub[$subActivity->id] ?? 0);
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

                    $disbursedByYear = array_fill_keys($yearRange, 0.0);
                    if (isset($disbursementBySubYear[$subActivity->id])) {
                        foreach ($disbursementBySubYear[$subActivity->id] as $year => $amount) {
                            if (array_key_exists($year, $disbursedByYear)) {
                                $disbursedByYear[$year] += (float) $amount;
                            }
                        }
                    }

                    $varianceByYear = [];
                    foreach ($yearRange as $year) {
                        $varianceByYear[$year] = round($committedByYear[$year] - $disbursedByYear[$year], 2);
                        $activityYearlyCommitted[$year] += $committedByYear[$year];
                        $activityYearlyDisbursed[$year] += $disbursedByYear[$year];
                    }

                    $variance = round($committed - $disbursed, 2);
                    $utilization = $committed > 0 ? round(($disbursed / $committed) * 100, 2) : 0;

                    $subRows[] = [
                        'subActivity' => $subActivity,
                        'references' => $referenceLabel['display'],
                        'references_full' => $referenceLabel['full'],
                        'committed' => round($committed, 2),
                        'disbursed' => round($disbursed, 2),
                        'variance' => $variance,
                        'utilization' => $utilization,
                        'yearly' => [
                            'committed' => array_map(fn ($v) => round((float) $v, 2), $committedByYear),
                            'disbursed' => array_map(fn ($v) => round((float) $v, 2), $disbursedByYear),
                            'variance' => $varianceByYear,
                        ],
                    ];

                    $activityTotalCommitted += $committed;
                    $activityTotalDisbursed += $disbursed;
                }

                foreach ($yearRange as $year) {
                    $projectYearlyCommitted[$year] += $activityYearlyCommitted[$year];
                    $projectYearlyDisbursed[$year] += $activityYearlyDisbursed[$year];
                }

                $activityVarianceByYear = [];
                foreach ($yearRange as $year) {
                    $activityVarianceByYear[$year] = round($activityYearlyCommitted[$year] - $activityYearlyDisbursed[$year], 2);
                }

                $activities[] = [
                    'activity' => $activity,
                    'references' => '',
                    'committed' => round($activityTotalCommitted, 2),
                    'disbursed' => round($activityTotalDisbursed, 2),
                    'variance' => round($activityTotalCommitted - $activityTotalDisbursed, 2),
                    'utilization' => $activityTotalCommitted > 0
                        ? round(($activityTotalDisbursed / $activityTotalCommitted) * 100, 2)
                        : 0,
                    'yearly' => [
                        'committed' => array_map(fn ($v) => round((float) $v, 2), $activityYearlyCommitted),
                        'disbursed' => array_map(fn ($v) => round((float) $v, 2), $activityYearlyDisbursed),
                        'variance' => $activityVarianceByYear,
                    ],
                    'subActivities' => $subRows,
                ];

                $projectTotalCommitted += $activityTotalCommitted;
                $projectTotalDisbursed += $activityTotalDisbursed;
            }

            $projectVarianceByYear = [];
            foreach ($yearRange as $year) {
                $projectVarianceByYear[$year] = round($projectYearlyCommitted[$year] - $projectYearlyDisbursed[$year], 2);
            }

            $rows[] = [
                'project' => $project,
                'references' => '',
                'committed' => round($projectTotalCommitted, 2),
                'disbursed' => round($projectTotalDisbursed, 2),
                'variance' => round($projectTotalCommitted - $projectTotalDisbursed, 2),
                'utilization' => $projectTotalCommitted > 0
                    ? round(($projectTotalDisbursed / $projectTotalCommitted) * 100, 2)
                    : 0,
                'yearly' => [
                    'committed' => array_map(fn ($v) => round((float) $v, 2), $projectYearlyCommitted),
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
        $committed = 0;
        $disbursed = 0;

        foreach ($rows as $projectRow) {
            $committed += $projectRow['committed'];
            $disbursed += $projectRow['disbursed'];
        }

        $variance = round($committed - $disbursed, 2);
        $utilization = $committed > 0 ? round(($disbursed / $committed) * 100, 2) : 0;

        return [
            'committed' => round($committed, 2),
            'disbursed' => round($disbursed, 2),
            'variance' => $variance,
            'utilization' => $utilization,
        ];
    }

    private function buildIfrCharts($commitments, $disbursements, Program $program, array $yearRange, string $mode, ?Carbon $startDate, ?Carbon $endDate): array
    {
        $periodCommitments = [];
        $periodDisbursements = [];
        $diffMonths = $startDate && $endDate ? $startDate->diffInMonths($endDate) : 0;
        $useMonthly = $mode === 'range' && $diffMonths <= 18;

        foreach ($commitments as $commitment) {
            $date = $this->resolveCommitmentDate($commitment);
            $key = $useMonthly
                ? $date->format('M Y')
                : $this->periodKey($date, $mode);
            $periodCommitments[$key] = ($periodCommitments[$key] ?? 0) + (float) $commitment->commitment_amount;
        }

        foreach ($disbursements as $disbursement) {
            $date = $this->resolveDisbursementDate($disbursement);
            $key = $useMonthly
                ? $date->format('M Y')
                : $this->periodKey($date, $mode);
            $periodDisbursements[$key] = ($periodDisbursements[$key] ?? 0) + (float) $disbursement->amount;
        }

        $lineLabels = array_values(array_unique(array_merge(array_keys($periodCommitments), array_keys($periodDisbursements))));
        sort($lineLabels);

        $lineCommitments = array_map(fn ($key) => round((float) ($periodCommitments[$key] ?? 0), 2), $lineLabels);
        $lineDisbursements = array_map(fn ($key) => round((float) ($periodDisbursements[$key] ?? 0), 2), $lineLabels);

        $commitmentByYear = array_fill_keys($yearRange, 0);
        $disbursementByYear = array_fill_keys($yearRange, 0);

        foreach ($commitments as $commitment) {
            $date = $this->resolveCommitmentDate($commitment);
            $year = $date->year;
            if (array_key_exists($year, $commitmentByYear)) {
                $commitmentByYear[$year] += (float) $commitment->commitment_amount;
            }
        }

        foreach ($disbursements as $disbursement) {
            $date = $this->resolveDisbursementDate($disbursement);
            $year = $date->year;
            if (array_key_exists($year, $disbursementByYear)) {
                $disbursementByYear[$year] += (float) $disbursement->amount;
            }
        }

        $barLabels = array_map('strval', array_keys($commitmentByYear));
        $barCommitments = array_map(fn ($value) => round((float) $value, 2), array_values($commitmentByYear));
        $barDisbursements = array_map(fn ($value) => round((float) $value, 2), array_values($disbursementByYear));

        $bubbleData = [];
        foreach ($program->projects as $project) {
            foreach ($project->activities as $activity) {
                foreach ($activity->subActivities as $subActivity) {
                    $committed = (float) $commitments
                        ->where('allocation_id', $subActivity->id)
                        ->sum('commitment_amount');
                    $disbursed = (float) $disbursements
                        ->where('sub_activity_id', $subActivity->id)
                        ->sum('amount');
                    if ($committed <= 0 && $disbursed <= 0) {
                        continue;
                    }
                    $bubbleData[] = [
                        'x' => round($committed, 2),
                        'y' => round($disbursed, 2),
                        'r' => max(4, min(18, sqrt(max($disbursed, 1)))),
                        'label' => $subActivity->name,
                    ];
                }
            }
        }

        return [
            'line' => [
                'labels' => $lineLabels,
                'commitments' => $lineCommitments,
                'disbursements' => $lineDisbursements,
            ],
            'bar' => [
                'labels' => $barLabels,
                'commitments' => $barCommitments,
                'disbursements' => $barDisbursements,
            ],
            'bubble' => $bubbleData,
        ];
    }

    private function buildIfrSummary(array $totals, array $rows, string $label): array
    {
        $committed = $totals['committed'];
        $disbursed = $totals['disbursed'];
        $utilization = $totals['utilization'];
        $cappedUtilization = min(100, max(0, $utilization));
        $variance = $totals['variance'];

        $summary = [];
        $summary[] = "Coverage period: {$label}.";

        if ($committed <= 0) {
            $summary[] = 'No commitments were found for the selected period, so disbursements cannot be compared.';
        } else {
            $summary[] = sprintf(
                'Total committed is %s, while actual disbursements are %s (%s%% utilization).',
                number_format($committed, 2),
                number_format($disbursed, 2),
                number_format($cappedUtilization, 2)
            );

            if ($utilization < 50) {
                $summary[] = 'Disbursement levels are low compared to commitments. Monitor delivery progress and payment schedules.';
            } elseif ($utilization < 80) {
                $summary[] = 'Disbursements are moderate; there is still room to execute the remaining commitment balance.';
            } elseif ($utilization <= 100) {
                $summary[] = 'Disbursements are on track and within committed limits.';
            } else {
                $summary[] = 'Disbursements exceed commitments. Investigate overpayments or unapproved disbursement activity.';
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
                    $utilization = min(100, max(0, (float) $row['utilization']));
                    return $row['subActivity']->name . ' (' . number_format($utilization, 2) . '%)';
                })
                ->implode(', ');
            $summary[] = 'Top over-disbursed sub-activities: ' . $top . '.';
        }

        if ($variance > 0) {
            $summary[] = 'Remaining committed balance: ' . number_format($variance, 2) . '.';
        }

        return $summary;
    }

    private function formatReferenceDisplay(array $references): array
    {
        $references = array_values(array_filter($references));
        if (empty($references)) {
            return [
                'display' => '—',
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
}
