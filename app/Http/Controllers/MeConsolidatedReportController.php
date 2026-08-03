<?php

namespace App\Http\Controllers;

use App\Exports\ConsolidatedMeReportExport;
use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\ConsortiumThinkTank;
use App\Models\MeDisaggregationDimension;
use App\Models\MeIndicatorAchievement;
use App\Models\MeIndicatorAchievementDisaggregation;
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
            'consolidated' => $service->build($approvedReports, $filters),
            'thinkTanks' => $thinkTanks,
            'filters' => $filters,
            'years' => MePerformanceReport::query()->distinct()->orderByDesc('reporting_year')->pluck('reporting_year'),
            'periodTypes' => MePerformanceReport::REPORTING_PERIOD_TYPES,
            'periodLabels' => MePerformanceReport::PERIOD_LABELS,
            'portfolios' => $portfolios->get(['id', 'name']),
            'disaggregationOptions' => $this->disaggregationOptions($request),
        ]);
    }

    public function excel(Request $request, MeConsolidatedReportingService $service)
    {
        $filters = $this->filters($request);
        $reports = $this->approvedQuery($request, $filters)->with($this->relations())->get();
        $rows = $service->build($reports, $filters);

        return Excel::download(
            new ConsolidatedMeReportExport($rows, $filters),
            'ATTP-Consolidated-MEL-'.$filters['year'].'-'.$filters['period_label'].'.xlsx'
        );
    }

    public function pdf(Request $request, MeConsolidatedReportingService $service)
    {
        $filters = $this->filters($request);
        $reports = $this->approvedQuery($request, $filters)->with($this->relations())->get();
        $rows = $service->build($reports, $filters);

        return Pdf::loadView('me.consolidated-reports.pdf', [
            'consolidated' => $rows,
            'reports' => $reports,
            'filters' => $filters,
            'selectedThinkTank' => filled($filters['think_tank_id'])
                ? ConsortiumThinkTank::query()->find($filters['think_tank_id'])
                : null,
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
            ->when($filters['portfolio_id'], fn ($query, $portfolioId) => $query->where('portfolio_id', $portfolioId))
            ->when($filters['think_tank_id'], fn ($query, $thinkTankId) => $query->where('think_tank_member_id', $thinkTankId));
        $this->applyDisaggregationFilters($query, $filters);
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
            'think_tank_id' => $request->filled('think_tank_id') ? (string) $request->query('think_tank_id') : null,
            'geographic_scope' => $this->allowedFilter($request, 'geographic_scope', MeIndicatorAchievement::GEOGRAPHIC_SCOPES),
            'country' => $request->filled('country') ? trim((string) $request->query('country')) : null,
            'rec' => $this->allowedFilter($request, 'rec', MeIndicatorAchievement::RECS),
            'implementing_institution_type' => $this->allowedFilter($request, 'implementing_institution_type', MeIndicatorAchievement::INSTITUTION_TYPES),
            'implementing_institution' => $request->filled('implementing_institution') ? trim((string) $request->query('implementing_institution')) : null,
            'priority_theme' => $this->allowedFilter($request, 'priority_theme', MeIndicatorAchievement::PRIORITY_THEMES),
            'gender' => $this->allowedFilter($request, 'gender', MeIndicatorAchievement::GENDERS),
            'age_group' => $this->allowedFilter($request, 'age_group', MeIndicatorAchievement::AGE_GROUPS),
            'stakeholder_category' => $this->allowedFilter($request, 'stakeholder_category', MeIndicatorAchievement::STAKEHOLDER_CATEGORIES),
        ];
    }

    private function applyDisaggregationFilters(Builder $query, array $filters): void
    {
        $keys = [
            'geographic_scope', 'country', 'rec', 'implementing_institution_type',
            'implementing_institution', 'priority_theme', 'gender', 'age_group',
            'stakeholder_category',
        ];
        if (! collect($keys)->contains(fn (string $key): bool => filled($filters[$key] ?? null))) {
            return;
        }

        $query->whereHas('indicatorResults.achievements.breakdowns', function (Builder $breakdownQuery) use ($filters, $keys): void {
            foreach ($keys as $key) {
                if (filled($filters[$key] ?? null)) {
                    $breakdownQuery->where($key, $filters[$key]);
                }
            }
        });
    }

    private function allowedFilter(Request $request, string $key, array $options): ?string
    {
        $value = trim((string) $request->query($key));

        return array_key_exists($value, $options) ? $value : null;
    }

    private function disaggregationOptions(Request $request): array
    {
        $countries = MeDisaggregationDimension::query()
            ->where('code', 'country')
            ->with(['options' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->first()?->options?->pluck('name', 'name')->all() ?? [];
        $institutions = MeIndicatorAchievementDisaggregation::query()
            ->whereNotNull('implementing_institution')
            ->where('implementing_institution', '!=', '')
            ->distinct()
            ->orderBy('implementing_institution')
            ->pluck('implementing_institution', 'implementing_institution')
            ->all();

        return [
            'geographic_scopes' => MeIndicatorAchievement::GEOGRAPHIC_SCOPES,
            'countries' => $countries,
            'recs' => MeIndicatorAchievement::RECS,
            'institution_types' => MeIndicatorAchievement::INSTITUTION_TYPES,
            'institutions' => $institutions,
            'priority_themes' => MeIndicatorAchievement::PRIORITY_THEMES,
            'genders' => MeIndicatorAchievement::GENDERS,
            'age_groups' => MeIndicatorAchievement::AGE_GROUPS,
            'stakeholder_categories' => MeIndicatorAchievement::STAKEHOLDER_CATEGORIES,
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
