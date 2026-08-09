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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class MeConsolidatedReportController extends Controller
{
    use ScopesAssignedPortfolios;

    private const STAGES = [
        'draft' => ['label' => 'Draft', 'color' => '#64748b', 'soft_color' => '#f1f5f9'],
        'submitted' => ['label' => 'Submitted', 'color' => '#1676b8', 'soft_color' => '#eaf5fc'],
        'verified' => ['label' => 'Verified', 'color' => '#0e7490', 'soft_color' => '#ecfeff'],
        'approved' => ['label' => 'Approved', 'color' => '#187459', 'soft_color' => '#eaf8f0'],
        'archived' => ['label' => 'Archived', 'color' => '#3e4a53', 'soft_color' => '#eef1f3'],
    ];

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.performance_reports.view|me.performance_reports.review|me.configuration.view|me.configuration.manage');
    }

    public function index(Request $request, MeConsolidatedReportingService $service): View
    {
        $filters = $this->filters($request);
        $reports = $this->query($request, $filters)
            ->with($this->relations())
            ->orderByRaw("CASE status WHEN 'submitted' THEN 1 WHEN 'verified' THEN 2 WHEN 'reviewed' THEN 2 WHEN 'draft' THEN 3 WHEN 'approved' THEN 4 WHEN 'archived' THEN 5 ELSE 6 END")
            ->orderBy('think_tank_member_id')
            ->get();
        $approvedReports = $reports->filter(fn ($report) => in_array($report->status, [
            MePerformanceReport::STATUS_APPROVED,
            MePerformanceReport::STATUS_ARCHIVED,
        ], true));
        $consolidated = $service->build($approvedReports, $filters);

        $reportThinkTankIds = $reports->pluck('think_tank_member_id')->filter()->unique()->values();
        $thinkTanks = ConsortiumThinkTank::query()
            ->where(function (Builder $query) use ($reportThinkTankIds, $filters): void {
                $query->where('status', 'active');
                if ($reportThinkTankIds->isNotEmpty()) {
                    $query->orWhereIn('id', $reportThinkTankIds);
                }
                if (filled($filters['think_tank_id'])) {
                    $query->orWhereKey($filters['think_tank_id']);
                }
            })
            ->when(filled($filters['think_tank_id']), fn (Builder $query): Builder => $query
                ->whereKey($filters['think_tank_id']))
            ->orderBy('name')
            ->get(['id', 'name', 'country', 'role', 'status']);

        $organizationRows = $thinkTanks->map(function (ConsortiumThinkTank $thinkTank) use ($reports): array {
            $organizationReports = $reports
                ->where('think_tank_member_id', $thinkTank->id)
                ->values();
            $approved = $organizationReports->filter(fn (MePerformanceReport $report): bool => in_array(
                $report->status,
                [MePerformanceReport::STATUS_APPROVED, MePerformanceReport::STATUS_ARCHIVED],
                true
            ));

            return [
                'think_tank' => $thinkTank,
                'reports' => $organizationReports,
                'report_count' => $organizationReports->count(),
                'approved_count' => $approved->count(),
                'indicator_count' => $organizationReports->sum(fn (MePerformanceReport $report): int => $report->indicatorResults->count()),
                'document_count' => $organizationReports->sum(fn (MePerformanceReport $report): int => $report->documents->count()),
                'latest_update' => $organizationReports->max('updated_at'),
            ];
        })->values();
        $organizationCount = $organizationRows->count();
        $submittedOrganizationCount = $organizationRows->where('report_count', '>', 0)->count();
        $approvedOrganizationCount = $organizationRows->where('approved_count', '>', 0)->count();
        $coverageRate = $this->percentage($submittedOrganizationCount, $organizationCount);
        $approvalRate = $this->percentage($approvedOrganizationCount, $organizationCount);
        $stageDistribution = collect(self::STAGES)->map(function (array $configuration, string $stage) use ($reports): array {
            $count = $reports->filter(fn (MePerformanceReport $report): bool => $this->stageKey($report) === $stage)->count();

            return $configuration + ['key' => $stage, 'count' => $count];
        })->values();
        $totalAchievements = (int) $consolidated->sum('achievement_count');
        $totalBeneficiaries = (int) $consolidated->sum('beneficiary_count');
        $duplicateResultCount = (int) $consolidated->sum('duplicate_result_count');
        $evidenceDocumentCount = (int) $approvedReports->sum(fn (MePerformanceReport $report): int => $report->documents->count());
        $genderTotals = collect([
            'female' => (int) $consolidated->sum(fn (array $row): int => (int) $row['gender']->get('female', 0)),
            'male' => (int) $consolidated->sum(fn (array $row): int => (int) $row['gender']->get('male', 0)),
            'not_disaggregated' => (int) $consolidated->sum(fn (array $row): int => (int) $row['gender']->get('not_disaggregated', 0)),
        ]);
        $ageTotals = collect([
            'youth_below_35' => (int) $consolidated->sum(fn (array $row): int => (int) $row['age_groups']->get('youth_below_35', 0)),
            'adult_35_plus' => (int) $consolidated->sum(fn (array $row): int => (int) $row['age_groups']->get('adult_35_plus', 0)),
            'not_disaggregated' => (int) $consolidated->sum(fn (array $row): int => (int) $row['age_groups']->get('not_disaggregated', 0)),
        ]);

        $portfolios = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToSectors($portfolios, $request->user());
        }

        return view('me.consolidated-reports.index', [
            'reports' => $reports,
            'approvedReports' => $approvedReports,
            'consolidated' => $consolidated,
            'thinkTanks' => $thinkTanks,
            'organizationRows' => $organizationRows,
            'organizationCount' => $organizationCount,
            'submittedOrganizationCount' => $submittedOrganizationCount,
            'approvedOrganizationCount' => $approvedOrganizationCount,
            'coverageRate' => $coverageRate,
            'approvalRate' => $approvalRate,
            'stageDistribution' => $stageDistribution,
            'stageConfiguration' => self::STAGES,
            'totalAchievements' => $totalAchievements,
            'totalBeneficiaries' => $totalBeneficiaries,
            'duplicateResultCount' => $duplicateResultCount,
            'evidenceDocumentCount' => $evidenceDocumentCount,
            'genderTotals' => $genderTotals,
            'ageTotals' => $ageTotals,
            'filters' => $filters,
            'years' => $this->scopedReportQuery($request)->distinct()->orderByDesc('reporting_year')->pluck('reporting_year'),
            'periodTypes' => MePerformanceReport::REPORTING_PERIOD_TYPES,
            'periodLabels' => MePerformanceReport::PERIOD_LABELS,
            'portfolios' => $portfolios->get(['id', 'name']),
            'disaggregationOptions' => $this->disaggregationOptions($request, $this->scopedReportQuery($request)->pluck('id')),
            'generatedAt' => now(),
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
            'selectedPortfolio' => filled($filters['portfolio_id'])
                ? Sector::query()->find($filters['portfolio_id'])
                : null,
        ])->setPaper('a4', 'landscape')->download(
            'ATTP-Consolidated-MEL-'.$filters['year'].'-'.$filters['period_label'].'.pdf'
        );
    }

    private function query(Request $request, array $filters): Builder
    {
        $query = $this->scopedReportQuery($request)
            ->where('reporting_year', $filters['year'])
            ->where('reporting_period_type', $filters['period_type'])
            ->where('reporting_period_label', $filters['period_label'])
            ->when($filters['portfolio_id'], fn ($query, $portfolioId) => $query->where('portfolio_id', $portfolioId))
            ->when($filters['think_tank_id'], fn ($query, $thinkTankId) => $query->where('think_tank_member_id', $thinkTankId));
        $this->applyDisaggregationFilters($query, $filters);
        return $query;
    }

    private function scopedReportQuery(Request $request): Builder
    {
        $query = MePerformanceReport::query()->whereNotNull('think_tank_member_id');
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
            $periodLabel = $defaultLabel;
        }

        return [
            'year' => max(2000, min(2100, (int) $request->query('reporting_year', now()->year))),
            'period_type' => $periodType,
            'period_label' => $periodLabel,
            'portfolio_id' => $this->uuidOrNull($request->query('portfolio_id')),
            'think_tank_id' => $this->uuidOrNull($request->query('think_tank_id')),
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

    private function disaggregationOptions(Request $request, Collection $reportIds): array
    {
        $countries = MeDisaggregationDimension::query()
            ->where('code', 'country')
            ->with(['options' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->first()?->options?->pluck('name', 'name')->all() ?? [];
        $institutions = MeIndicatorAchievementDisaggregation::query()
            ->whereHas('achievement', fn (Builder $query): Builder => $query->whereIn('report_id', $reportIds))
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
            'indicatorResults.indicator:id,indicator_code,name,organization_rollup_method,unit_id,value_type,results_level',
            'indicatorResults.indicator.unit:id,name,symbol',
            'indicatorResults.achievements.breakdowns',
            'documents:id,report_id',
        ];
    }

    private function stageKey(MePerformanceReport $report): string
    {
        return match ($report->status) {
            MePerformanceReport::STATUS_SUBMITTED => 'submitted',
            MePerformanceReport::STATUS_REVIEWED, MePerformanceReport::STATUS_VERIFIED => 'verified',
            MePerformanceReport::STATUS_APPROVED => 'approved',
            MePerformanceReport::STATUS_ARCHIVED => 'archived',
            default => 'draft',
        };
    }

    private function percentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }

    private function uuidOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return Str::isUuid($value) ? $value : null;
    }
}
