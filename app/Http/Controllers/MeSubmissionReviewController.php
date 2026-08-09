<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\IndicatorResult;
use App\Models\MeDataQualityFinding;
use App\Models\MeDataSubmission;
use App\Models\MeDataSubmissionReview;
use App\Models\MeReportingPeriod;
use App\Models\MeSubmissionEvidence;
use App\Models\Project;
use App\Models\Sector;
use App\Models\User;
use App\Services\MeDataQualityService;
use App\Services\MeReportingNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeSubmissionReviewController extends Controller
{
    use ScopesAssignedPortfolios;

    public const ACTIONS = [
        'start_review' => 'Start review',
        'verify' => 'Verify',
        'approve' => 'Approve',
        'return' => 'Return for correction',
        'reject' => 'Reject',
    ];

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.submissions.review|me.data_entry.manage|me.configuration.manage')
            ->except('resolveFinding');
        $this->middleware('permission:me.dqa.manage|me.submissions.review|me.data_entry.manage|me.configuration.manage')
            ->only('resolveFinding');
    }

    public function index(Request $request)
    {
        $status = trim((string) $request->query('status'));
        if (! in_array($status, $this->statuses(), true)) {
            $status = '';
        }
        $search = trim((string) $request->query('q'));
        $portfolioId = trim((string) $request->query('portfolio_id'));
        $dqaFilter = in_array($request->query('dqa'), ['open', 'blocking', 'clear'], true)
            ? (string) $request->query('dqa')
            : '';
        $evidenceFilter = in_array($request->query('evidence'), ['with', 'without'], true)
            ? (string) $request->query('evidence')
            : '';
        $sort = in_array($request->query('sort'), ['newest', 'oldest', 'dqa', 'recently_reviewed'], true)
            ? (string) $request->query('sort')
            : 'newest';
        $perPage = in_array((int) $request->query('per_page'), [10, 20, 50, 100], true)
            ? (int) $request->query('per_page')
            : 20;
        $effectiveStatusSql = "COALESCE(NULLIF(workflow_status, ''), status)";

        $portfolioQuery = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToSectors($portfolioQuery, $request->user());
        }
        $portfolios = $portfolioQuery->get(['id', 'name']);
        if ($portfolioId !== '' && ! $portfolios->contains(fn (Sector $portfolio): bool => (string) $portfolio->id === $portfolioId)) {
            abort(403, 'You do not have access to the selected portfolio.');
        }

        $query = $this->scopedQuery($request)
            ->with([
                'assignment.thinkTank:id,name,email,country',
                'assignment.collection.form:id,portfolio_id,project_component_id,code,title',
                'assignment.collection.form.portfolio:id,name',
                'assignment.collection.form.projectComponent:id,project_id,name',
                'assignment.collection.reportingPeriod:id,code,label,reporting_year,submission_deadline',
                'submittedBy:id,name,email',
                'reviewedBy:id,name,email',
            ])
            ->withCount([
                'answers',
                'indicatorResults',
                'evidence',
                'dataQualityFindings as open_dqa_count' => fn ($q) => $q->where('status', 'open'),
                'dataQualityFindings as blocking_dqa_count' => fn ($q) => $q->where('status', 'open')->where('severity', 'error'),
            ]);

        if ($status !== '') {
            $query->where(function (Builder $statusQuery) use ($status): void {
                $statusQuery->where('workflow_status', $status)
                    ->orWhere(function (Builder $legacyQuery) use ($status): void {
                        $legacyQuery->where(function (Builder $emptyWorkflow): void {
                            $emptyWorkflow->whereNull('workflow_status')->orWhere('workflow_status', '');
                        })->where('status', $status);
                    });
            });
        }
        if ($portfolioId !== '') {
            $query->whereHas('assignment.collection.form', fn (Builder $q) => $q->where('portfolio_id', $portfolioId));
        }
        if ($request->filled('think_tank_id')) {
            $query->whereHas('assignment', fn (Builder $q) => $q->where('think_tank_member_id', $request->query('think_tank_id')));
        }
        if ($request->filled('reporting_year')) {
            $query->whereHas('assignment.collection.reportingPeriod', fn (Builder $q) => $q->where('reporting_year', (int) $request->query('reporting_year')));
        }
        if ($request->filled('reporting_period_id')) {
            $query->whereHas('assignment.collection', fn (Builder $q) => $q->where('reporting_period_id', $request->query('reporting_period_id')));
        }
        if ($request->filled('component_id')) {
            $query->whereHas('assignment.collection.form', fn (Builder $q) => $q->where('project_component_id', $request->query('component_id')));
        }
        if ($request->filled('indicator_id')) {
            $query->whereHas('indicatorResults', fn (Builder $q) => $q->where('indicator_id', $request->query('indicator_id')));
        }
        if ($request->filled('country')) {
            $query->whereHas('assignment.thinkTank', fn (Builder $q) => $q->where('country', $request->query('country')));
        }
        if ($request->filled('reviewer_id')) {
            $query->where('reviewed_by', $request->query('reviewer_id'));
        }
        if ($search !== '') {
            $like = '%'.addcslashes(strtolower($search), '%_\\').'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->whereRaw("LOWER(COALESCE(notes, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(NULLIF(workflow_status, ''), status, '')) LIKE ?", [$like])
                    ->orWhereHas('submittedBy', fn (Builder $user) => $user
                        ->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like]))
                    ->orWhereHas('assignment.thinkTank', fn (Builder $tank) => $tank
                        ->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(country) LIKE ?', [$like]))
                    ->orWhereHas('assignment.collection.form', fn (Builder $form) => $form
                        ->whereRaw('LOWER(title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$like]))
                    ->orWhereHas('assignment.collection.reportingPeriod', fn (Builder $period) => $period
                        ->whereRaw('LOWER(label) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(code) LIKE ?', [$like]))
                    ->orWhereHas('indicatorResults.indicator', fn (Builder $indicator) => $indicator
                        ->whereRaw('LOWER(name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(indicator_code) LIKE ?', [$like]));
            });
        }

        if ($dqaFilter === 'open') {
            $query->whereHas('dataQualityFindings', fn (Builder $q) => $q->where('status', 'open'));
        } elseif ($dqaFilter === 'blocking') {
            $query->whereHas('dataQualityFindings', fn (Builder $q) => $q->where('status', 'open')->where('severity', 'error'));
        } elseif ($dqaFilter === 'clear') {
            $query->whereDoesntHave('dataQualityFindings', fn (Builder $q) => $q->where('status', 'open'));
        }

        if ($evidenceFilter === 'with') {
            $query->has('evidence');
        } elseif ($evidenceFilter === 'without') {
            $query->doesntHave('evidence');
        }

        match ($sort) {
            'oldest' => $query
                ->orderByRaw('CASE WHEN submitted_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('submitted_at')
                ->orderBy('updated_at'),
            'dqa' => $query
                ->orderByDesc('blocking_dqa_count')
                ->orderByDesc('open_dqa_count')
                ->orderByRaw('CASE WHEN submitted_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('submitted_at'),
            'recently_reviewed' => $query
                ->orderByRaw('CASE WHEN reviewed_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('reviewed_at')
                ->orderByDesc('updated_at'),
            default => $query
                ->orderByRaw('CASE WHEN submitted_at IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('submitted_at')
                ->orderByDesc('updated_at'),
        };

        $summary = $this->scopedQuery($request)
            ->selectRaw("{$effectiveStatusSql} AS effective_status, COUNT(*) AS total")
            ->groupByRaw($effectiveStatusSql)
            ->pluck('total', 'effective_status');

        $scopedReviewerIds = $this->scopedQuery($request)
            ->whereNotNull('reviewed_by')
            ->distinct()
            ->pluck('reviewed_by');
        $assignedPortfolioIds = collect(
            $this->userHasAssignedPortfolioScope($request->user())
                ? $this->assignedPortfolioIds($request->user())
                : []
        );

        $thinkTankQuery = ConsortiumThinkTank::query()
            ->whereHas('dataCollectionAssignments.submission')
            ->when($assignedPortfolioIds->isNotEmpty(), fn ($q) => $q->whereHas(
                'dataCollectionAssignments.collection.form',
                fn ($form) => $form->whereIn('portfolio_id', $assignedPortfolioIds)
            ));
        $periodQuery = MeReportingPeriod::query()
            ->whereHas('collections.assignments.submission')
            ->when($assignedPortfolioIds->isNotEmpty(), fn ($q) => $q->whereIn('portfolio_id', $assignedPortfolioIds));
        $componentQuery = Project::query()
            ->whereHas('dataEntryForms', fn ($forms) => $forms
                ->when($assignedPortfolioIds->isNotEmpty(), fn ($q) => $q->whereIn('portfolio_id', $assignedPortfolioIds))
                ->whereHas('collections.assignments.submission'));
        $indicatorQuery = Indicator::query()
            ->whereHas('results', fn ($results) => $results
                ->whereNotNull('data_submission_id')
                ->when($assignedPortfolioIds->isNotEmpty(), fn ($q) => $q->whereHas(
                    'dataSubmission.assignment.collection.form',
                    fn ($form) => $form->whereIn('portfolio_id', $assignedPortfolioIds)
                )));

        $activeFilterCount = collect([
            $search,
            $portfolioId,
            $request->query('think_tank_id'),
            $request->query('reporting_period_id'),
            $request->query('component_id'),
            $request->query('indicator_id'),
            $request->query('country'),
            $request->query('reviewer_id'),
            $request->query('reporting_year'),
            $dqaFilter,
            $evidenceFilter,
        ])->filter(fn ($value) => filled($value))->count();

        $submissions = $query->paginate($perPage)->withQueryString();
        $thinkTanks = (clone $thinkTankQuery)->orderBy('name')->get(['id', 'name']);
        $countries = (clone $thinkTankQuery)
            ->whereNotNull('country')
            ->where('country', '!=', '')
            ->select('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country');

        return view('me.submission-reviews.index', [
            'submissions' => $submissions,
            'summary' => $summary,
            'statuses' => $this->statuses(),
            'statusLabels' => $this->statusLabels(),
            'statusFilter' => $status,
            'search' => $search,
            'portfolioId' => $portfolioId,
            'dqaFilter' => $dqaFilter,
            'evidenceFilter' => $evidenceFilter,
            'sort' => $sort,
            'perPage' => $perPage,
            'activeFilterCount' => $activeFilterCount,
            'reviewMetrics' => [
                'awaiting' => (int) (($summary[MeDataSubmission::STATUS_SUBMITTED] ?? 0) + ($summary[MeDataSubmission::STATUS_RESUBMITTED] ?? 0)),
                'in_review' => (int) ($summary[MeDataSubmission::STATUS_UNDER_REVIEW] ?? 0),
                'blocking_dqa' => $this->scopedQuery($request)->whereHas(
                    'dataQualityFindings',
                    fn (Builder $q) => $q->where('status', 'open')->where('severity', 'error')
                )->count(),
                'approved' => (int) ($summary[MeDataSubmission::STATUS_APPROVED] ?? 0),
            ],
            'portfolios' => $portfolios,
            'thinkTanks' => $thinkTanks,
            'countries' => $countries,
            'periods' => $periodQuery->orderByDesc('period_start')->get(['id', 'label', 'reporting_year']),
            'components' => $componentQuery->orderBy('project_id')->get(['id', 'project_id', 'name']),
            'indicators' => $indicatorQuery->orderBy('display_order')->get(['id', 'indicator_code', 'name']),
            'reviewers' => User::query()->whereIn('id', $scopedReviewerIds)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, MeDataSubmission $submission)
    {
        $this->assertInScope($request, $submission);
        $submission->load([
            'assignment.thinkTank:id,name,country,email',
            'assignment.collection.form.fields.indicator.unit',
            'assignment.collection.form.fields.indicator.approvedReferenceSheet',
            'assignment.collection.form.indicators.unit',
            'assignment.collection.form.indicators.approvedReferenceSheet',
            'assignment.collection.reportingPeriod',
            'answers.field.indicator.approvedReferenceSheet',
            'indicatorResults.indicator.unit',
            'indicatorResults.indicator.approvedReferenceSheet',
            'indicatorResults.indicator.targets',
            'evidence.indicator',
            'versions',
            'reviews.reviewer:id,name,email',
            'dataQualityFindings.indicatorResult.indicator',
            'submittedBy:id,name,email',
        ]);
        $previousApproved = IndicatorResult::query()
            ->approved()
            ->whereIn('indicator_id', $submission->indicatorResults->pluck('indicator_id'))
            ->where('think_tank_member_id', $submission->assignment->think_tank_member_id)
            ->where('data_submission_id', '!=', $submission->id)
            ->with(['indicator:id,indicator_code,name', 'reportingPeriod:id,label'])
            ->latest('approved_at')
            ->get()
            ->groupBy('indicator_id');

        $effectiveStatus = $submission->effectiveStatus();
        $availableActions = $this->availableActions($effectiveStatus);
        $blockingDqaCount = $submission->dataQualityFindings
            ->where('status', 'open')
            ->where('severity', 'error')
            ->count();
        $canDecide = (string) $submission->submitted_by !== (string) $request->user()->id
            || $request->user()->isAdmin()
            || $request->user()->isSuperAdmin();

        return view('me.submission-reviews.show', compact(
            'submission',
            'previousApproved',
            'effectiveStatus',
            'availableActions',
            'blockingDqaCount',
            'canDecide'
        ) + ['statusLabels' => $this->statusLabels()]);
    }

    public function decide(Request $request, MeDataSubmission $submission)
    {
        $this->assertInScope($request, $submission);
        if ((string) $submission->submitted_by === (string) $request->user()->id
            && ! $request->user()->isAdmin() && ! $request->user()->isSuperAdmin()) {
            abort(403, 'A submitter cannot review their own M&E submission.');
        }
        $validated = $request->validate([
            'action' => ['required', Rule::in(array_keys(self::ACTIONS))],
            'comments' => ['nullable', 'string', 'max:5000'],
        ]);
        $action = $validated['action'];
        if (in_array($action, ['return', 'reject'], true) && blank($validated['comments'] ?? null)) {
            throw ValidationException::withMessages(['comments' => 'Reviewer comments are required for a return or rejection.']);
        }

        $decision = DB::transaction(function () use ($submission, $request, $validated, $action): array {
            /** @var MeDataSubmission $lockedSubmission */
            $lockedSubmission = MeDataSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            $from = $lockedSubmission->effectiveStatus();
            $to = $this->targetStatus($from, $action);
            app(MeDataQualityService::class)->evaluate($lockedSubmission);
            if ($action === 'approve' && $lockedSubmission->dataQualityFindings()
                ->where('status', 'open')->where('severity', 'error')->exists()) {
                throw ValidationException::withMessages([
                    'action' => 'Resolve all blocking data-quality findings before final approval.',
                ]);
            }
            $legacy = match ($to) {
                MeDataSubmission::STATUS_RETURNED, MeDataSubmission::STATUS_REJECTED => MeDataSubmission::STATUS_RETURNED,
                MeDataSubmission::STATUS_VERIFIED => MeDataSubmission::STATUS_VALIDATED,
                MeDataSubmission::STATUS_APPROVED => MeDataSubmission::STATUS_APPROVED,
                default => MeDataSubmission::STATUS_SUBMITTED,
            };
            $attributes = [
                'status' => $legacy,
                'workflow_status' => $to,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $validated['comments'] ?? null,
            ];
            if ($to === MeDataSubmission::STATUS_UNDER_REVIEW) {
                $attributes += ['under_review_by' => $request->user()->id, 'under_review_at' => now()];
            } elseif ($to === MeDataSubmission::STATUS_VERIFIED) {
                $attributes += ['verified_by' => $request->user()->id, 'verified_at' => now()];
            } elseif ($to === MeDataSubmission::STATUS_APPROVED) {
                $attributes += ['approved_by' => $request->user()->id, 'approved_at' => now()];
            } elseif ($to === MeDataSubmission::STATUS_REJECTED) {
                $attributes += ['rejected_by' => $request->user()->id, 'rejected_at' => now()];
            }
            if (in_array($to, [MeDataSubmission::STATUS_RETURNED, MeDataSubmission::STATUS_REJECTED], true)) {
                $attributes += [
                    'verified_by' => null,
                    'verified_at' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                ];
            }
            $lockedSubmission->update($attributes);

            $resultStatus = match ($to) {
                MeDataSubmission::STATUS_VERIFIED => 'validated',
                MeDataSubmission::STATUS_APPROVED => 'approved',
                MeDataSubmission::STATUS_RETURNED => 'returned',
                MeDataSubmission::STATUS_REJECTED => 'rejected',
                default => 'submitted',
            };
            $resultAttributes = [
                'review_status' => $resultStatus,
                'review_notes' => $validated['comments'] ?? null,
                'updated_by' => $request->user()->id,
            ];
            if ($to === MeDataSubmission::STATUS_VERIFIED) {
                $resultAttributes += ['validated_by' => $request->user()->id, 'validated_at' => now()];
            } elseif ($to === MeDataSubmission::STATUS_APPROVED) {
                $resultAttributes += ['approved_by' => $request->user()->id, 'approved_at' => now()];
            } elseif (in_array($to, [MeDataSubmission::STATUS_RETURNED, MeDataSubmission::STATUS_REJECTED], true)) {
                $resultAttributes += ['validated_by' => null, 'validated_at' => null, 'approved_by' => null, 'approved_at' => null];
            }
            $lockedSubmission->indicatorResults()->update($resultAttributes);

            if ($to === MeDataSubmission::STATUS_VERIFIED) {
                $lockedSubmission->evidence()->update([
                    'verification_status' => 'verified',
                    'verified_by' => $request->user()->id,
                    'verified_at' => now(),
                    'verification_notes' => $validated['comments'] ?? null,
                ]);
            } elseif (in_array($to, [MeDataSubmission::STATUS_RETURNED, MeDataSubmission::STATUS_REJECTED], true)) {
                $lockedSubmission->evidence()->update([
                    'verification_status' => 'pending',
                    'verified_by' => null,
                    'verified_at' => null,
                    'verification_notes' => null,
                ]);
            }
            MeDataSubmissionReview::query()->create([
                'submission_id' => $lockedSubmission->id,
                'submission_version' => (int) $lockedSubmission->current_version,
                'from_status' => $from,
                'to_status' => $to,
                'action' => $action,
                'comments' => $validated['comments'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            return ['submission' => $lockedSubmission->fresh(), 'to' => $to];
        });

        $submission = $decision['submission'];
        $to = $decision['to'];
        app(MeReportingNotificationService::class)->submissionLifecycle($submission, $to);

        return back()->with('success', self::ACTIONS[$action].' completed. The immutable review history has been updated.');
    }

    public function downloadEvidence(Request $request, MeDataSubmission $submission, MeSubmissionEvidence $evidence)
    {
        $this->assertInScope($request, $submission);
        abort_unless((string) $evidence->submission_id === (string) $submission->id, 404);
        $path = str_replace('\\', '/', (string) $evidence->file_path);
        abort_unless(str_starts_with($path, 'me-data/submissions/'.$submission->id.'/') && ! str_contains($path, '..'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $evidence->original_name ?: 'evidence');
    }

    public function resolveFinding(
        Request $request,
        MeDataSubmission $submission,
        MeDataQualityFinding $finding
    ) {
        $this->assertInScope($request, $submission);
        if ((string) $submission->submitted_by === (string) $request->user()->id
            && ! $request->user()->isAdmin() && ! $request->user()->isSuperAdmin()) {
            abort(403, 'A submitter cannot resolve findings on their own M&E submission.');
        }
        abort_unless((string) $finding->submission_id === (string) $submission->id, 404);
        $validated = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:5000'],
        ]);
        DB::transaction(function () use ($finding, $validated, $request): void {
            /** @var MeDataQualityFinding $lockedFinding */
            $lockedFinding = MeDataQualityFinding::query()->lockForUpdate()->findOrFail($finding->id);
            if ($lockedFinding->status !== 'open') {
                throw ValidationException::withMessages([
                    'resolution_notes' => 'This data-quality finding has already been resolved.',
                ]);
            }
            $lockedFinding->update([
                'status' => 'resolved',
                'resolution_notes' => $validated['resolution_notes'],
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
            ]);
        });

        return back()->with('success', 'The data-quality finding was resolved and retained in the audit history.');
    }

    private function targetStatus(string $from, string $action): string
    {
        $allowed = [
            'start_review' => [[MeDataSubmission::STATUS_SUBMITTED, MeDataSubmission::STATUS_RESUBMITTED], MeDataSubmission::STATUS_UNDER_REVIEW],
            'verify' => [[MeDataSubmission::STATUS_UNDER_REVIEW], MeDataSubmission::STATUS_VERIFIED],
            'approve' => [[MeDataSubmission::STATUS_VALIDATED, MeDataSubmission::STATUS_VERIFIED], MeDataSubmission::STATUS_APPROVED],
            'return' => [[MeDataSubmission::STATUS_SUBMITTED, MeDataSubmission::STATUS_RESUBMITTED, MeDataSubmission::STATUS_UNDER_REVIEW, MeDataSubmission::STATUS_VALIDATED, MeDataSubmission::STATUS_VERIFIED], MeDataSubmission::STATUS_RETURNED],
            'reject' => [[MeDataSubmission::STATUS_SUBMITTED, MeDataSubmission::STATUS_RESUBMITTED, MeDataSubmission::STATUS_UNDER_REVIEW, MeDataSubmission::STATUS_VALIDATED, MeDataSubmission::STATUS_VERIFIED], MeDataSubmission::STATUS_REJECTED],
        ];
        [$fromStatuses, $to] = $allowed[$action];
        if (! in_array($from, $fromStatuses, true)) {
            throw ValidationException::withMessages(['action' => 'That decision is not available at the current workflow stage.']);
        }

        return $to;
    }

    private function scopedQuery(Request $request): Builder
    {
        $query = MeDataSubmission::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $ids = $this->assignedPortfolioIds($request->user());
            $query->whereHas('assignment.collection.form', fn (Builder $form) => $form->whereIn('portfolio_id', $ids));
        }

        return $query;
    }

    private function assertInScope(Request $request, MeDataSubmission $submission): void
    {
        abort_unless($this->scopedQuery($request)->whereKey($submission->id)->exists(), 404);
    }

    /** @return array<string, string> */
    private function availableActions(string $status): array
    {
        return collect(self::ACTIONS)
            ->filter(function (string $label, string $action) use ($status): bool {
                try {
                    $this->targetStatus($status, $action);

                    return true;
                } catch (ValidationException) {
                    return false;
                }
            })
            ->all();
    }

    /** @return array<string, string> */
    private function statusLabels(): array
    {
        return [
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
    }

    /** @return array<int, string> */
    private function statuses(): array
    {
        return [
            MeDataSubmission::STATUS_DRAFT, MeDataSubmission::STATUS_SUBMITTED,
            MeDataSubmission::STATUS_RESUBMITTED, MeDataSubmission::STATUS_UNDER_REVIEW,
            MeDataSubmission::STATUS_RETURNED, MeDataSubmission::STATUS_VALIDATED,
            MeDataSubmission::STATUS_VERIFIED,
            MeDataSubmission::STATUS_APPROVED, MeDataSubmission::STATUS_REJECTED,
        ];
    }
}
