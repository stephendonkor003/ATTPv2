<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\MeDisaggregationDimension;
use App\Models\MeIndicatorAchievement;
use App\Models\MeIndicatorAchievementDisaggregation;
use App\Models\MePerformanceReport;
use App\Models\MePerformanceReportIndicatorResult;
use App\Models\Project;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'indicatorResults:id,report_id,indicator_id,indicator_result_id,actual_value,actual_text,rollup_numerator,rollup_denominator',
                'indicatorResults.indicator:id,indicator_code,name,results_level,value_type,organization_rollup_method',
                'indicatorResults.achievements:id,report_indicator_result_id',
                'documents:id,report_id',
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
        $awaitingVerification = (int) ($distribution->firstWhere('key', 'submitted')['count'] ?? 0);
        $awaitingApproval = (int) ($distribution->firstWhere('key', 'verified')['count'] ?? 0);
        $awaitingReview = $awaitingVerification + $awaitingApproval;

        $reviewMinutes = $reports
            ->filter(fn (MePerformanceReport $report): bool => $report->submitted_at !== null
                && $report->reviewed_at !== null
                && $report->reviewed_at->greaterThanOrEqualTo($report->submitted_at))
            ->map(fn (MePerformanceReport $report): int => (int) $report->submitted_at
                ->diffInMinutes($report->reviewed_at));
        $averageReviewMinutes = $reviewMinutes->isEmpty()
            ? null
            : (int) round($reviewMinutes->average());
        $approvalMinutes = $reports
            ->filter(fn (MePerformanceReport $report): bool => $report->submitted_at !== null
                && $report->approved_at !== null
                && $report->approved_at->greaterThanOrEqualTo($report->submitted_at))
            ->map(fn (MePerformanceReport $report): int => (int) $report->submitted_at
                ->diffInMinutes($report->approved_at));
        $averageApprovalMinutes = $approvalMinutes->isEmpty()
            ? null
            : (int) round($approvalMinutes->average());

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
            ->filter(fn ($result): bool => $this->resultIsComplete($result))
            ->count();
        $indicatorTotal = $indicatorResults->count();
        $indicatorCompleteness = $this->percentage($reportedIndicators, $indicatorTotal);
        $submissionReadyCount = $reports
            ->filter(fn (MePerformanceReport $report): bool => $report->isSubmissionReady())
            ->count();
        $evidenceReportCount = $reports
            ->filter(fn (MePerformanceReport $report): bool => $report->documents->isNotEmpty())
            ->count();
        $submissionReadiness = $this->percentage($submissionReadyCount, $totalReports);
        $evidenceCoverage = $this->percentage($evidenceReportCount, $totalReports);

        $ratingColors = [
            'exceptional' => '#187459',
            'on_track' => '#0e7490',
            'at_risk' => '#a56a17',
            'off_track' => '#ae3f3d',
            'not_rated' => '#64748b',
        ];
        $ratingDistribution = collect(MePerformanceReport::PERFORMANCE_RATINGS)
            ->map(function (string $label, string $key) use ($reports, $totalReports, $ratingColors): array {
                $count = $key === 'not_rated'
                    ? $reports->filter(fn (MePerformanceReport $report): bool => blank($report->performance_rating)
                        || $report->performance_rating === 'not_rated')->count()
                    : $reports->where('performance_rating', $key)->count();

                return [
                    'key' => $key,
                    'label' => $label,
                    'color' => $ratingColors[$key] ?? '#64748b',
                    'count' => $count,
                    'percentage' => $this->percentage($count, $totalReports),
                ];
            })
            ->values();

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
        $attentionReports = $reports
            ->filter(fn (MePerformanceReport $report): bool => in_array($this->stageKey($report), ['submitted', 'verified'], true)
                || $this->timelinessKey($report) === 'overdue')
            ->sortBy(function (MePerformanceReport $report): string {
                $overduePriority = $this->timelinessKey($report) === 'overdue' ? '0' : '1';
                $dueAt = $report->assignment?->collection?->due_at?->format('YmdHis') ?? '99999999999999';

                return $overduePriority.'|'.$dueAt;
            })
            ->take(6)
            ->map(function (MePerformanceReport $report): array {
                $stage = $this->stageKey($report);
                $timeliness = $this->timelinessKey($report);

                return [
                    'id' => $report->id,
                    'title' => $report->form?->title ?: 'Performance report',
                    'owner' => $report->thinkTank?->name ?: 'Secretariat / Internal',
                    'period' => $report->periodLabel(),
                    'stage' => $stage,
                    'stage_label' => self::STAGES[$stage]['label'] ?? Str::headline($stage),
                    'timeliness' => $timeliness,
                    'reason' => $timeliness === 'overdue'
                        ? 'Submission is past the linked collection deadline.'
                        : ($stage === 'verified' ? 'Verified and awaiting final approval.' : 'Submitted and awaiting verification.'),
                    'due_at' => $report->assignment?->collection?->due_at,
                ];
            })
            ->values();

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
                'indicatorResults:id,report_id,indicator_id,indicator_result_id,actual_value,actual_text,rollup_numerator,rollup_denominator',
                'indicatorResults.indicator:id,indicator_code,name,value_type,organization_rollup_method',
                'indicatorResults.achievements:id,report_indicator_result_id',
                'documents:id,report_id',
            ])
            ->withCount([
                'documents',
            ])
            ->withExists([
                'transitions as has_return_transition' => fn ($query) => $query
                    ->where('action', 'returned_for_correction'),
            ])
            ->tap(fn (Builder $query): Builder => $this->applyRecordSort($query, $filters['sort']))
            ->paginate($filters['per_page'], ['*'], 'records_page')
            ->withQueryString();
        $records->getCollection()->each(function (MePerformanceReport $report): void {
            $this->decorateReport($report);
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
            'awaitingVerification' => $awaitingVerification,
            'awaitingApproval' => $awaitingApproval,
            'averageReviewMinutes' => $averageReviewMinutes,
            'averageReviewLabel' => $this->durationLabel($averageReviewMinutes),
            'reviewDecisionCount' => $reviewMinutes->count(),
            'averageApprovalMinutes' => $averageApprovalMinutes,
            'averageApprovalLabel' => $this->durationLabel($averageApprovalMinutes),
            'approvalDecisionCount' => $approvalMinutes->count(),
            'indicatorCompleteness' => $indicatorCompleteness,
            'reportedIndicators' => $reportedIndicators,
            'indicatorTotal' => $indicatorTotal,
            'submissionReadyCount' => $submissionReadyCount,
            'submissionReadiness' => $submissionReadiness,
            'evidenceReportCount' => $evidenceReportCount,
            'evidenceCoverage' => $evidenceCoverage,
            'ratingDistribution' => $ratingDistribution,
            'reportsByThinkTank' => $reportsByThinkTank,
            'reportsByComponent' => $reportsByComponent,
            'reportsByPeriod' => $reportsByPeriod,
            'attentionReports' => $attentionReports,
            'drilldown' => $drilldown,
            'drilldownLabel' => $this->drilldownLabel($drilldown),
            'records' => $records,
            'stageConfiguration' => self::STAGES,
            'timelinessConfiguration' => self::TIMELINESS,
            'generatedAt' => now(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $reports = $this->applyFilters($this->scopedReportQuery($request), $filters)
            ->with([
                'form:id,code,title',
                'portfolio:id,name',
                'projectComponent:id,project_id,name',
                'thinkTank:id,name,role,country',
                'assignment:id,collection_id',
                'assignment.collection:id,due_at',
                'indicatorResults:id,report_id,indicator_id,indicator_result_id,actual_value,actual_text,rollup_numerator,rollup_denominator',
                'indicatorResults.indicator:id,indicator_code,name,value_type,organization_rollup_method',
                'indicatorResults.achievements:id,report_indicator_result_id',
                'documents:id,report_id',
            ])
            ->withExists([
                'transitions as has_return_transition' => fn ($query) => $query
                    ->where('action', 'returned_for_correction'),
            ])
            ->tap(fn (Builder $query): Builder => $this->applyRecordSort($query, $filters['sort']))
            ->get();
        $reports->each(fn (MePerformanceReport $report) => $this->decorateReport($report));
        $filename = 'ATTP-MEL-reporting-workflow-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($reports): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [
                'Report ID', 'Form Code', 'Report', 'Owner', 'Country', 'Portfolio',
                'Project Component', 'Reporting Period', 'Workflow Stage', 'Timeliness',
                'Deadline', 'Submitted At', 'Verified At', 'Approved At', 'Indicator Results',
                'Complete Indicator Results', 'Required Sections Complete', 'Submission Ready',
                'Supporting Documents', 'Performance Rating', 'Last Updated',
            ], ',', '"', '');
            foreach ($reports as $report) {
                fputcsv($stream, [
                    $report->id,
                    $report->form?->code,
                    $report->form?->title,
                    $report->thinkTank?->name ?: 'Secretariat / Internal',
                    $report->thinkTank?->country,
                    $report->portfolio?->name,
                    $report->projectComponent?->name,
                    $report->periodLabel(),
                    self::STAGES[$report->dashboard_stage]['label'] ?? Str::headline($report->dashboard_stage),
                    self::TIMELINESS[$report->dashboard_timeliness]['label'] ?? Str::headline($report->dashboard_timeliness),
                    $report->assignment?->collection?->due_at?->toIso8601String(),
                    $report->submitted_at?->toIso8601String(),
                    $report->verified_at?->toIso8601String(),
                    $report->approved_at?->toIso8601String(),
                    $report->indicator_results_count,
                    $report->reported_indicator_results_count,
                    $report->dashboard_completed_sections.'/7',
                    $report->dashboard_submission_ready ? 'Yes' : 'No',
                    $report->documents->count(),
                    MePerformanceReport::PERFORMANCE_RATINGS[$report->performance_rating] ?? 'Not Rated',
                    $report->updated_at?->toIso8601String(),
                ], ',', '"', '');
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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
        $disaggregationKeys = [
            'geographic_scope', 'country', 'rec', 'implementing_institution_type',
            'implementing_institution', 'priority_theme', 'gender', 'age_group',
            'stakeholder_category',
        ];
        if (collect($disaggregationKeys)->contains(fn (string $key): bool => filled($filters[$key] ?? null))) {
            $query->whereHas('indicatorResults.achievements.breakdowns', function (Builder $breakdownQuery) use ($filters, $disaggregationKeys): void {
                foreach ($disaggregationKeys as $key) {
                    if (filled($filters[$key] ?? null)) {
                        $breakdownQuery->where($key, $filters[$key]);
                    }
                }
            });
        }

        return $query
            ->when(filled($filters['q']), function (Builder $builder) use ($filters): Builder {
                $term = '%'.mb_strtolower($filters['q']).'%';

                return $builder->where(function (Builder $search) use ($term): void {
                    $search
                        ->whereRaw('LOWER(reporting_period_label) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(performance_rating) LIKE ?', [$term])
                        ->orWhereHas('form', fn (Builder $form): Builder => $form
                            ->whereRaw('LOWER(title) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(code) LIKE ?', [$term]))
                        ->orWhereHas('thinkTank', fn (Builder $thinkTank): Builder => $thinkTank
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(country) LIKE ?', [$term]))
                        ->orWhereHas('projectComponent', fn (Builder $component): Builder => $component
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(project_id) LIKE ?', [$term]))
                        ->orWhereHas('portfolio', fn (Builder $portfolio): Builder => $portfolio
                            ->whereRaw('LOWER(name) LIKE ?', [$term]));
                });
            })
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
                ->applyStageFilter($builder, $filters['status']))
            ->when(filled($filters['performance_rating']), fn (Builder $builder): Builder => $filters['performance_rating'] === 'not_rated'
                ? $builder->where(function (Builder $rating): void {
                    $rating->whereNull('performance_rating')->orWhere('performance_rating', 'not_rated');
                })
                : $builder->where('performance_rating', $filters['performance_rating']));
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
            'approved' => $query->where('status', MePerformanceReport::STATUS_APPROVED),
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
        $performanceRating = trim((string) $request->query('performance_rating'));
        $query = trim((string) $request->query('q'));
        $sort = trim((string) $request->query('sort', 'latest_period'));
        $perPage = (int) $request->query('per_page', 15);

        return [
            'q' => $query !== '' ? Str::limit($query, 120, '') : null,
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
            'performance_rating' => array_key_exists($performanceRating, MePerformanceReport::PERFORMANCE_RATINGS)
                ? $performanceRating
                : null,
            'geographic_scope' => $this->allowedFilter($request, 'geographic_scope', MeIndicatorAchievement::GEOGRAPHIC_SCOPES),
            'country' => $request->filled('country') ? trim((string) $request->query('country')) : null,
            'rec' => $this->allowedFilter($request, 'rec', MeIndicatorAchievement::RECS),
            'implementing_institution_type' => $this->allowedFilter($request, 'implementing_institution_type', MeIndicatorAchievement::INSTITUTION_TYPES),
            'implementing_institution' => $request->filled('implementing_institution') ? trim((string) $request->query('implementing_institution')) : null,
            'priority_theme' => $this->allowedFilter($request, 'priority_theme', MeIndicatorAchievement::PRIORITY_THEMES),
            'gender' => $this->allowedFilter($request, 'gender', MeIndicatorAchievement::GENDERS),
            'age_group' => $this->allowedFilter($request, 'age_group', MeIndicatorAchievement::AGE_GROUPS),
            'stakeholder_category' => $this->allowedFilter($request, 'stakeholder_category', MeIndicatorAchievement::STAKEHOLDER_CATEGORIES),
            'sort' => in_array($sort, ['latest_period', 'oldest_period', 'recently_updated', 'workflow_stage'], true)
                ? $sort
                : 'latest_period',
            'per_page' => in_array($perPage, [15, 25, 50, 100], true) ? $perPage : 15,
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
            'performance_ratings' => MePerformanceReport::PERFORMANCE_RATINGS,
            'geographic_scopes' => MeIndicatorAchievement::GEOGRAPHIC_SCOPES,
            'countries' => MeDisaggregationDimension::query()
                ->where('code', 'country')
                ->with(['options' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
                ->first()?->options?->pluck('name', 'name')->all() ?? [],
            'recs' => MeIndicatorAchievement::RECS,
            'institution_types' => MeIndicatorAchievement::INSTITUTION_TYPES,
            'institutions' => MeIndicatorAchievementDisaggregation::query()
                ->whereHas('achievement', fn (Builder $achievement): Builder => $achievement
                    ->whereIn('report_id', $reportIds))
                ->whereNotNull('implementing_institution')
                ->where('implementing_institution', '!=', '')
                ->distinct()
                ->orderBy('implementing_institution')
                ->pluck('implementing_institution', 'implementing_institution')
                ->all(),
            'priority_themes' => MeIndicatorAchievement::PRIORITY_THEMES,
            'genders' => MeIndicatorAchievement::GENDERS,
            'age_groups' => MeIndicatorAchievement::AGE_GROUPS,
            'stakeholder_categories' => MeIndicatorAchievement::STAKEHOLDER_CATEGORIES,
        ];
    }

    private function allowedFilter(Request $request, string $key, array $options): ?string
    {
        $value = trim((string) $request->query($key));

        return array_key_exists($value, $options) ? $value : null;
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
            MePerformanceReport::STATUS_REVIEWED, MePerformanceReport::STATUS_VERIFIED => 'verified',
            MePerformanceReport::STATUS_APPROVED => 'approved',
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
                    fn ($result): bool => $this->resultIsComplete($result)
                ))->values();
        }
        if ($drilldown === 'indicator_incomplete') {
            return $reports->filter(fn (MePerformanceReport $report): bool => $report->indicatorResults->isEmpty()
                || $report->indicatorResults->contains(
                    fn ($result): bool => ! $this->resultIsComplete($result)
                ))->values();
        }
        if ($drilldown === 'review_queue') {
            return $reports->filter(fn (MePerformanceReport $report): bool => in_array(
                $this->stageKey($report),
                ['submitted', 'verified'],
                true
            ))->values();
        }
        if ($drilldown === 'submission_ready') {
            return $reports->filter(fn (MePerformanceReport $report): bool => $report->isSubmissionReady())->values();
        }
        if ($drilldown === 'submission_incomplete') {
            return $reports->reject(fn (MePerformanceReport $report): bool => $report->isSubmissionReady())->values();
        }
        if ($drilldown === 'evidence_present') {
            return $reports->filter(fn (MePerformanceReport $report): bool => $report->documents->isNotEmpty())->values();
        }
        if ($drilldown === 'evidence_missing') {
            return $reports->filter(fn (MePerformanceReport $report): bool => $report->documents->isEmpty())->values();
        }
        if ($drilldown === 'reviewed_decisions') {
            return $reports->filter(fn (MePerformanceReport $report): bool => $report->submitted_at !== null
                && $report->reviewed_at !== null
                && $report->reviewed_at->greaterThanOrEqualTo($report->submitted_at))->values();
        }
        if ($drilldown === 'approved_decisions') {
            return $reports->filter(fn (MePerformanceReport $report): bool => $report->submitted_at !== null
                && $report->approved_at !== null
                && $report->approved_at->greaterThanOrEqualTo($report->submitted_at))->values();
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
            'review_queue',
            'submission_ready',
            'submission_incomplete',
            'evidence_present',
            'evidence_missing',
            'reviewed_decisions',
            'approved_decisions',
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
            'review_queue' => 'Reports requiring Secretariat review or final approval',
            'submission_ready' => 'Reports with all seven required sections complete',
            'submission_incomplete' => 'Reports with one or more required sections incomplete',
            'evidence_present' => 'Reports with supporting evidence',
            'evidence_missing' => 'Reports without supporting evidence',
            'reviewed_decisions' => 'Reports included in average first-review time',
            'approved_decisions' => 'Reports included in average final-approval time',
            default => 'All matching report records',
        };
    }

    private function resultIsComplete(MePerformanceReportIndicatorResult $result): bool
    {
        $hasActual = $result->indicator?->value_type === 'milestone'
            ? filled($result->actual_text)
            : $result->actual_value !== null;

        return $hasActual && filled($result->indicator_result_id);
    }

    private function decorateReport(MePerformanceReport $report): void
    {
        $resultTotal = $report->indicatorResults->count();
        $resultComplete = $report->indicatorResults
            ->filter(fn (MePerformanceReportIndicatorResult $result): bool => $this->resultIsComplete($result))
            ->count();
        $sections = collect($report->sectionCompletion());
        $completedSections = $sections->where('status', 'complete')->count();

        $report->setAttribute('dashboard_stage', $this->stageKey($report));
        $report->setAttribute('dashboard_timeliness', $this->timelinessKey($report));
        $report->setAttribute('indicator_results_count', $resultTotal);
        $report->setAttribute('reported_indicator_results_count', $resultComplete);
        $report->setAttribute('documents_count', $report->documents->count());
        $report->setAttribute('dashboard_completed_sections', $completedSections);
        $report->setAttribute('dashboard_missing_sections', max(0, 7 - $completedSections));
        $report->setAttribute('dashboard_submission_ready', $sections->every(
            fn (array $section): bool => $section['status'] === 'complete'
        ));
    }

    private function applyRecordSort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'oldest_period' => $query
                ->orderBy('reporting_year')
                ->orderBy('reporting_period_type')
                ->orderBy('reporting_period_label')
                ->orderBy('created_at'),
            'recently_updated' => $query->orderByDesc('updated_at')->orderByDesc('created_at'),
            'workflow_stage' => $query
                ->orderByRaw("CASE status WHEN 'submitted' THEN 1 WHEN 'verified' THEN 2 WHEN 'draft' THEN 3 WHEN 'approved' THEN 4 WHEN 'archived' THEN 5 ELSE 6 END")
                ->orderByDesc('updated_at'),
            default => $query
                ->orderByDesc('reporting_year')
                ->orderByDesc('reporting_period_type')
                ->orderByDesc('reporting_period_label')
                ->orderByDesc('updated_at'),
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
