<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\ConsortiumThinkTank;
use App\Models\MeDataQualityFinding;
use App\Models\MeDataSubmission;
use App\Models\MeDataSubmissionReview;
use App\Models\MeReportingPeriod;
use App\Models\Sector;
use App\Services\MeDataQualityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeModuleController extends Controller
{
    use ScopesAssignedPortfolios;

    private const SECTIONS = [
        'results-framework' => [
            'title' => 'Results Framework and Indicator Management',
            'icon' => 'feather-target',
        ],
        'data-entry-performance-tracking' => [
            'title' => 'Data Entry and Performance Tracking',
            'icon' => 'feather-edit-3',
        ],
        'data-quality-approval-workflow' => [
            'title' => 'Data Quality and Approval Workflow',
            'icon' => 'feather-check-circle',
        ],
        'reporting-dashboard' => [
            'title' => 'Reporting and Dashboard',
            'icon' => 'feather-bar-chart-2',
        ],
        'management-dashboard' => [
            'title' => 'Management Dashboard',
            'icon' => 'feather-monitor',
        ],
        'knowledge-evidence-repository' => [
            'title' => 'Knowledge and Evidence Repository',
            'subtitle' => 'MEAL plans, TOCs and pertinent documents',
            'icon' => 'feather-folder',
        ],
        'data-governance-framework' => [
            'title' => 'Data Governance Framework',
            'icon' => 'feather-shield',
        ],
    ];

    private const FINDING_STATUSES = [
        'open' => 'Open',
        'resolved' => 'Resolved',
        'superseded' => 'Superseded by a later check',
    ];

    private const WORKFLOW_LABELS = [
        MeDataSubmission::STATUS_DRAFT => 'Draft',
        MeDataSubmission::STATUS_SUBMITTED => 'Awaiting review',
        MeDataSubmission::STATUS_RESUBMITTED => 'Resubmitted',
        MeDataSubmission::STATUS_UNDER_REVIEW => 'Under review',
        MeDataSubmission::STATUS_RETURNED => 'Returned',
        MeDataSubmission::STATUS_VALIDATED => 'Validated (legacy)',
        MeDataSubmission::STATUS_VERIFIED => 'Verified',
        MeDataSubmission::STATUS_APPROVED => 'Approved',
        MeDataSubmission::STATUS_REJECTED => 'Rejected',
    ];

    private const DQA_ELIGIBLE_STATUSES = [
        MeDataSubmission::STATUS_SUBMITTED,
        MeDataSubmission::STATUS_RESUBMITTED,
        MeDataSubmission::STATUS_UNDER_REVIEW,
        MeDataSubmission::STATUS_VALIDATED,
        MeDataSubmission::STATUS_VERIFIED,
    ];

    public function resultsFramework()
    {
        return redirect()->route('budget.me.indicators.index');
    }

    public function dataEntry()
    {
        return $this->show('data-entry-performance-tracking');
    }

    public function dataQuality(Request $request)
    {
        $validated = $request->validate([
            'tab' => ['nullable', Rule::in(['findings', 'pipeline', 'rules'])],
            'q' => ['nullable', 'string', 'max:150'],
            'portfolio_id' => ['nullable', 'uuid'],
            'think_tank_id' => ['nullable', 'uuid'],
            'reporting_period_id' => ['nullable', 'uuid'],
            'reporting_year' => ['nullable', 'integer', 'between:2000,2200'],
            'severity' => ['nullable', Rule::in(['error', 'warning'])],
            'finding_status' => ['nullable', Rule::in(array_keys(self::FINDING_STATUSES))],
            'workflow_status' => ['nullable', Rule::in(array_keys(self::WORKFLOW_LABELS))],
            'rule' => ['nullable', Rule::in(array_keys(MeDataQualityService::RULES))],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'severity', 'aging', 'dqa'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 20, 50, 100])],
        ]);
        $tab = (string) ($validated['tab'] ?? 'findings');
        $filters = [
            'tab' => $tab,
            'q' => trim((string) ($validated['q'] ?? '')),
            'portfolio_id' => (string) ($validated['portfolio_id'] ?? ''),
            'think_tank_id' => (string) ($validated['think_tank_id'] ?? ''),
            'reporting_period_id' => (string) ($validated['reporting_period_id'] ?? ''),
            'reporting_year' => (string) ($validated['reporting_year'] ?? ''),
            'severity' => (string) ($validated['severity'] ?? ''),
            'finding_status' => (string) ($validated['finding_status'] ?? ($tab === 'findings' ? 'open' : '')),
            'workflow_status' => (string) ($validated['workflow_status'] ?? ''),
            'rule' => (string) ($validated['rule'] ?? ''),
            'sort' => (string) ($validated['sort'] ?? 'severity'),
            'per_page' => (int) ($validated['per_page'] ?? 20),
        ];

        $portfolioQuery = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToSectors($portfolioQuery, $request->user());
        }
        $portfolios = $portfolioQuery->get(['id', 'name']);
        if ($filters['portfolio_id'] !== '' && ! $portfolios->contains(
            fn (Sector $portfolio): bool => (string) $portfolio->id === $filters['portfolio_id']
        )) {
            abort(403, 'You do not have access to the selected portfolio.');
        }

        $contextSubmissions = $this->scopedSubmissions($request);
        $this->applySubmissionContextFilters($contextSubmissions, $filters);
        $contextFindings = $this->scopedFindings($request);
        $this->applyFindingContextFilters($contextFindings, $filters);

        $findingsQuery = clone $contextFindings;
        $this->applyFindingListFilters($findingsQuery, $filters);
        $findingsQuery->with([
            'submission.assignment.thinkTank:id,name,country',
            'submission.assignment.collection.form:id,portfolio_id,project_component_id,code,title',
            'submission.assignment.collection.form.portfolio:id,name',
            'submission.assignment.collection.reportingPeriod:id,label,reporting_year',
            'submission.submittedBy:id,name,email',
            'indicatorResult.indicator:id,indicator_code,name',
            'resolvedBy:id,name,email',
        ]);
        match ($filters['sort']) {
            'oldest' => $findingsQuery->oldest(),
            'aging' => $findingsQuery->orderByRaw("CASE status WHEN 'open' THEN 0 ELSE 1 END")->oldest(),
            'newest' => $findingsQuery->latest(),
            default => $findingsQuery
                ->orderByRaw("CASE severity WHEN 'error' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
                ->orderByRaw("CASE status WHEN 'open' THEN 0 ELSE 1 END")
                ->oldest(),
        };

        $pipelineQuery = clone $contextSubmissions;
        $this->applySubmissionListFilters($pipelineQuery, $filters);
        $pipelineQuery
            ->with([
                'assignment.thinkTank:id,name,country',
                'assignment.collection.form:id,portfolio_id,project_component_id,code,title',
                'assignment.collection.form.portfolio:id,name',
                'assignment.collection.reportingPeriod:id,label,reporting_year,review_deadline',
                'submittedBy:id,name,email',
                'reviewedBy:id,name,email',
            ])
            ->withCount([
                'indicatorResults',
                'evidence',
                'dataQualityFindings as open_dqa_count' => fn ($query) => $query->where('status', 'open'),
                'dataQualityFindings as blocking_dqa_count' => fn ($query) => $query->where('status', 'open')->where('severity', 'error'),
                'dataQualityFindings as warning_dqa_count' => fn ($query) => $query->where('status', 'open')->where('severity', 'warning'),
            ])
            ->withMax(['reviews as last_dqa_at' => fn ($query) => $query->where('action', 'dqa_evaluated')], 'reviewed_at');
        match ($filters['sort']) {
            'oldest' => $pipelineQuery->orderByRaw('CASE WHEN submitted_at IS NULL THEN 1 ELSE 0 END')->oldest('submitted_at'),
            'dqa', 'severity' => $pipelineQuery->orderByDesc('blocking_dqa_count')->orderByDesc('warning_dqa_count')->latest('submitted_at'),
            default => $pipelineQuery->orderByRaw('CASE WHEN submitted_at IS NULL THEN 1 ELSE 0 END')->latest('submitted_at'),
        };

        $metricSubmissions = clone $contextSubmissions;
        $submittedScope = (clone $metricSubmissions)->whereNotNull('submitted_at');
        $metrics = [
            'open_errors' => (clone $contextFindings)->where('status', 'open')->where('severity', 'error')->count(),
            'open_warnings' => (clone $contextFindings)->where('status', 'open')->where('severity', 'warning')->count(),
            'affected_submissions' => (clone $metricSubmissions)
                ->whereHas('dataQualityFindings', fn (Builder $query) => $query->where('status', 'open'))
                ->count(),
            'ready_for_review' => (clone $metricSubmissions)
                ->where($this->effectiveStatusClosure([MeDataSubmission::STATUS_SUBMITTED, MeDataSubmission::STATUS_RESUBMITTED]))
                ->whereDoesntHave('dataQualityFindings', fn (Builder $query) => $query->where('status', 'open')->where('severity', 'error'))
                ->count(),
            'awaiting_approval' => (clone $metricSubmissions)
                ->where($this->effectiveStatusClosure([MeDataSubmission::STATUS_VALIDATED, MeDataSubmission::STATUS_VERIFIED]))
                ->count(),
            'approved' => (clone $metricSubmissions)
                ->where($this->effectiveStatusClosure([MeDataSubmission::STATUS_APPROVED]))
                ->count(),
            'evaluated' => (clone $submittedScope)->whereHas('reviews', fn (Builder $query) => $query->where('action', 'dqa_evaluated'))->count(),
            'submitted' => (clone $submittedScope)->count(),
        ];
        $metrics['coverage'] = $metrics['submitted'] > 0
            ? round(($metrics['evaluated'] / $metrics['submitted']) * 100, 1)
            : 0.0;

        $aging = [
            'new' => (clone $contextFindings)->where('status', 'open')->where('created_at', '>=', now()->subDays(2))->count(),
            'attention' => (clone $contextFindings)->where('status', 'open')->where('created_at', '>=', now()->subDays(7))->where('created_at', '<', now()->subDays(2))->count(),
            'overdue' => (clone $contextFindings)->where('status', 'open')->where('created_at', '<', now()->subDays(7))->count(),
        ];
        $ruleSummary = (clone $contextFindings)
            ->where('status', 'open')
            ->selectRaw('rule_code, severity, COUNT(*) AS total')
            ->groupBy('rule_code', 'severity')
            ->orderByDesc('total')
            ->get();
        $lastEvaluationAt = MeDataSubmissionReview::query()
            ->where('action', 'dqa_evaluated')
            ->whereHas('submission', function (Builder $query) use ($request, $filters): void {
                if ($this->userHasAssignedPortfolioScope($request->user())) {
                    $query->whereHas('assignment.collection.form', fn (Builder $form) => $form
                        ->whereIn('portfolio_id', $this->assignedPortfolioIds($request->user())));
                }
                $this->applySubmissionContextFilters($query, $filters);
            })
            ->max('reviewed_at');

        $assignedPortfolioIds = collect($this->userHasAssignedPortfolioScope($request->user())
            ? $this->assignedPortfolioIds($request->user())
            : []);
        $thinkTankQuery = ConsortiumThinkTank::query()
            ->whereHas('dataCollectionAssignments.submission')
            ->when($assignedPortfolioIds->isNotEmpty(), fn (Builder $query) => $query->whereHas(
                'dataCollectionAssignments.collection.form',
                fn (Builder $form) => $form->whereIn('portfolio_id', $assignedPortfolioIds)
            ));
        $periodQuery = MeReportingPeriod::query()
            ->whereHas('collections.assignments.submission')
            ->when($assignedPortfolioIds->isNotEmpty(), fn (Builder $query) => $query->whereIn('portfolio_id', $assignedPortfolioIds));

        $canManageDqa = collect(['me.dqa.manage', 'me.submissions.review', 'me.data_entry.manage', 'me.configuration.manage'])
            ->contains(fn (string $permission): bool => $request->user()->hasPermission($permission));
        $canOpenReviewQueue = collect(['me.submissions.review', 'me.data_entry.manage', 'me.configuration.manage'])
            ->contains(fn (string $permission): bool => $request->user()->hasPermission($permission));

        return view('me.data-quality.index', [
            'findings' => $findingsQuery->paginate($filters['per_page'], ['*'], 'findings_page')->withQueryString(),
            'pipeline' => $pipelineQuery->paginate($filters['per_page'], ['*'], 'pipeline_page')->withQueryString(),
            'filters' => $filters,
            'metrics' => $metrics,
            'aging' => $aging,
            'ruleSummary' => $ruleSummary,
            'ruleCatalogue' => MeDataQualityService::RULES,
            'findingStatuses' => self::FINDING_STATUSES,
            'workflowLabels' => self::WORKFLOW_LABELS,
            'eligibleStatuses' => self::DQA_ELIGIBLE_STATUSES,
            'portfolios' => $portfolios,
            'thinkTanks' => $thinkTankQuery->orderBy('name')->get(['id', 'name']),
            'periods' => $periodQuery->orderByDesc('period_start')->get(['id', 'label', 'reporting_year']),
            'lastEvaluationAt' => $lastEvaluationAt,
            'canManageDqa' => $canManageDqa,
            'canOpenReviewQueue' => $canOpenReviewQueue,
            'canViewNotifications' => $request->user()->hasPermission('me.reporting_notifications.view'),
            'activeFilterCount' => collect([
                $filters['q'], $filters['portfolio_id'], $filters['think_tank_id'],
                $filters['reporting_period_id'], $filters['reporting_year'], $filters['severity'],
                $filters['workflow_status'], $filters['rule'],
            ])->filter(fn ($value) => filled($value))->count()
                + (filled($filters['finding_status']) && $filters['finding_status'] !== 'open' ? 1 : 0),
        ]);
    }

    public function evaluateSubmission(Request $request, MeDataSubmission $submission): RedirectResponse
    {
        $this->assertSubmissionInScope($request, $submission);
        $count = DB::transaction(function () use ($submission): int {
            $locked = MeDataSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            $this->assertDqaEligible($locked);

            return app(MeDataQualityService::class)->evaluate($locked)->count();
        });

        return back()->with('success', 'Data-quality checks completed. '.$count.' current '.str('finding')->plural($count).' recorded and the run was added to the audit history.');
    }

    public function evaluateSelected(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'submission_ids' => ['required', 'array', 'min:1', 'max:50'],
            'submission_ids.*' => ['required', 'uuid', 'distinct'],
        ]);
        $submissions = $this->scopedSubmissions($request)
            ->whereIn('id', $validated['submission_ids'])
            ->get();
        abort_if($submissions->count() !== count($validated['submission_ids']), 404);
        $ineligible = $submissions->reject(fn (MeDataSubmission $submission): bool => in_array(
            $submission->effectiveStatus(), self::DQA_ELIGIBLE_STATUSES, true
        ));
        if ($ineligible->isNotEmpty()) {
            throw ValidationException::withMessages([
                'submission_ids' => $ineligible->count().' selected '.str('submission')->plural($ineligible->count()).' cannot be evaluated at the current workflow stage.',
            ]);
        }

        $findingCount = 0;
        foreach ($submissions->sortBy('id') as $submission) {
            $findingCount += DB::transaction(function () use ($submission): int {
                $locked = MeDataSubmission::query()->lockForUpdate()->findOrFail($submission->id);
                $this->assertDqaEligible($locked);

                return app(MeDataQualityService::class)->evaluate($locked)->count();
            });
        }

        return back()->with('success', $submissions->count().' '.str('submission')->plural($submissions->count()).' evaluated with '.$findingCount.' current '.str('finding')->plural($findingCount).'.');
    }

    public function reportingDashboard()
    {
        return $this->show('reporting-dashboard');
    }

    public function managementDashboard()
    {
        return $this->show('management-dashboard');
    }

    public function knowledgeRepository()
    {
        return $this->show('knowledge-evidence-repository');
    }

    public function dataGovernance()
    {
        return $this->show('data-governance-framework');
    }

    private function scopedSubmissions(Request $request): Builder
    {
        $query = MeDataSubmission::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $query->whereHas('assignment.collection.form', fn (Builder $form) => $form
                ->whereIn('portfolio_id', $this->assignedPortfolioIds($request->user())));
        }

        return $query;
    }

    private function scopedFindings(Request $request): Builder
    {
        $query = MeDataQualityFinding::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $query->whereHas('submission.assignment.collection.form', fn (Builder $form) => $form
                ->whereIn('portfolio_id', $this->assignedPortfolioIds($request->user())));
        }

        return $query;
    }

    private function applySubmissionContextFilters(Builder $query, array $filters): void
    {
        if ($filters['portfolio_id'] !== '') {
            $query->whereHas('assignment.collection.form', fn (Builder $form) => $form->where('portfolio_id', $filters['portfolio_id']));
        }
        if ($filters['think_tank_id'] !== '') {
            $query->whereHas('assignment', fn (Builder $assignment) => $assignment->where('think_tank_member_id', $filters['think_tank_id']));
        }
        if ($filters['reporting_period_id'] !== '') {
            $query->whereHas('assignment.collection', fn (Builder $collection) => $collection->where('reporting_period_id', $filters['reporting_period_id']));
        }
        if ($filters['reporting_year'] !== '') {
            $query->whereHas('assignment.collection.reportingPeriod', fn (Builder $period) => $period->where('reporting_year', (int) $filters['reporting_year']));
        }
    }

    private function applyFindingContextFilters(Builder $query, array $filters): void
    {
        $query->whereHas('submission', fn (Builder $submission) => $this->applySubmissionContextFilters($submission, $filters));
    }

    private function applyFindingListFilters(Builder $query, array $filters): void
    {
        if ($filters['finding_status'] !== '') {
            $query->where('status', $filters['finding_status']);
        }
        if ($filters['severity'] !== '') {
            $query->where('severity', $filters['severity']);
        }
        if ($filters['rule'] !== '') {
            $query->where('rule_code', $filters['rule']);
        }
        if ($filters['workflow_status'] !== '') {
            $query->whereHas('submission', fn (Builder $submission) => $submission
                ->where($this->effectiveStatusClosure([$filters['workflow_status']])));
        }
        if ($filters['q'] !== '') {
            $like = '%'.addcslashes(strtolower($filters['q']), '%_\\').'%';
            $query->where(function (Builder $search) use ($like): void {
                $search->whereRaw('LOWER(message) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(rule_code) LIKE ?', [$like])
                    ->orWhereRaw("LOWER(COALESCE(field_key, '')) LIKE ?", [$like])
                    ->orWhereHas('submission.assignment.thinkTank', fn (Builder $tank) => $tank->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('submission.assignment.collection.form', fn (Builder $form) => $form
                        ->whereRaw('LOWER(title) LIKE ?', [$like])->orWhereRaw('LOWER(code) LIKE ?', [$like]))
                    ->orWhereHas('indicatorResult.indicator', fn (Builder $indicator) => $indicator
                        ->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(indicator_code) LIKE ?', [$like]));
            });
        }
    }

    private function applySubmissionListFilters(Builder $query, array $filters): void
    {
        if ($filters['workflow_status'] !== '') {
            $query->where($this->effectiveStatusClosure([$filters['workflow_status']]));
        }
        if ($filters['severity'] !== '' || $filters['finding_status'] !== '' || $filters['rule'] !== '') {
            $query->whereHas('dataQualityFindings', function (Builder $finding) use ($filters): void {
                if ($filters['severity'] !== '') {
                    $finding->where('severity', $filters['severity']);
                }
                if ($filters['finding_status'] !== '') {
                    $finding->where('status', $filters['finding_status']);
                }
                if ($filters['rule'] !== '') {
                    $finding->where('rule_code', $filters['rule']);
                }
            });
        }
        if ($filters['q'] !== '') {
            $like = '%'.addcslashes(strtolower($filters['q']), '%_\\').'%';
            $query->where(function (Builder $search) use ($like): void {
                $search->whereRaw("LOWER(COALESCE(notes, '')) LIKE ?", [$like])
                    ->orWhereHas('assignment.thinkTank', fn (Builder $tank) => $tank->whereRaw('LOWER(name) LIKE ?', [$like]))
                    ->orWhereHas('assignment.collection.form', fn (Builder $form) => $form
                        ->whereRaw('LOWER(title) LIKE ?', [$like])->orWhereRaw('LOWER(code) LIKE ?', [$like]))
                    ->orWhereHas('dataQualityFindings', fn (Builder $finding) => $finding
                        ->whereRaw('LOWER(message) LIKE ?', [$like])->orWhereRaw('LOWER(rule_code) LIKE ?', [$like]));
            });
        }
    }

    private function effectiveStatusClosure(array $statuses): \Closure
    {
        return function (Builder $query) use ($statuses): void {
            $query->whereIn('workflow_status', $statuses)
                ->orWhere(function (Builder $legacy) use ($statuses): void {
                    $legacy->where(function (Builder $empty): void {
                        $empty->whereNull('workflow_status')->orWhere('workflow_status', '');
                    })->whereIn('status', $statuses);
                });
        };
    }

    private function assertSubmissionInScope(Request $request, MeDataSubmission $submission): void
    {
        abort_unless($this->scopedSubmissions($request)->whereKey($submission->id)->exists(), 404);
    }

    private function assertDqaEligible(MeDataSubmission $submission): void
    {
        if (! in_array($submission->effectiveStatus(), self::DQA_ELIGIBLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'submission' => 'Data-quality checks can only run after submission and before final approval, return or rejection.',
            ]);
        }
    }

    private function show(string $key)
    {
        $section = self::SECTIONS[$key];
        $sections = self::SECTIONS;

        return view('me.module.show', compact('key', 'section', 'sections'));
    }
}
