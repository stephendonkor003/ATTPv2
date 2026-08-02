<?php

namespace App\Http\Controllers;

use App\Exports\ConsolidatedMeReportExport;
use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\ConsortiumThinkTank;
use App\Models\MePerformanceReport;
use App\Models\Sector;
use App\Services\MeConsolidatedReportingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class MeConsolidatedReportController extends Controller
{
    use ScopesAssignedPortfolios;

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.performance_reports.view|me.performance_reports.review|me.configuration.view|me.configuration.manage');
    }

    public function index(Request $request, MeConsolidatedReportingService $service)
    {
        $filters = $this->filters($request);
        $reports = $this->query($request, $filters)
            ->with($this->relations())
            ->orderBy('status')
            ->orderBy('think_tank_member_id')
            ->get();
        $approvedReports = $reports->filter(fn ($report) => in_array($report->status, [
            MePerformanceReport::STATUS_APPROVED,
            MePerformanceReport::STATUS_ARCHIVED,
            MePerformanceReport::STATUS_REVIEWED,
        ], true));

        $thinkTanks = ConsortiumThinkTank::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'country', 'role']);

        $portfolios = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToSectors($portfolios, $request->user());
        }

        return view('me.consolidated-reports.index', [
            'reports' => $reports,
            'approvedReports' => $approvedReports,
            'consolidated' => $service->build($approvedReports),
            'thinkTanks' => $thinkTanks,
            'filters' => $filters,
            'years' => MePerformanceReport::query()->distinct()->orderByDesc('reporting_year')->pluck('reporting_year'),
            'periodTypes' => MePerformanceReport::REPORTING_PERIOD_TYPES,
            'periodLabels' => MePerformanceReport::PERIOD_LABELS,
            'portfolios' => $portfolios->get(['id', 'name']),
        ]);
    }

    public function excel(Request $request, MeConsolidatedReportingService $service)
    {
        $filters = $this->filters($request);
        $reports = $this->approvedQuery($request, $filters)->with($this->relations())->get();
        $rows = $service->build($reports);

        return Excel::download(
            new ConsolidatedMeReportExport($rows, $filters),
            'ATTP-Consolidated-MEL-'.$filters['year'].'-'.$filters['period_label'].'.xlsx'
        );
    }

    public function pdf(Request $request, MeConsolidatedReportingService $service)
    {
        $filters = $this->filters($request);
        $reports = $this->approvedQuery($request, $filters)->with($this->relations())->get();
        $rows = $service->build($reports);

        return Pdf::loadView('me.consolidated-reports.pdf', [
            'consolidated' => $rows,
            'reports' => $reports,
            'filters' => $filters,
            'generatedBy' => $request->user(),
        ])->setPaper('a4', 'landscape')->download(
            'ATTP-Consolidated-MEL-'.$filters['year'].'-'.$filters['period_label'].'.pdf'
        );
    }

    private function query(Request $request, array $filters): Builder
    {
        $query = MePerformanceReport::query()
            ->whereNotNull('think_tank_member_id')
            ->where('reporting_year', $filters['year'])
            ->where('reporting_period_type', $filters['period_type'])
            ->where('reporting_period_label', $filters['period_label'])
            ->when($filters['portfolio_id'], fn ($query, $portfolioId) => $query->where('portfolio_id', $portfolioId));
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query, $request->user());
        }

        return $query;
    }

    private function approvedQuery(Request $request, array $filters): Builder
    {
        return $this->query($request, $filters)->whereIn('status', [
            MePerformanceReport::STATUS_APPROVED,
            MePerformanceReport::STATUS_ARCHIVED,
            MePerformanceReport::STATUS_REVIEWED,
        ]);
    }

    private function filters(Request $request): array
    {
        $periodType = (string) $request->query('reporting_period_type', 'quarter');
        if (! isset(MePerformanceReport::REPORTING_PERIOD_TYPES[$periodType])) {
            $periodType = 'quarter';
        }
        $defaultLabel = array_key_first(MePerformanceReport::PERIOD_LABELS[$periodType]);
        $periodLabel = (string) $request->query('reporting_period_label', $defaultLabel);
        if (! isset(MePerformanceReport::PERIOD_LABELS[$periodType][$periodLabel])) {
            throw ValidationException::withMessages([
                'reporting_period_label' => 'The selected period does not belong to the selected reporting frequency.',
            ]);
        }

        return [
            'year' => max(2000, min(2100, (int) $request->query('reporting_year', now()->year))),
            'period_type' => $periodType,
            'period_label' => $periodLabel,
            'portfolio_id' => $request->filled('portfolio_id') ? (string) $request->query('portfolio_id') : null,
        ];
    }

    private function relations(): array
    {
        return [
            'form:id,code,title',
            'portfolio:id,name',
            'thinkTank:id,name,country,role',
            'approvedBy:id,name',
            'indicatorResults.indicator:id,indicator_code,name,organization_rollup_method,unit_id',
            'indicatorResults.indicator.unit:id,name,symbol',
            'indicatorResults.achievements.breakdowns',
        ];
    }
}
