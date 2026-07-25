<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\IndicatorResult;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeDataEntryForm;
use App\Models\MePerformanceReport;
use App\Models\MePerformanceReportDocument;
use App\Models\MeReportingPeriod;
use App\Support\IndicatorReportingSchedule;
use App\Services\IndicatorAggregationService;
use App\Services\MeReportingNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MePerformanceReportController extends Controller
{
    use ScopesAssignedPortfolios;

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.performance_reports.view|me.performance_reports.review|me.performance_reports.archive|me.data_entry.view|me.data_entry.manage|me.configuration.view|me.configuration.manage')
            ->only(['create', 'edit', 'downloadDocument']);
        $this->middleware('permission:me.data_entry.manage|me.configuration.manage')
            ->only(['store', 'update', 'submit', 'destroyDocument']);
        $this->middleware('permission:me.performance_reports.review')->only('review');
        $this->middleware('permission:me.performance_reports.archive')->only('archive');
    }

    public function create(Request $request): View
    {
        $forms = $this->scopedForms($request)
            ->where('status', MeDataEntryForm::STATUS_PUBLISHED)
            ->whereNotNull('project_component_id')
            ->whereHas('indicators')
            ->with([
                'portfolio:id,name',
                'projectComponent:id,project_id,name,governance_node_id',
                'projectComponent.governanceNode:id,name,code',
                'indicators:id,indicator_code,name,frequency_of_reporting_id',
                'indicators.frequency:id,name,code,interval_unit,interval_value,frequency_in_days',
            ])
            ->orderBy('title')
            ->get();

        return view('me.performance-reports.create', [
            'forms' => $forms,
            'quarters' => MePerformanceReport::QUARTERS,
            'defaultYear' => (int) now()->year,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'form_id' => ['required', 'uuid', Rule::exists('me_data_entry_forms', 'id')],
            'reporting_quarter' => ['required', Rule::in(array_keys(MePerformanceReport::QUARTERS))],
            'reporting_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $form = $this->scopedForms($request)
            ->with([
                'projectComponent:id,project_id,name,governance_node_id',
                'indicators.frequency',
                'indicators.setupTarget',
                'indicators.targets',
            ])
            ->findOrFail($validated['form_id']);

        if ($form->status !== MeDataEntryForm::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'form_id' => 'Choose a published reporting form.',
            ]);
        }
        if (! $form->projectComponent) {
            throw ValidationException::withMessages([
                'form_id' => 'The reporting form must be linked to a project component.',
            ]);
        }

        $report = $this->createReportFor(
            $form,
            (int) $validated['reporting_year'],
            (string) $validated['reporting_quarter'],
            $request
        );

        return redirect()
            ->route('budget.me.performance-reports.edit', $report)
            ->with('success', 'Quarterly report created. Complete the report sections and attach supporting evidence.');
    }

    public function edit(Request $request, MePerformanceReport $report): View
    {
        $this->assertReportInScope($request, $report);
        $report->load([
            'form:id,code,title,indicator_id',
            'portfolio:id,name',
            'projectComponent:id,project_id,name,governance_node_id',
            'responsibleDirectorate:id,name,code',
            'reportingPeriod:id,label,period_start,period_end',
            'indicatorResults.indicator:id,indicator_code,name,unit_id,means_of_verification_id,frequency_of_reporting_id,data_collection_method',
            'indicatorResults.indicator.unit:id,name,symbol',
            'indicatorResults.indicator.meansOfVerification:id,title,file_path,external_url',
            'documents',
            'thinkTank:id,name,role,country',
            'createdBy:id,name',
            'reviewedBy:id,name',
            'archivedBy:id,name',
            'transitions.actor:id,name',
        ]);
        $sectionCompletion = $report->sectionCompletion();

        return view('me.performance-reports.edit', [
            'report' => $report,
            'performanceRatings' => MePerformanceReport::PERFORMANCE_RATINGS,
            'canManage' => $this->userMayAuthorReport($request, $report),
            'canReview' => $request->user()->can('me.performance_reports.review'),
            'canArchive' => $request->user()->can('me.performance_reports.archive'),
            'sectionCompletion' => $sectionCompletion,
            'submissionReady' => collect($sectionCompletion)
                ->every(fn (array $section): bool => $section['status'] === 'complete'),
        ]);
    }

    public function update(Request $request, MePerformanceReport $report): RedirectResponse
    {
        $this->assertReportInScope($request, $report);
        abort_unless($this->userMayAuthorReport($request, $report), 403, 'Only the assigned report author can edit this draft.');
        if (! $report->isEditable()) {
            throw ValidationException::withMessages([
                'report' => 'Only draft reports can be edited.',
            ]);
        }

        $validated = $request->validate($this->reportRules(false));
        $storedPaths = [];

        try {
            DB::transaction(function () use ($request, $report, $validated, &$storedPaths): void {
                $report->update($this->reportNarrativeAttributes($validated) + [
                    'updated_by' => $request->user()->id,
                ]);

                $this->syncIndicatorResults($report, $validated['indicator_results'] ?? [], $request);
                $this->storeDocuments($report, $validated, $request, $storedPaths);
            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                Storage::disk('local')->delete($storedPath);
            }

            throw $exception;
        }

        return redirect()
            ->route('budget.me.performance-reports.edit', $report)
            ->with('success', 'Report draft saved and indicator progress recalculated.');
    }

    public function submit(Request $request, MePerformanceReport $report): RedirectResponse
    {
        $this->assertReportInScope($request, $report);
        abort_unless($this->userMayAuthorReport($request, $report), 403, 'Only the assigned report author can submit this report.');
        if (! $report->isEditable()) {
            throw ValidationException::withMessages([
                'report' => 'This report is not available for submission.',
            ]);
        }

        $report->load(['indicatorResults', 'documents']);
        $submissionIssues = $report->submissionIssues();
        if ($submissionIssues !== []) {
            throw ValidationException::withMessages([
                'report' => 'All seven mandatory sections must be complete before submission. '
                    .implode(' | ', $submissionIssues),
            ]);
        }

        DB::transaction(function () use ($request, $report): void {
            $fromStatus = (string) $report->status;
            $report->update([
                'status' => MePerformanceReport::STATUS_SUBMITTED,
                'submitted_by' => $request->user()->id,
                'submitted_at' => now(),
                'updated_by' => $request->user()->id,
            ]);

            IndicatorResult::query()
                ->whereIn('id', $report->indicatorResults->pluck('indicator_result_id')->filter())
                ->update([
                    'review_status' => 'submitted',
                    'validated_by' => null,
                    'validated_at' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'updated_by' => $request->user()->id,
                ]);

            $this->recordTransition(
                $report,
                $fromStatus,
                MePerformanceReport::STATUS_SUBMITTED,
                'submitted',
                'Submitted to the Secretariat/M&E Officer for review.',
                (string) $request->user()->id
            );
        });
        app(MeReportingNotificationService::class)->performanceLifecycle($report, 'submitted');

        return redirect()
            ->route('budget.me.performance-reports.edit', $report)
            ->with('success', 'Report submitted for review.');
    }

    public function review(Request $request, MePerformanceReport $report): RedirectResponse
    {
        $this->assertReportInScope($request, $report);
        if ((string) $report->created_by === (string) $request->user()->id
            && ! $request->user()->isAdmin()
            && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Report authors cannot review and approve their own report.');
        }
        $validated = $request->validate([
            'review_action' => ['required', Rule::in([
                'returned',
                MePerformanceReport::STATUS_REVIEWED,
            ])],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $action = (string) $validated['review_action'];
        if ($action === 'returned' && blank($validated['review_notes'] ?? null)) {
            throw ValidationException::withMessages([
                'review_notes' => 'Explain the corrections required before returning this report.',
            ]);
        }
        if (! $report->isSubmitted()) {
            throw ValidationException::withMessages([
                'review_action' => 'Only submitted reports can be reviewed or returned.',
            ]);
        }

        $report->load(['indicatorResults.indicator', 'documents']);
        if ($action === MePerformanceReport::STATUS_REVIEWED && ! $report->isSubmissionReady()) {
            throw ValidationException::withMessages([
                'review_action' => 'This report is incomplete and cannot be approved. Return it to the author for correction.',
            ]);
        }

        DB::transaction(function () use ($request, $report, $validated, $action): void {
            $targetStatus = $action === 'returned'
                ? MePerformanceReport::STATUS_DRAFT
                : MePerformanceReport::STATUS_REVIEWED;
            $report->update([
                'status' => $targetStatus,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $validated['review_notes'] ?? null,
                'updated_by' => $request->user()->id,
            ]);

            $resultIds = $report->indicatorResults->pluck('indicator_result_id')->filter();
            if ($resultIds->isNotEmpty()) {
                $attributes = [
                    'review_status' => $action === 'returned' ? 'returned' : 'approved',
                    'review_notes' => $validated['review_notes'] ?? null,
                    'updated_by' => $request->user()->id,
                ];
                if ($action === 'returned') {
                    $attributes += [
                        'validated_by' => null,
                        'validated_at' => null,
                        'approved_by' => null,
                        'approved_at' => null,
                    ];
                } else {
                    $attributes += [
                        'validated_by' => $request->user()->id,
                        'validated_at' => now(),
                        'approved_by' => $request->user()->id,
                        'approved_at' => now(),
                    ];
                }

                IndicatorResult::query()->whereIn('id', $resultIds)->update($attributes);
            }
            if ($action !== 'returned') {
                $report->documents()->update([
                    'validation_status' => 'validated',
                    'validated_by' => $request->user()->id,
                    'validated_at' => now(),
                    'validation_notes' => $validated['review_notes'] ?? null,
                ]);
            }

            $this->recordTransition(
                $report,
                MePerformanceReport::STATUS_SUBMITTED,
                $targetStatus,
                $action === 'returned' ? 'returned_for_correction' : 'reviewed_and_approved',
                $validated['review_notes'] ?? null,
                (string) $request->user()->id
            );
        });
        app(MeReportingNotificationService::class)->performanceLifecycle(
            $report,
            $action === 'returned' ? 'returned' : 'approved'
        );

        return redirect()
            ->route('budget.me.performance-reports.edit', $report)
            ->with(
                'success',
                $action === 'returned'
                    ? 'Report returned to the author as a draft for correction.'
                    : 'Report reviewed and approved.'
            );
    }

    public function archive(Request $request, MePerformanceReport $report): RedirectResponse
    {
        $this->assertReportInScope($request, $report);
        if (! $report->isReviewed()) {
            throw ValidationException::withMessages([
                'report' => 'Only a reviewed and approved report can be archived.',
            ]);
        }

        $validated = $request->validate([
            'archive_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($request, $report, $validated): void {
            $report->update([
                'status' => MePerformanceReport::STATUS_ARCHIVED,
                'archived_by' => $request->user()->id,
                'archived_at' => now(),
                'archive_notes' => $validated['archive_notes'] ?? null,
                'updated_by' => $request->user()->id,
            ]);

            $this->recordTransition(
                $report,
                MePerformanceReport::STATUS_REVIEWED,
                MePerformanceReport::STATUS_ARCHIVED,
                'archived',
                $validated['archive_notes'] ?? null,
                (string) $request->user()->id
            );
        });
        app(MeReportingNotificationService::class)->performanceLifecycle($report, 'archived');

        return redirect()
            ->route('budget.me.performance-reports.edit', $report)
            ->with('success', 'Report archived as a read-only historical record.');
    }

    public function downloadDocument(
        Request $request,
        MePerformanceReport $report,
        MePerformanceReportDocument $document
    ) {
        $this->assertReportInScope($request, $report);
        abort_unless((string) $document->report_id === (string) $report->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }

    public function destroyDocument(
        Request $request,
        MePerformanceReport $report,
        MePerformanceReportDocument $document
    ): RedirectResponse {
        $this->assertReportInScope($request, $report);
        abort_unless((string) $document->report_id === (string) $report->id, 404);
        abort_unless($this->userMayAuthorReport($request, $report), 403, 'Only the assigned report author can remove draft evidence.');
        if (! $report->isEditable()) {
            throw ValidationException::withMessages([
                'documents' => 'Documents cannot be removed after report submission.',
            ]);
        }

        $path = $document->file_path;
        $document->delete();
        Storage::disk('local')->delete($path);

        return back()->with('success', 'Supporting document removed.');
    }

    protected function createReportFor(
        MeDataEntryForm $form,
        int $year,
        string $quarter,
        Request $request,
        ?ConsortiumThinkTank $member = null,
        ?MeDataCollectionAssignment $assignment = null
    ): MePerformanceReport {
        $form->loadMissing([
            'projectComponent:id,project_id,name,governance_node_id',
            'indicators.frequency',
            'indicators.setupTarget',
            'indicators.targets',
        ]);

        if ($form->status !== MeDataEntryForm::STATUS_PUBLISHED || ! $form->projectComponent) {
            throw ValidationException::withMessages([
                'form_id' => 'Choose a published reporting form linked to a project component.',
            ]);
        }

        $dueIndicators = $form->indicators
            ->filter(fn (Indicator $indicator): bool => (string) $indicator->project_component_id === (string) $form->project_component_id
                && IndicatorReportingSchedule::isDueInQuarter($indicator, $quarter))
            ->values();

        if ($dueIndicators->isEmpty()) {
            throw ValidationException::withMessages([
                'reporting_quarter' => 'None of this form’s linked indicators are due in the selected quarter under their approved reporting frequency.',
            ]);
        }

        $duplicate = MePerformanceReport::query()
            ->where('form_id', $form->id)
            ->where('reporting_year', $year)
            ->where('reporting_quarter', $quarter)
            ->when(
                $member,
                fn ($query) => $query->where('think_tank_member_id', $member->id),
                fn ($query) => $query->whereNull('think_tank_member_id')
            )
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'reporting_quarter' => 'A report already exists for this reporting form, owner, and period.',
            ]);
        }

        [$periodStart, $periodEnd] = $this->quarterDates($year, $quarter);
        $period = $this->resolveReportingPeriod(
            $form,
            $year,
            $quarter,
            $periodStart,
            $periodEnd,
            (string) $request->user()->id
        );

        return DB::transaction(function () use (
            $form,
            $period,
            $year,
            $quarter,
            $periodStart,
            $periodEnd,
            $dueIndicators,
            $request,
            $member,
            $assignment
        ): MePerformanceReport {
            $report = MePerformanceReport::query()->create([
                'form_id' => $form->id,
                'reporting_period_id' => $period->id,
                'portfolio_id' => $form->portfolio_id,
                'project_component_id' => $form->project_component_id,
                'responsible_directorate_id' => $form->projectComponent?->governance_node_id,
                'think_tank_member_id' => $member?->id,
                'assignment_id' => $assignment?->id,
                'reporting_year' => $year,
                'reporting_quarter' => $quarter,
                'status' => MePerformanceReport::STATUS_DRAFT,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            foreach ($dueIndicators as $indicator) {
                $target = $this->targetForPeriod($indicator, $periodStart, $periodEnd, "{$year}-{$quarter}");

                $report->indicatorResults()->create([
                    'indicator_id' => $indicator->id,
                    'target_value' => $target?->target_value,
                    'annual_target' => $indicator->annual_target,
                    'life_of_programme_target' => $indicator->life_of_programme_target
                        ?? $indicator->setupTarget?->target_value,
                    'aggregation_method' => $indicator->aggregation_method ?: 'non_additive',
                    'cumulative_programme_result' => $indicator->baseline_value,
                    'reporting_frequency' => IndicatorReportingSchedule::cadenceLabel($indicator),
                ]);
            }

            $this->recordTransition(
                $report,
                null,
                MePerformanceReport::STATUS_DRAFT,
                'created',
                $member
                    ? 'Draft report created by '.$member->name.'.'
                    : 'Draft report created by the Secretariat/M&E team.',
                (string) $request->user()->id
            );

            return $report;
        });
    }

    protected function recordTransition(
        MePerformanceReport $report,
        ?string $fromStatus,
        string $toStatus,
        string $action,
        ?string $notes,
        string $userId
    ): void {
        $report->transitions()->create([
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'action' => $action,
            'notes' => $notes,
            'acted_by' => $userId,
        ]);
    }

    protected function userMayAuthorReport(Request $request, MePerformanceReport $report): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        if ($report->think_tank_member_id) {
            if (! $user->isThinkTankUser()
                || ! $user->canAccessThinkTankArea('me')
                || ! $user->can('think_tank.me.reports.manage')) {
                return false;
            }

            return (string) $user->resolvedThinkTankMembership()?->id === (string) $report->think_tank_member_id;
        }

        return (string) $report->created_by === (string) $user->id
            && ($user->can('me.data_entry.manage') || $user->can('me.configuration.manage'));
    }

    private function reportRules(bool $final): array
    {
        $required = $final ? 'required' : 'nullable';

        return [
            'indicator_results' => ['required', 'array', 'min:1'],
            'indicator_results.*.actual_value' => [$required, 'numeric'],
            'key_achievements' => [$required, 'string', 'max:20000'],
            'variance_explanation' => [$required, 'string', 'max:20000'],
            'means_of_verification_notes' => [$required, 'string', 'max:20000'],
            'overall_assessment' => [$required, 'string', 'max:20000'],
            'performance_rating' => [$required, Rule::in(array_keys(MePerformanceReport::PERFORMANCE_RATINGS))],
            'conclusion' => [$required, 'string', 'max:20000'],
            'challenges_faced' => [$required, 'string', 'max:20000'],
            'mitigation_strategies' => [$required, 'string', 'max:20000'],
            'lessons_learned' => [$required, 'string', 'max:20000'],
            'adaptive_management_actions' => [$required, 'string', 'max:20000'],
            'next_period_priorities' => [$required, 'string', 'max:20000'],
            'document_names' => ['nullable', 'array', 'max:10'],
            'document_names.*' => ['nullable', 'string', 'max:255'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => [
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,zip',
            ],
        ];
    }

    private function reportNarrativeAttributes(array $validated): array
    {
        return collect([
            'key_achievements',
            'variance_explanation',
            'means_of_verification_notes',
            'overall_assessment',
            'performance_rating',
            'conclusion',
            'challenges_faced',
            'mitigation_strategies',
            'lessons_learned',
            'adaptive_management_actions',
            'next_period_priorities',
        ])->mapWithKeys(fn (string $key): array => [$key => $validated[$key] ?? null])->all();
    }

    private function syncIndicatorResults(MePerformanceReport $report, array $values, Request $request): void
    {
        $report->loadMissing(['reportingPeriod', 'indicatorResults.indicator']);
        foreach ($report->indicatorResults as $reportResult) {
            $actual = data_get($values, $reportResult->id.'.actual_value');
            $actual = $actual === null || $actual === '' ? null : (float) $actual;
            $target = $reportResult->target_value === null ? null : (float) $reportResult->target_value;
            $progress = $actual !== null && $target !== null && $target != 0.0
                ? round(($actual / $target) * 100, 2)
                : null;

            $indicatorResult = $reportResult->indicatorResult ?: new IndicatorResult();
            $indicatorResult->fill([
                'indicator_id' => $reportResult->indicator_id,
                'reporting_period_id' => $report->reporting_period_id,
                'think_tank_member_id' => $report->think_tank_member_id,
                'period_type' => 'quarter',
                'period_label' => $report->periodLabel(),
                'period_start' => $report->reportingPeriod->period_start,
                'period_end' => $report->reportingPeriod->period_end,
                'actual_value' => $actual,
                'unit_id' => $reportResult->indicator?->unit_id,
                'data_source' => 'Quarterly performance report '.$report->form?->code,
                'method' => $reportResult->indicator?->data_collection_method,
                'notes' => $report->variance_explanation,
                'review_status' => 'draft',
                'validated_by' => null,
                'validated_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'review_notes' => null,
                'collected_by' => $request->user()->id,
                'collected_at' => now(),
                'updated_by' => $request->user()->id,
            ]);
            if (! $indicatorResult->exists) {
                $indicatorResult->created_by = $request->user()->id;
            }
            $indicatorResult->save();

            $reportResult->update([
                'indicator_result_id' => $indicatorResult->id,
                'actual_value' => $actual,
                'progress_percent' => $progress,
            ]);
        }

        foreach ($report->indicatorResults->pluck('indicator_id')->unique() as $indicatorId) {
            app(IndicatorAggregationService::class)->recalculate(
                (string) $indicatorId,
                $report->think_tank_member_id ? (string) $report->think_tank_member_id : null
            );
        }
    }

    private function storeDocuments(
        MePerformanceReport $report,
        array $validated,
        Request $request,
        array &$storedPaths
    ): void {
        $names = $validated['document_names'] ?? [];
        foreach ($request->file('documents', []) as $index => $file) {
            $path = $file->store('me/performance-reports/'.$report->id, 'local');
            $storedPaths[] = $path;

            $report->documents()->create([
                'document_name' => trim((string) ($names[$index] ?? ''))
                    ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    private function scopedForms(Request $request)
    {
        $query = MeDataEntryForm::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query, $request->user());
        }

        return $query;
    }

    private function assertReportInScope(Request $request, MePerformanceReport $report): void
    {
        if ($this->userHasAssignedPortfolioScope($request->user())
            && ! $this->portfolioOwnedRecordIsInAssignedPortfolio($report, $request->user())) {
            abort(403, 'You do not have access to this performance report.');
        }
    }

    private function quarterDates(int $year, string $quarter): array
    {
        $startMonth = match ($quarter) {
            'Q1' => 1,
            'Q2' => 4,
            'Q3' => 7,
            'Q4' => 10,
        };
        $start = Carbon::create($year, $startMonth, 1)->startOfDay();

        return [$start->copy(), $start->copy()->addMonths(3)->subDay()->endOfDay()];
    }

    private function resolveReportingPeriod(
        MeDataEntryForm $form,
        int $year,
        string $quarter,
        Carbon $start,
        Carbon $end,
        string $userId
    ): MeReportingPeriod {
        $existing = MeReportingPeriod::query()
            ->where('portfolio_id', $form->portfolio_id)
            ->where('period_type', MeReportingPeriod::TYPE_QUARTER)
            ->whereDate('period_start', $start->toDateString())
            ->whereDate('period_end', $end->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        return MeReportingPeriod::query()->create([
            'portfolio_id' => $form->portfolio_id,
            'code' => 'ME-'.$year.'-'.$quarter.'-'.Str::upper(substr(str_replace('-', '', (string) $form->portfolio_id), 0, 8)),
            'label' => $quarter.' '.$year,
            'period_type' => MeReportingPeriod::TYPE_QUARTER,
            'period_start' => $start,
            'period_end' => $end,
            'status' => MeReportingPeriod::STATUS_ACTIVE,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function targetForPeriod(
        Indicator $indicator,
        Carbon $periodStart,
        Carbon $periodEnd,
        string $periodLabel
    ) {
        $quarter = 'Q'.(int) ceil($periodStart->month / 3);
        $year = (string) $periodStart->year;
        $acceptedLabels = [
            $periodLabel,
            str_replace('-', ' ', $periodLabel),
            $quarter.' '.$year,
            $year.' '.$quarter,
            $quarter.'-'.$year,
        ];

        return $indicator->targets
            ->first(function ($target) use ($periodStart, $periodEnd, $acceptedLabels): bool {
                if ($target->target_context === Indicator::SETUP_TARGET_CONTEXT) {
                    return false;
                }
                if ($target->period_type === 'quarter' && in_array($target->period_label, $acceptedLabels, true)) {
                    return true;
                }

                return $target->period_start
                    && $target->period_start->betweenIncluded($periodStart, $periodEnd);
            })
            ?? $indicator->setupTarget;
    }
}
