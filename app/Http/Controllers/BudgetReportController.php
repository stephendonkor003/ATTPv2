<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use App\Models\Program;
use App\Models\Project;
use App\Models\Activity;
use App\Models\BudgetCommitment;
use App\Models\ProgramFunding;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPurchaseOrder;
use App\Models\SystemAuditLog;

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
            'projects.activities.subActivities.allocations',
            'approvedFundings.funder',
            'fundings.funder',
        ])->findOrFail($programId);

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
            . '-' . now()->format('Ymd-His') . '.pdf';

        return PDF::loadView('budgetreport.project-financial-position-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function buildProjectFinancialPositionReportData(Request $request): array
    {
        $programs = Program::orderBy('name')->get();
        $selectedProgramId = $request->input('program_id') ?: $programs->first()?->id;
        $program = null;
        $position = null;
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
                'projects.allocations',
                'projects.activities.allocations',
                'projects.activities.subActivities.allocations',
                'approvedFundings.funder',
                'fundings.funder',
            ])->findOrFail($selectedProgramId);

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
            $position = $this->buildProjectFinancialPosition($program, $fundings->pluck('id')->all(), $filters);
        }

        return [
            'programs' => $programs,
            'selectedProgramId' => $selectedProgramId,
            'program' => $program,
            'position' => $position,
            'funders' => $funders,
            'fundingOptions' => $fundingOptions,
            'structureOptions' => $structureOptions,
            'structureFilterLabel' => $structureFilterLabel,
            'filters' => $filters,
            'query' => $request->query(),
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
        if ($fundings->isEmpty()) {
            $fundings = ProgramFunding::query()
                ->where('program_name', $program->name)
                ->get();
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
        if (!empty($commitment->commitment_year)) {
            return Carbon::create((int) $commitment->commitment_year, 1, 1);
        }

        if ($commitment->purchaseRequest?->commitment_date) {
            return Carbon::parse($commitment->purchaseRequest->commitment_date)->startOfDay();
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

        if ($mode === 'multi_year') {
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

    private function buildProjectFinancialPosition(Program $program, array $programFundingIds, array $filters = []): array
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
                ->where('status', BudgetCommitment::STATUS_APPROVED)
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

        $invoices = (empty($invoiceIdsFromPurchaseOrders) && empty($subActivityIds))
            ? collect()
            : ProcurementInvoice::query()
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
            : ProcurementDisbursement::query()
                ->where(function ($query) use ($purchaseOrderIds, $subActivityIds) {
                    if (! empty($purchaseOrderIds)) {
                        $query->whereIn('purchase_order_id', $purchaseOrderIds);
                    }

                    if (! empty($subActivityIds)) {
                        $method = empty($purchaseOrderIds) ? 'whereIn' : 'orWhereIn';
                        $query->{$method}('sub_activity_id', $subActivityIds);
                    }
                })
                ->whereNotIn('status', ['cancelled', 'void', 'failed'])
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

            $projectRows->push($projectRow);
            $totals = $this->addProjectPositionTotals($totals, $projectRow);
        }

        $displayRows = $this->filterProjectPositionRows($projectRows, $filters);

        $totals['approved_funding'] = round($approvedFunding, 2);
        $totals['funding_balance'] = round($approvedFunding - $totals['disbursed'], 2);
        $totals['allocation_balance'] = round($approvedFunding - $totals['budget'], 2);
        $totals['uncommitted_budget'] = round($totals['budget'] - $totals['committed'], 2);
        $totals['unpaid_commitments'] = round($totals['committed'] - $totals['disbursed'], 2);
        $totals['invoice_balance'] = round($totals['invoiced'] - $totals['disbursed'], 2);
        $totals['commitment_rate'] = $totals['budget'] > 0 ? round(($totals['committed'] / $totals['budget']) * 100, 1) : 0;
        $totals['disbursement_rate'] = $totals['committed'] > 0 ? round(($totals['disbursed'] / $totals['committed']) * 100, 1) : 0;

        return [
            'currency' => $fundings->first()?->currency ?? $program->currency ?? 'USD',
            'rows' => $displayRows,
            'all_rows' => $projectRows,
            'totals' => $totals,
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
            'unpaid_commitments' => round($committed - $disbursed, 2),
            'po_balance' => round($purchaseOrders - $disbursed, 2),
            'invoice_balance' => round($invoiced - $disbursed, 2),
            'commitment_rate' => $budget > 0 ? round(($committed / $budget) * 100, 1) : 0,
            'disbursement_rate' => $committed > 0 ? round(($disbursed / $committed) * 100, 1) : 0,
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
