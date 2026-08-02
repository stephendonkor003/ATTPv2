<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\MePerformanceReport;
use App\Models\MePerformanceReportIndicatorResult;
use App\Models\Project;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MePerformanceReportDashboardController extends Controller
{
    use ScopesAssignedPortfolios;

    private const STAGES = [
        'draft' => [
            'label' => 'Draft',
            'color' => '#64748b',
            'soft_color' => '#f1f5f9',
            'icon' => 'feather-edit-3',
        ],
        'submitted' => [
            'label' => 'Submitted',
            'color' => '#1676b8',
            'soft_color' => '#eaf5fc',
            'icon' => 'feather-send',
        ],
        'returned' => [
            'label' => 'Returned',
            'color' => '#d8941d',
            'soft_color' => '#fff7e5',
            'icon' => 'feather-corner-up-left',
        ],
        'verified' => [
            'label' => 'Verified',
            'color' => '#0e7490',
            'soft_color' => '#ecfeff',
            'icon' => 'feather-shield',
        ],
        'approved' => [
            'label' => 'Approved',
            'color' => '#15935d',
            'soft_color' => '#eaf8f0',
            'icon' => 'feather-check-circle',
        ],
        'archived' => [
            'label' => 'Archived',
            'color' => '#3e4a53',
            'soft_color' => '#eef1f3',
            'icon' => 'feather-archive',
        ],
    ];

    private const TIMELINESS = [
        'on_time' => ['label' => 'On time', 'color' => '#15935d'],
        'late' => ['label' => 'Submitted late', 'color' => '#dc6b2f'],
        'overdue' => ['label' => 'Overdue', 'color' => '#c43d38'],
        'pending' => ['label' => 'Pending within deadline', 'color' => '#d8941d'],
        'no_deadline' => ['label' => 'No deadline', 'color' => '#94a3b8'],
    ];

    public function __construct()
    {
        $this->middleware([
            'auth',
            'not.funding.partner',
            'permission:me.performance_reports.view|me.performance_reports.review|me.performance_reports.archive|me.data_entry.view|me.data_entry.manage|me.configuration.view|me.configuration.manage',
        ]);
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $scopeQuery = $this->scopedReportQuery($request);
        $filteredQuery = $this->applyFilters(clone $scopeQuery, $filters);

        $reports = $filteredQuery
            ->with([
                'portfolio:id,name',
                'projectComponent:id,project_id,name',
                'thinkTank:id,name,role,country',
                'form:id,code,title',
                'assignment:id,collection_id',
                'assignment.collection:id,due_at',
                'indicatorResults:id,report_id,indicator_id,indicator_result_id,actual_value',
                'indicatorResults.indicator:id,indicator_code,name,results_level',
            ])
            ->withExists([
                'transitions as has_return_transition' => fn ($query) => $query
                    ->where('action', 'returned_for_correction'),
            ])
            ->get();

        $totalReports = $reports->count();
        $distribution = collect(self::STAGES)
            ->map(function (array $configuration, string $stage) use ($reports, $totalReports): array {
                $count = $reports
                    ->filter(fn (MePerformanceReport $report): bool => $this->stageKey($report) === $stage)
                    ->count();

                return $configuration + [
                    'key' => $stage,
                    'count' => $count,
                    'percentage' => $this->percentage($count, $totalReports),
                ];
            })
            ->values();

        $timeliness = collect(self::TIMELINESS)
            ->map(function (array $configuration, string $key) use ($reports, $totalReports): array {
                $count = $reports
                    ->filter(fn (MePerformanceReport $report): bool => $this->timelinessKey($report) === $key)
                    ->count();

                return $configuration + [
                    'key' => $key,
                    'count' => $count,
                    'percentage' => $this->percentage($count, $totalReports),
                ];
            })
            ->values();

        $submittedWithDeadline = $timeliness
            ->whereIn('key', ['on_time', 'late'])
            ->sum('count');
        $onTimeCount = (int) ($timeliness->firstWhere('key', 'on_time')['count'] ?? 0);
        $onTimeRate = $this->percentage($onTimeCount, (int) $submittedWithDeadline);
        $overdueReports = (int) ($timeliness->firstWhere('key', 'overdue')['count'] ?? 0);
        $awaitingReview = (int) ($distribution->firstWhere('key', 'submitted')['count'] ?? 0);

        $reviewMinutes = $reports
            ->filter(fn (MePerformanceReport $report): bool => $report->submitted_at !== null
                && $report->reviewed_at !== null
                && $report->reviewed_at->greaterThanOrEqualTo($report->submitted_at))
            ->map(fn (MePerformanceReport $report): int => (int) $report->submitted_at
                ->diffInMinutes($report->reviewed_at));
        $averageReviewMinutes = $reviewMinutes->isEmpty()
            ? null
            : (int) round($reviewMinutes->average());

        $indicatorResults = $reports
            ->flatMap(fn (MePerformanceReport $report): Collection => $report->indicatorResults)
            ->when(
                filled($filters['indicator_id']),
                fn (Collection $results): Collection => $results->where('indicator_id', $filters['indicator_id'])
            )
            ->when(
                filled($filters['results_level']),
                fn (Collection $results): Collection => $results->filter(
                    fn ($result): bool => $result->indicator?->results_level === $filters['results_level']
                )
            );
        $reportedIndicators = $indicatorResults
            ->filter(fn ($result): bool => $result->actual_value !== null && filled($result->indicator_result_id))
            ->count();
        $indicatorTotal = $indicatorResults->count();
        $indicatorCompleteness = $this->percentage($reportedIndicators, $indicatorTotal);

        $reportsByThinkTank = $this->groupReports(
            $reports,
            fn (MePerformanceReport $report): string => (string) ($report->think_tank_member_id ?: 'internal'),
            function (MePerformanceReport $report): array {
                if (! $report->thinkTank) {
                    return ['Secretariat / Internal', 'Internal report'];
                }

                return [
                    $report->thinkTank->name,
                    Str::headline($report->thinkTank->role ?: 'think tank'),
                ];
            },
            $totalReports
        );
        $reportsByComponent = $this->groupReports(
            $reports,
            fn (MePerformanceReport $report): string => (string) $report->project_component_id,
            fn (MePerformanceReport $report): array => [
                $report->projectComponent?->name ?: 'Component unavailable',
                $report->projectComponent?->project_id ?: 'No component code',
            ],
            $totalReports
        );
        $reportsByPeriod = $this->groupReports(
            $reports,
            fn (MePerformanceReport $report): string => $report->reporting_year.'|'.$report->reporting_period_type.'|'.$report->reporting_period_label,
            fn (MePerformanceReport $report): array => [
                $report->periodLabel(),
                Str::headline((string) $report->reporting_period_type).' reporting period',
            ],
            $totalReports,
            true
        );

        $drilldown = $this->drilldown($request);
        $drilldownReports = $this->applyDrilldown($reports, $drilldown);
        $recordIds = $drilldownReports->pluck('id');
        $records = $this->applyFilters($this->scopedReportQuery($request), $filters)
            ->when(
                $recordIds->isEmpty(),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
                fn (Builder $query): Builder => $query->whereIn('id', $recordIds)
            )
            ->with([
                'form:id,code,title',
                'portfolio:id,name',
                'projectComponent:id,project_id,name',
                'thinkTank:id,name,role',
                'assignment:id,collection_id',
                'assignment.collection:id,due_at',
            ])
            ->withCount([
                'indicatorResults',
                'indicatorResults as reported_indicator_results_count' => fn ($query) => $query
                    ->whereNotNull('actual_value')
                    ->whereNotNull('indicator_result_id'),
                'documents',
            ])
            ->withExists([
                'transitions as has_return_transition' => fn ($query) => $query
                    ->where('action', 'returned_for_correction'),
            ])
            ->orderByDesc('reporting_year')
            ->orderByDesc('reporting_quarter')
            ->paginate(15, ['*'], 'records_page')
            ->withQueryString();
        $records->getCollection()->each(function (MePerformanceReport $report): void {
            $report->setAttribute('dashboard_stage', $this->stageKey($report));
            $report->setAttribute('dashboard_timeliness', $this->timelinessKey($report));
        });

        $filterOptions = $this->filterOptions($scopeQuery);

        return view('me.performance-reports.dashboard', [
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'distribution' => $distribution,
            'timeliness' => $timeliness,
            'totalReports' => $totalReports,
            'onTimeRate' => $onTimeRate,
            'overdueReports' => $overdueReports,
            'awaitingReview' => $awaitingReview,
            'averageReviewMinutes' => $averageReviewMinutes,
            'averageReviewLabel' => $this->durationLabel($averageReviewMinutes),
            'reviewDecisionCount' => $reviewMinutes->count(),
            'indicatorCompleteness' => $indicatorCompleteness,
            'reportedIndicators' => $reportedIndicators,
            'indicatorTotal' => $indicatorTotal,
            'reportsByThinkTank' => $reportsByThinkTank,
            'reportsByComponent' => $reportsByComponent,
            'reportsByPeriod' => $reportsByPeriod,
            'drilldown' => $drilldown,
            'drilldownLabel' => $this->drilldownLabel($drilldown),
            'records' => $records,
            'stageConfiguration' => self::STAGES,
            'timelinessConfiguration' => self::TIMELINESS,
            'generatedAt' => now(),
        ]);
    }

    private function scopedReportQuery(Request $request): Builder
    {
        $query = MePerformanceReport::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query, $request->user());
        }

        return $query;
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['reporting_year']), fn (Builder $builder): Builder => $builder
                ->where('reporting_year', $filters['reporting_year']))
            ->when(filled($filters['reporting_period_type']), fn (Builder $builder): Builder => $builder
                ->where('reporting_period_type', $filters['reporting_period_type']))
            ->when(filled($filters['reporting_period_label']), fn (Builder $builder): Builder => $builder
                ->where('reporting_period_label', $filters['reporting_period_label']))
            ->when(filled($filters['component_id']), fn (Builder $builder): Builder => $builder
                ->where('project_component_id', $filters['component_id']))
            ->when(filled($filters['results_level']), fn (Builder $builder): Builder => $builder
                ->whereHas('indicatorResults.indicator', fn (Builder $indicatorQuery): Builder => $indicatorQuery
                    ->where('results_level', $filters['results_level'])))
            ->when(filled($filters['think_tank_id']), function (Builder $builder) use ($filters): Builder {
                return $filters['think_tank_id'] === 'internal'
                    ? $builder->whereNull('think_tank_member_id')
                    : $builder->where('think_tank_member_id', $filters['think_tank_id']);
            })
            ->when(filled($filters['indicator_id']), fn (Builder $builder): Builder => $builder
                ->whereHas('indicatorResults', fn (Builder $resultQuery): Builder => $resultQuery
                    ->where('indicator_id', $filters['indicator_id'])))
            ->when(filled($filters['thematic_area_id']), fn (Builder $builder): Builder => $builder
                ->where('portfolio_id', $filters['thematic_area_id']))
            ->when(filled($filters['status']), fn (Builder $builder): Builder => $this
                ->applyStageFilter($builder, $filters['status']));
    }

    private function applyStageFilter(Builder $query, string $stage): Builder
    {
        return match ($stage) {
            'draft' => $query
                ->where('status', MePerformanceReport::STATUS_DRAFT)
                ->whereDoesntHave('transitions', fn (Builder $transitionQuery): Builder => $transitionQuery
                    ->where('action', 'returned_for_correction')),
            'returned' => $query
                ->where('status', MePerformanceReport::STATUS_DRAFT)
                ->whereHas('transitions', fn (Builder $transitionQuery): Builder => $transitionQuery
                    ->where('action', 'returned_for_correction')),
            'submitted' => $query->where('status', MePerformanceReport::STATUS_SUBMITTED),
            'verified' => $query->where('status', MePerformanceReport::STATUS_VERIFIED),
            'approved' => $query->whereIn('status', [MePerformanceReport::STATUS_REVIEWED, MePerformanceReport::STATUS_APPROVED]),
            'archived' => $query->where('status', MePerformanceReport::STATUS_ARCHIVED),
            default => $query,
        };
    }

    private function filters(Request $request): array
    {
        $year = filter_var($request->query('reporting_year'), FILTER_VALIDATE_INT);
        $periodType = trim((string) $request->query('reporting_period_type'));
        $periodLabel = trim((string) $request->query('reporting_period_label'));
        $resultsLevel = trim((string) $request->query('results_level'));
        $status = trim((string) $request->query('status'));
        $thinkTank = trim((string) $request->query('think_tank_id'));

        return [
            'reporting_year' => $year && $year >= 2000 && $year <= 2100 ? $year : null,
            'reporting_period_type' => array_key_exists($periodType, MePerformanceReport::REPORTING_PERIOD_TYPES) ? $periodType : null,
            'reporting_period_label' => $periodType && isset(MePerformanceReport::PERIOD_LABELS[$periodType][$periodLabel]) ? $periodLabel : null,
            'component_id' => $this->uuidOrNull($request->query('component_id')),
            'results_level' => in_array($resultsLevel, ['pdo', 'intermediate_results'], true)
                ? $resultsLevel
                : null,
            'think_tank_id' => $thinkTank === 'internal'
                ? 'internal'
                : $this->uuidOrNull($thinkTank),
            'indicator_id' => $this->uuidOrNull($request->query('indicator_id')),
            'thematic_area_id' => $this->uuidOrNull($request->query('thematic_area_id')),
            'status' => array_key_exists($status, self::STAGES) ? $status : null,
        ];
    }

    private function filterOptions(Builder $scopeQuery): array
    {
        $reportIds = (clone $scopeQuery)->pluck('id');
        $componentIds = (clone $scopeQuery)
            ->whereNotNull('project_component_id')
            ->distinct()
            ->pluck('project_component_id');
        $thinkTankIds = (clone $scopeQuery)
            ->whereNotNull('think_tank_member_id')
            ->distinct()
            ->pluck('think_tank_member_id');
        $thematicAreaIds = (clone $scopeQuery)
            ->whereNotNull('portfolio_id')
            ->distinct()
            ->pluck('portfolio_id');
        $indicatorIds = MePerformanceReportIndicatorResult::query()
            ->whereIn('report_id', $reportIds)
            ->distinct()
            ->pluck('indicator_id');

        return [
            'years' => (clone $scopeQuery)
                ->distinct()
                ->orderByDesc('reporting_year')
                ->pluck('reporting_year'),
            'period_types' => MePerformanceReport::REPORTING_PERIOD_TYPES,
            'period_labels' => MePerformanceReport::PERIOD_LABELS,
            'components' => Project::query()
                ->whereIn('id', $componentIds)
                ->orderBy('name')
                ->get(['id', 'project_id', 'name']),
            'think_tanks' => ConsortiumThinkTank::query()
                ->whereIn('id', $thinkTankIds)
                ->orderBy('name')
                ->get(['id', 'name', 'role']),
            'indicators' => Indicator::query()
                ->whereIn('id', $indicatorIds)
                ->orderBy('indicator_code')
                ->get(['id', 'indicator_code', 'name', 'results_level']),
            'thematic_areas' => Sector::query()
                ->whereIn('id', $thematicAreaIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'results_levels' => [
                'pdo' => 'PDO',
                'intermediate_results' => 'Intermediate Results',
            ],
            'statuses' => collect(self::STAGES)->mapWithKeys(
                fn (array $configuration, string $key): array => [$key => $configuration['label']]
            ),
        ];
    }

    private function groupReports(
        Collection $reports,
        callable $keyResolver,
        callable $labelResolver,
        int $total,
        bool $sortByKeyDescending = false
    ): Collection {
        $grouped = $reports
            ->groupBy($keyResolver)
            ->map(function (Collection $items, string $key) use ($labelResolver, $total): array {
                [$label, $subtitle] = $labelResolver($items->first());
                $count = $items->count();

                return [
                    'key' => $key,
                    'label' => $label,
                    'subtitle' => $subtitle,
                    'count' => $count,
                    'percentage' => $this->percentage($count, $total),
                ];
            });

        return ($sortByKeyDescending ? $grouped->sortKeysDesc() : $grouped->sortByDesc('count'))
            ->take(10)
            ->values();
    }

    private function stageKey(MePerformanceReport $report): string
    {
        if ($report->status === MePerformanceReport::STATUS_DRAFT) {
            return (bool) $report->getAttribute('has_return_transition')
                ? 'returned'
                : 'draft';
        }

        return match ($report->status) {
            MePerformanceReport::STATUS_SUBMITTED => 'submitted',
            MePerformanceReport::STATUS_VERIFIED => 'verified',
            MePerformanceReport::STATUS_REVIEWED, MePerformanceReport::STATUS_APPROVED => 'approved',
            MePerformanceReport::STATUS_ARCHIVED => 'archived',
            default => 'draft',
        };
    }

    private function timelinessKey(MePerformanceReport $report): string
    {
        $dueAt = $report->assignment?->collection?->due_at;
        if (! $dueAt) {
            return 'no_deadline';
        }

        if (in_array($this->stageKey($report), ['draft', 'returned'], true)) {
            return now()->greaterThan($dueAt) ? 'overdue' : 'pending';
        }

        if (! $report->submitted_at) {
            return now()->greaterThan($dueAt) ? 'overdue' : 'pending';
        }

        return $report->submitted_at->lessThanOrEqualTo($dueAt) ? 'on_time' : 'late';
    }

    private function applyDrilldown(Collection $reports, ?string $drilldown): Collection
    {
        if (! $drilldown) {
            return $reports;
        }
        if (Str::startsWith($drilldown, 'stage_')) {
            $stage = Str::after($drilldown, 'stage_');

            return $reports
                ->filter(fn (MePerformanceReport $report): bool => $this->stageKey($report) === $stage)
                ->values();
        }
        if (Str::startsWith($drilldown, 'timeliness_')) {
            $timeliness = Str::after($drilldown, 'timeliness_');

            return $reports
                ->filter(fn (MePerformanceReport $report): bool => $this->timelinessKey($report) === $timeliness)
                ->values();
        }
        if ($drilldown === 'indicator_complete') {
            return $reports->filter(fn (MePerformanceReport $report): bool => $report->indicatorResults->isNotEmpty()
                && $report->indicatorResults->every(
                    fn ($result): bool => $result->actual_value !== null && filled($result->indicator_result_id)
                ))->values();
        }
        if ($drilldown === 'indicator_incomplete') {
            return $reports->filter(fn (MePerformanceReport $report): bool => $report->indicatorResults->isEmpty()
                || $report->indicatorResults->contains(
                    fn ($result): bool => $result->actual_value === null || blank($result->indicator_result_id)
                ))->values();
        }
        if ($drilldown === 'reviewed_decisions') {
            return $reports->filter(fn (MePerformanceReport $report): bool => $report->submitted_at !== null
                && $report->reviewed_at !== null
                && $report->reviewed_at->greaterThanOrEqualTo($report->submitted_at))->values();
        }

        return $reports;
    }

    private function drilldown(Request $request): ?string
    {
        $value = trim((string) $request->query('drilldown'));
        $allowed = [
            ...collect(array_keys(self::STAGES))->map(fn (string $stage): string => 'stage_'.$stage)->all(),
            ...collect(array_keys(self::TIMELINESS))->map(fn (string $key): string => 'timeliness_'.$key)->all(),
            'indicator_complete',
            'indicator_incomplete',
            'reviewed_decisions',
        ];

        return in_array($value, $allowed, true) ? $value : null;
    }

    private function drilldownLabel(?string $drilldown): string
    {
        if (! $drilldown) {
            return 'All matching report records';
        }
        if (Str::startsWith($drilldown, 'stage_')) {
            $stage = Str::after($drilldown, 'stage_');

            return (self::STAGES[$stage]['label'] ?? Str::headline($stage)).' report records';
        }
        if (Str::startsWith($drilldown, 'timeliness_')) {
            $key = Str::after($drilldown, 'timeliness_');

            return (self::TIMELINESS[$key]['label'] ?? Str::headline($key)).' report records';
        }

        return match ($drilldown) {
            'indicator_complete' => 'Reports with complete indicator reporting',
            'indicator_incomplete' => 'Reports with incomplete indicator reporting',
            'reviewed_decisions' => 'Reports included in average review and approval time',
            default => 'All matching report records',
        };
    }

    private function percentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }

    private function durationLabel(?int $minutes): string
    {
        if ($minutes === null) {
            return 'No decisions yet';
        }
        if ($minutes < 60) {
            return $minutes.' min';
        }

        $hours = (int) floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        if ($hours < 24) {
            return $hours.'h'.($remainingMinutes > 0 ? ' '.$remainingMinutes.'m' : '');
        }

        $days = (int) floor($hours / 24);
        $remainingHours = $hours % 24;

        return $days.'d'.($remainingHours > 0 ? ' '.$remainingHours.'h' : '');
    }

    private function uuidOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return Str::isUuid($value) ? $value : null;
    }
}
