<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\ConsortiumThinkTank;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeDataSubmission;
use App\Models\MePerformanceReport;
use App\Models\MePerformanceReportIndicatorResult;
use App\Models\MeReportingPeriod;
use App\Models\Sector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MeManagementDashboardController extends Controller
{
    use ScopesAssignedPortfolios;

    private const ACCESS_PERMISSION = 'me.results.view|me.performance_reports.view|me.performance_reports.review|me.performance_reports.archive|me.data_entry.view|me.data_entry.manage|me.dqa.manage|me.submissions.review|me.configuration.view|me.configuration.manage';

    private const REPORT_STAGES = [
        'draft' => ['label' => 'Draft', 'color' => '#64748b'],
        'submitted' => ['label' => 'Submitted', 'color' => '#1676b8'],
        'verified' => ['label' => 'Verified', 'color' => '#0e7490'],
        'approved' => ['label' => 'Approved', 'color' => '#15935d'],
        'archived' => ['label' => 'Archived', 'color' => '#3e4a53'],
    ];

    private const RATING_CONFIGURATION = [
        'exceptional' => ['label' => 'Exceptional', 'color' => '#187459'],
        'on_track' => ['label' => 'On track', 'color' => '#0e7490'],
        'at_risk' => ['label' => 'At risk', 'color' => '#a56a17'],
        'off_track' => ['label' => 'Off track', 'color' => '#ae3f3d'],
        'not_rated' => ['label' => 'Not rated', 'color' => '#64748b'],
    ];

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner', 'permission:'.self::ACCESS_PERMISSION]);
    }

    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $portfolios = $this->authorizedPortfolios($request);
        $this->assertAuthorizedFilter($filters['portfolio_id'], $portfolios, 'portfolio');

        $periodOptions = $this->periodOptions($request, $filters['reporting_year']);
        $this->assertAuthorizedFilter($filters['reporting_period_id'], $periodOptions, 'reporting period');

        $thinkTanks = $this->thinkTankOptions($request);
        $this->assertAuthorizedFilter($filters['think_tank_id'], $thinkTanks, 'reporting organization');

        $reports = $this->applyReportFilters($this->scopedReportQuery($request), $filters)
            ->with([
                'portfolio:id,name',
                'thinkTank:id,name,role,country',
                'form:id,code,title',
                'reportingPeriod:id,label,reporting_year,submission_deadline,review_deadline',
                'indicatorResults:id,report_id,indicator_id,indicator_result_id,actual_value,actual_text,target_achievement_percent',
                'indicatorResults.indicator:id,indicator_code,name,value_type',
                'documents:id,report_id,validation_status',
            ])
            ->latest('updated_at')
            ->get();

        $assignments = $this->applyAssignmentFilters($this->scopedAssignmentQuery($request), $filters)
            ->with([
                'thinkTank:id,name,role,country',
                'collection:id,form_id,reporting_period_id,due_at,status',
                'collection.form:id,portfolio_id,code,title',
                'collection.form.portfolio:id,name',
                'collection.reportingPeriod:id,label,reporting_year,submission_deadline,review_deadline',
                'submission:id,assignment_id,status,workflow_status,submitted_at,approved_at,updated_at',
                'submission.dataQualityFindings:id,submission_id,severity,status,created_at',
                'performanceReports:id,assignment_id,status,submitted_at,approved_at,archived_at,updated_at',
            ])
            ->get();

        $periods = $this->applyPeriodFilters($this->scopedPeriodQuery($request), $filters)
            ->with('portfolio:id,name')
            ->orderByDesc('period_start')
            ->get();

        $submissions = $assignments->pluck('submission')->filter()->values();
        $findings = $submissions
            ->flatMap(fn (MeDataSubmission $submission): Collection => $submission->dataQualityFindings)
            ->values();
        $openFindings = $findings->where('status', 'open')->values();

        $officialReports = $reports->filter(fn (MePerformanceReport $report): bool => $report->isApproved())->values();
        $officialResults = $officialReports
            ->flatMap(fn (MePerformanceReport $report): Collection => $report->indicatorResults)
            ->values();

        $submittedAssignments = $assignments->filter(
            fn (MeDataCollectionAssignment $assignment): bool => $this->assignmentHasBeenSubmitted($assignment)
        );
        $approvedAssignments = $assignments->filter(
            fn (MeDataCollectionAssignment $assignment): bool => $this->assignmentHasBeenApproved($assignment)
        );
        $awaitingDecision = $reports->filter(fn (MePerformanceReport $report): bool => in_array(
            $report->status,
            [MePerformanceReport::STATUS_SUBMITTED, MePerformanceReport::STATUS_REVIEWED, MePerformanceReport::STATUS_VERIFIED],
            true
        ));
        $attentionReports = $officialReports->filter(fn (MePerformanceReport $report): bool => in_array(
            $report->performance_rating,
            ['at_risk', 'off_track'],
            true
        ));
        $evidencedOfficialReports = $officialReports->filter(
            fn (MePerformanceReport $report): bool => $report->documents->isNotEmpty()
        );
        $achievementValues = $officialResults
            ->pluck('target_achievement_percent')
            ->filter(fn ($value): bool => $value !== null)
            ->map(fn ($value): float => (float) $value);

        $metrics = [
            'official_reports' => $officialReports->count(),
            'official_rate' => $this->percentage($officialReports->count(), $reports->count()),
            'average_achievement' => $achievementValues->isEmpty() ? null : round($achievementValues->average(), 1),
            'reporting_coverage' => $this->percentage($submittedAssignments->count(), $assignments->count()),
            'submitted_assignments' => $submittedAssignments->count(),
            'total_assignments' => $assignments->count(),
            'awaiting_decision' => $awaitingDecision->count(),
            'open_errors' => $openFindings->where('severity', 'error')->count(),
            'open_warnings' => $openFindings->where('severity', 'warning')->count(),
            'evidence_coverage' => $this->percentage($evidencedOfficialReports->count(), $officialReports->count()),
            'evidenced_official_reports' => $evidencedOfficialReports->count(),
            'attention_reports' => $attentionReports->count(),
            'approved_assignments' => $approvedAssignments->count(),
            'submission_approval_rate' => $this->percentage($approvedAssignments->count(), $submittedAssignments->count()),
            'reported_indicators' => $officialResults->filter(
                fn (MePerformanceReportIndicatorResult $result): bool => $this->resultIsComplete($result)
            )->count(),
            'official_indicator_results' => $officialResults->count(),
        ];

        $lifecycle = collect(self::REPORT_STAGES)->map(function (array $configuration, string $key) use ($reports): array {
            $count = $reports->filter(fn (MePerformanceReport $report): bool => $this->reportStage($report) === $key)->count();

            return $configuration + ['key' => $key, 'count' => $count];
        })->values();

        $performance = collect(self::RATING_CONFIGURATION)->map(function (array $configuration, string $key) use ($officialReports): array {
            $count = $key === 'not_rated'
                ? $officialReports->filter(fn (MePerformanceReport $report): bool => blank($report->performance_rating) || $report->performance_rating === 'not_rated')->count()
                : $officialReports->where('performance_rating', $key)->count();

            return $configuration + ['key' => $key, 'count' => $count];
        })->values();

        $dataQuality = collect([
            ['key' => 'errors', 'label' => 'Open errors', 'count' => $metrics['open_errors'], 'color' => '#ae3f3d'],
            ['key' => 'warnings', 'label' => 'Open warnings', 'count' => $metrics['open_warnings'], 'color' => '#a56a17'],
            ['key' => 'resolved', 'label' => 'Resolved findings', 'count' => $findings->where('status', 'resolved')->count(), 'color' => '#187459'],
        ]);
        $findingAging = [
            'new' => $openFindings->where('created_at', '>=', now()->subDays(2))->count(),
            'attention' => $openFindings->where('created_at', '>=', now()->subDays(7))->where('created_at', '<', now()->subDays(2))->count(),
            'overdue' => $openFindings->where('created_at', '<', now()->subDays(7))->count(),
        ];

        $organizationRows = $this->organizationRows($assignments);
        $portfolioRows = $this->portfolioRows($portfolios, $reports, $assignments, $officialReports);
        $periodHealth = $this->periodHealth($periods, $assignments);
        $recentDecisions = $officialReports
            ->sortByDesc(fn (MePerformanceReport $report): int => (int) ($report->approved_at?->timestamp ?? $report->archived_at?->timestamp ?? 0))
            ->take(6)
            ->values();

        return view('me.management-dashboard.index', [
            'filters' => $filters,
            'filterOptions' => [
                'years' => $this->yearOptions($request),
                'portfolios' => $portfolios,
                'periods' => $periodOptions,
                'thinkTanks' => $thinkTanks,
            ],
            'metrics' => $metrics,
            'lifecycle' => $lifecycle,
            'performance' => $performance,
            'dataQuality' => $dataQuality,
            'findingAging' => $findingAging,
            'organizationRows' => $organizationRows,
            'portfolioRows' => $portfolioRows,
            'periodHealth' => $periodHealth,
            'recentDecisions' => $recentDecisions,
            'reportCount' => $reports->count(),
            'submissionCount' => $submissions->count(),
            'generatedAt' => now(),
            'isPortfolioScoped' => $this->userHasAssignedPortfolioScope($request->user()),
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

    private function scopedAssignmentQuery(Request $request): Builder
    {
        $query = MeDataCollectionAssignment::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $query->whereHas('collection.form', fn (Builder $form): Builder => $form
                ->whereIn('portfolio_id', $this->assignedPortfolioIds($request->user())));
        }

        return $query;
    }

    private function scopedPeriodQuery(Request $request): Builder
    {
        $query = MeReportingPeriod::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query, $request->user());
        }

        return $query;
    }

    private function applyReportFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['reporting_year'], fn (Builder $builder): Builder => $builder->where('reporting_year', $filters['reporting_year']))
            ->when($filters['portfolio_id'], fn (Builder $builder): Builder => $builder->where('portfolio_id', $filters['portfolio_id']))
            ->when($filters['reporting_period_id'], fn (Builder $builder): Builder => $builder->where('reporting_period_id', $filters['reporting_period_id']))
            ->when($filters['think_tank_id'], fn (Builder $builder): Builder => $builder->where('think_tank_member_id', $filters['think_tank_id']));
    }

    private function applyAssignmentFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['reporting_year'], fn (Builder $builder): Builder => $builder->whereHas(
                'collection.reportingPeriod',
                fn (Builder $period): Builder => $period->where('reporting_year', $filters['reporting_year'])
            ))
            ->when($filters['portfolio_id'], fn (Builder $builder): Builder => $builder->whereHas(
                'collection.form',
                fn (Builder $form): Builder => $form->where('portfolio_id', $filters['portfolio_id'])
            ))
            ->when($filters['reporting_period_id'], fn (Builder $builder): Builder => $builder->whereHas(
                'collection',
                fn (Builder $collection): Builder => $collection->where('reporting_period_id', $filters['reporting_period_id'])
            ))
            ->when($filters['think_tank_id'], fn (Builder $builder): Builder => $builder->where('think_tank_member_id', $filters['think_tank_id']));
    }

    private function applyPeriodFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['reporting_year'], fn (Builder $builder): Builder => $builder->where('reporting_year', $filters['reporting_year']))
            ->when($filters['portfolio_id'], fn (Builder $builder): Builder => $builder->where('portfolio_id', $filters['portfolio_id']))
            ->when($filters['reporting_period_id'], fn (Builder $builder): Builder => $builder->whereKey($filters['reporting_period_id']));
    }

    private function authorizedPortfolios(Request $request): Collection
    {
        $query = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToSectors($query, $request->user());
        }

        return $query->get(['id', 'name']);
    }

    private function periodOptions(Request $request, ?int $year = null): Collection
    {
        return $this->scopedPeriodQuery($request)
            ->when($year, fn (Builder $query): Builder => $query->where('reporting_year', $year))
            ->orderByDesc('period_start')
            ->get(['id', 'portfolio_id', 'label', 'reporting_year', 'period_start']);
    }

    private function thinkTankOptions(Request $request): Collection
    {
        $reportIds = $this->scopedReportQuery($request)
            ->whereNotNull('think_tank_member_id')
            ->distinct()
            ->pluck('think_tank_member_id');
        $assignmentIds = $this->scopedAssignmentQuery($request)
            ->whereNotNull('think_tank_member_id')
            ->distinct()
            ->pluck('think_tank_member_id');

        return ConsortiumThinkTank::query()
            ->whereIn('id', $reportIds->merge($assignmentIds)->unique()->values())
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'country']);
    }

    private function yearOptions(Request $request): Collection
    {
        return $this->scopedReportQuery($request)->whereNotNull('reporting_year')->distinct()->pluck('reporting_year')
            ->merge($this->scopedPeriodQuery($request)->whereNotNull('reporting_year')->distinct()->pluck('reporting_year'))
            ->map(fn ($year): int => (int) $year)
            ->unique()
            ->sortDesc()
            ->values();
    }

    private function organizationRows(Collection $assignments): Collection
    {
        return $assignments
            ->groupBy(fn (MeDataCollectionAssignment $assignment): string => (string) ($assignment->think_tank_member_id ?: 'unassigned'))
            ->map(function (Collection $items): array {
                $organization = $items->first()->thinkTank;
                $submitted = $items->filter(fn (MeDataCollectionAssignment $assignment): bool => $this->assignmentHasBeenSubmitted($assignment));
                $approved = $items->filter(fn (MeDataCollectionAssignment $assignment): bool => $this->assignmentHasBeenApproved($assignment));
                $overdue = $items->filter(fn (MeDataCollectionAssignment $assignment): bool => $assignment->collection?->due_at?->isPast()
                    && ! $this->assignmentHasBeenSubmitted($assignment));
                $openErrors = $items->sum(fn (MeDataCollectionAssignment $assignment): int => $assignment->submission
                    ? $assignment->submission->dataQualityFindings->where('status', 'open')->where('severity', 'error')->count()
                    : 0);

                return [
                    'id' => $organization?->id,
                    'name' => $organization?->name ?: 'Unassigned organization',
                    'country' => $organization?->country,
                    'expected' => $items->count(),
                    'submitted' => $submitted->count(),
                    'approved' => $approved->count(),
                    'overdue' => $overdue->count(),
                    'open_errors' => $openErrors,
                    'coverage' => $this->percentage($submitted->count(), $items->count()),
                    'approval_rate' => $this->percentage($approved->count(), $submitted->count()),
                    'last_submission_at' => $items
                        ->flatMap(fn (MeDataCollectionAssignment $assignment): array => $this->assignmentSubmissionDates($assignment))
                        ->filter()
                        ->sortDesc()
                        ->first(),
                ];
            })
            ->sortBy([['overdue', 'desc'], ['open_errors', 'desc'], ['coverage', 'asc'], ['name', 'asc']])
            ->values();
    }

    private function portfolioRows(Collection $portfolios, Collection $reports, Collection $assignments, Collection $officialReports): Collection
    {
        return $portfolios->map(function (Sector $portfolio) use ($reports, $assignments, $officialReports): array {
            $portfolioReports = $reports->where('portfolio_id', $portfolio->id);
            $portfolioOfficial = $officialReports->where('portfolio_id', $portfolio->id);
            $portfolioAssignments = $assignments->filter(fn (MeDataCollectionAssignment $assignment): bool => (string) $assignment->collection?->form?->portfolio_id === (string) $portfolio->id);
            $submitted = $portfolioAssignments->filter(fn (MeDataCollectionAssignment $assignment): bool => $this->assignmentHasBeenSubmitted($assignment));
            $evidenced = $portfolioOfficial->filter(fn (MePerformanceReport $report): bool => $report->documents->isNotEmpty());
            $attention = $portfolioOfficial->whereIn('performance_rating', ['at_risk', 'off_track']);

            return [
                'id' => $portfolio->id,
                'name' => $portfolio->name,
                'reports' => $portfolioReports->count(),
                'official' => $portfolioOfficial->count(),
                'official_rate' => $this->percentage($portfolioOfficial->count(), $portfolioReports->count()),
                'coverage' => $this->percentage($submitted->count(), $portfolioAssignments->count()),
                'evidence_coverage' => $this->percentage($evidenced->count(), $portfolioOfficial->count()),
                'attention' => $attention->count(),
                'has_activity' => $portfolioReports->isNotEmpty() || $portfolioAssignments->isNotEmpty(),
            ];
        })->filter(fn (array $row): bool => $row['has_activity'])->sortByDesc('reports')->values();
    }

    private function periodHealth(Collection $periods, Collection $assignments): Collection
    {
        return $periods->map(function (MeReportingPeriod $period) use ($assignments): array {
            $periodAssignments = $assignments->filter(fn (MeDataCollectionAssignment $assignment): bool => (string) $assignment->collection?->reporting_period_id === (string) $period->id);
            $submitted = $periodAssignments->filter(fn (MeDataCollectionAssignment $assignment): bool => $this->assignmentHasBeenSubmitted($assignment));
            $status = $period->isOpenForSubmission()
                ? 'open'
                : (($period->submission_deadline && $period->submission_deadline->isPast() && ! $period->isClosed()) ? 'deadline_passed' : ($period->isClosed() ? 'closed' : 'planned'));

            return [
                'id' => $period->id,
                'label' => $period->label,
                'portfolio' => $period->portfolio?->name ?: 'Cross-portfolio',
                'reporting_year' => $period->reporting_year,
                'status' => $status,
                'status_label' => match ($status) {
                    'open' => 'Open for submission',
                    'deadline_passed' => 'Deadline passed',
                    'closed' => 'Closed',
                    default => 'Planned',
                },
                'deadline' => $period->submission_deadline,
                'expected' => $periodAssignments->count(),
                'submitted' => $submitted->count(),
                'coverage' => $this->percentage($submitted->count(), $periodAssignments->count()),
            ];
        })->sortBy(function (array $period): string {
            $priority = ['deadline_passed' => '0', 'open' => '1', 'planned' => '2', 'closed' => '3'];

            return ($priority[$period['status']] ?? '4').'|'.($period['deadline']?->format('YmdHis') ?? '99999999999999');
        })->take(8)->values();
    }

    private function submissionHasBeenSent(?MeDataSubmission $submission): bool
    {
        return $submission !== null
            && ($submission->submitted_at !== null || ! in_array($submission->effectiveStatus(), [MeDataSubmission::STATUS_DRAFT, MeDataSubmission::STATUS_RETURNED], true));
    }

    private function assignmentHasBeenSubmitted(MeDataCollectionAssignment $assignment): bool
    {
        return $this->submissionHasBeenSent($assignment->submission)
            || $assignment->performanceReports->contains(
                fn (MePerformanceReport $report): bool => $report->submitted_at !== null
                    || ! in_array($report->status, [MePerformanceReport::STATUS_DRAFT], true)
            );
    }

    private function assignmentHasBeenApproved(MeDataCollectionAssignment $assignment): bool
    {
        return $assignment->submission?->isApproved() === true
            || $assignment->performanceReports->contains(
                fn (MePerformanceReport $report): bool => $report->isApproved()
            );
    }

    private function assignmentSubmissionDates(MeDataCollectionAssignment $assignment): array
    {
        return [
            $assignment->submission?->submitted_at,
            ...$assignment->performanceReports->pluck('submitted_at')->all(),
        ];
    }

    private function resultIsComplete(MePerformanceReportIndicatorResult $result): bool
    {
        $hasActual = $result->indicator?->value_type === 'milestone'
            ? filled($result->actual_text)
            : $result->actual_value !== null;

        return $hasActual && filled($result->indicator_result_id);
    }

    private function reportStage(MePerformanceReport $report): string
    {
        return match ($report->status) {
            MePerformanceReport::STATUS_SUBMITTED => 'submitted',
            MePerformanceReport::STATUS_REVIEWED, MePerformanceReport::STATUS_VERIFIED => 'verified',
            MePerformanceReport::STATUS_APPROVED => 'approved',
            MePerformanceReport::STATUS_ARCHIVED => 'archived',
            default => 'draft',
        };
    }

    private function filters(Request $request): array
    {
        $year = filter_var($request->query('reporting_year'), FILTER_VALIDATE_INT);

        return [
            'reporting_year' => $year && $year >= 2000 && $year <= 2100 ? (int) $year : null,
            'portfolio_id' => $this->uuidOrNull($request->query('portfolio_id')),
            'reporting_period_id' => $this->uuidOrNull($request->query('reporting_period_id')),
            'think_tank_id' => $this->uuidOrNull($request->query('think_tank_id')),
        ];
    }

    private function assertAuthorizedFilter(?string $id, Collection $options, string $label): void
    {
        if ($id && ! $options->contains(fn ($option): bool => (string) $option->id === $id)) {
            abort(403, 'You do not have access to the selected '.$label.'.');
        }
    }

    private function uuidOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return Str::isUuid($value) ? $value : null;
    }

    private function percentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 1) : 0.0;
    }
}
