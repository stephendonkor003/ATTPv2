<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\IndicatorResult;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeDataEntryForm;
use App\Models\MeDisaggregationDimension;
use App\Models\MeIndicatorAchievement;
use App\Models\MeKnowledgeEvidenceItem;
use App\Models\MePerformanceReport;
use App\Models\MePerformanceReportDocument;
use App\Models\MeRepositoryDocumentLink;
use App\Models\MeRepositoryDocumentVersion;
use App\Models\MeReportingPeriod;
use App\Support\IndicatorReportingSchedule;
use App\Services\IndicatorAggregationService;
use App\Services\MeReportingNotificationService;
use App\Services\MeRepositoryFolderService;
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
            ->only(['store', 'update', 'submit', 'replaceDocument', 'destroyDocument']);
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
                'indicators:id,indicator_code,name,definitions,results_level,value_type,baseline_value,annual_target,life_of_programme_target,frequency_of_reporting_id,unit_id,data_collection_method,means_of_verification_folder_id,requires_evidence',
                'indicators.frequency:id,name,code,interval_unit,interval_value,frequency_in_days',
                'indicators.unit:id,name,symbol',
                'indicators.disaggregationRequirements.dimension:id,code,name,dimension_group,sort_order',
                'indicators.meansOfVerificationFolder:id,name',
                'indicators.meansOfVerificationFolder.documents:id,folder_id',
            ])
            ->orderBy('title')
            ->get();

        return view('me.performance-reports.create', [
            'forms' => $forms,
            'periodTypes' => MePerformanceReport::REPORTING_PERIOD_TYPES,
            'periodLabels' => MePerformanceReport::PERIOD_LABELS,
            'defaultYear' => (int) now()->year,
            'reportingTaxonomy' => $this->reportingTaxonomy(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->normalizeLegacyPeriodInput($request);
        $validated = $request->validate([
            'form_id' => ['required', 'uuid', Rule::exists('me_data_entry_forms', 'id')],
            'reporting_period_type' => ['required', Rule::in(array_keys(MePerformanceReport::REPORTING_PERIOD_TYPES))],
            'reporting_period_label' => ['required', 'string', 'max:40'],
            'reporting_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'reporting_scope' => ['nullable', 'array'],
            'reporting_scope.geographic_scope' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::GEOGRAPHIC_SCOPES))],
            'reporting_scope.country' => ['nullable', 'string', 'max:120'],
            'reporting_scope.rec' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::RECS))],
            'reporting_scope.priority_theme' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::PRIORITY_THEMES))],
            'reporting_scope.gender' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::GENDERS))],
            'reporting_scope.age_group' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::AGE_GROUPS))],
            'reporting_scope.stakeholder_category' => ['nullable', Rule::in(array_keys(MeIndicatorAchievement::STAKEHOLDER_CATEGORIES))],
        ]);
        $this->assertValidPeriodSelection($validated['reporting_period_type'], $validated['reporting_period_label']);

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
            (string) $validated['reporting_period_type'],
            (string) $validated['reporting_period_label'],
            $request,
            reportingScope: $this->cleanReportingScope($validated['reporting_scope'] ?? [])
        );

        return redirect()
            ->route('budget.me.performance-reports.edit', $report)
            ->with('success', $report->periodLabel().' report created. Complete the indicator achievements, results, narratives and supporting evidence.');
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
            'indicatorResults.indicator:id,indicator_code,name,definitions,results_level,value_type,baseline_value,annual_target,life_of_programme_target,unit_id,means_of_verification_id,means_of_verification_folder_id,frequency_of_reporting_id,data_collection_method,organization_rollup_method,requires_evidence',
            'indicatorResults.indicator.unit:id,name,symbol',
            'indicatorResults.indicator.meansOfVerification:id,title,file_path,external_url',
            'indicatorResults.indicator.meansOfVerificationFolder:id,portfolio_id,name',
            'indicatorResults.indicator.meansOfVerificationFolder.documents:id,folder_id,title,document_type,validation_status,version_number',
            'indicatorResults.indicator.disaggregationRequirements.dimension:id,code,name,dimension_group,sort_order',
            'indicatorResults.achievements.breakdowns',
            'indicatorResults.achievements.leadThinkTank:id,name,country',
            'indicatorResults.achievements.documentLinks.repositoryItem:id,title,document_type,repository_category,original_filename,file_size,validation_status',
            'documents.repositoryItem.versions',
            'thinkTank:id,name,role,country',
            'createdBy:id,name',
            'reviewedBy:id,name',
            'verifiedBy:id,name',
            'approvedBy:id,name',
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
            'achievementTaxonomy' => $this->reportingTaxonomy(),
            'reportAnalytics' => $this->reportAnalytics($report),
            'activeThinkTanks' => ConsortiumThinkTank::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'country']),
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
                MePerformanceReport::STATUS_VERIFIED,
                MePerformanceReport::STATUS_APPROVED,
            ])],
            'review_notes' => ['required', 'string', 'max:5000'],
        ]);

        $action = (string) $validated['review_action'];
        $allowed = match ($action) {
            'returned' => in_array($report->status, [MePerformanceReport::STATUS_SUBMITTED, MePerformanceReport::STATUS_VERIFIED], true),
            MePerformanceReport::STATUS_VERIFIED => $report->isSubmitted(),
            MePerformanceReport::STATUS_APPROVED => $report->isVerified(),
            default => false,
        };
        if (! $allowed) {
            throw ValidationException::withMessages([
                'review_action' => 'This decision is not available at the report’s current lifecycle stage.',
            ]);
        }
        if ($action === MePerformanceReport::STATUS_APPROVED
            && (string) $report->verified_by === (string) $request->user()->id
            && ! $request->user()->isAdmin()
            && ! $request->user()->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'review_action' => 'The officer who verified this report cannot also give final approval. Ask another authorized reviewer to approve it.',
            ]);
        }

        $report->load(['indicatorResults.indicator', 'documents']);
        if (in_array($action, [MePerformanceReport::STATUS_VERIFIED, MePerformanceReport::STATUS_APPROVED], true)
            && ! $report->isSubmissionReady()) {
            throw ValidationException::withMessages([
                'review_action' => 'This report is incomplete and cannot advance. Return it to the author for correction.',
            ]);
        }

        DB::transaction(function () use ($request, $report, $validated, $action): void {
            $fromStatus = (string) $report->status;
            $targetStatus = $action === 'returned'
                ? MePerformanceReport::STATUS_DRAFT
                : $action;
            $attributes = [
                'status' => $targetStatus,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $validated['review_notes'] ?? null,
                'updated_by' => $request->user()->id,
            ];
            if ($action === 'returned') {
                $attributes += [
                    'verified_by' => null,
                    'verified_at' => null,
                    'verification_notes' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'approval_notes' => null,
                ];
            } elseif ($action === MePerformanceReport::STATUS_VERIFIED) {
                $attributes += [
                    'verified_by' => $request->user()->id,
                    'verified_at' => now(),
                    'verification_notes' => $validated['review_notes'] ?? null,
                ];
            } else {
                $attributes += [
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                    'approval_notes' => $validated['review_notes'] ?? null,
                ];
            }
            $report->update($attributes);

            $resultIds = $report->indicatorResults->pluck('indicator_result_id')->filter();
            if ($resultIds->isNotEmpty()) {
                $attributes = [
                    'review_status' => $action === 'returned' ? 'returned' : $action,
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
                } elseif ($action === MePerformanceReport::STATUS_VERIFIED) {
                    $attributes += [
                        'validated_by' => $request->user()->id,
                        'validated_at' => now(),
                        'approved_by' => null,
                        'approved_at' => null,
                    ];
                } else {
                    $attributes += [
                        'approved_by' => $request->user()->id,
                        'approved_at' => now(),
                    ];
                }

                IndicatorResult::query()->whereIn('id', $resultIds)->update($attributes);
            }
            if ($action === MePerformanceReport::STATUS_VERIFIED) {
                $report->documents()->update([
                    'validation_status' => 'validated',
                    'validated_by' => $request->user()->id,
                    'validated_at' => now(),
                    'validation_notes' => $validated['review_notes'] ?? null,
                ]);
            }

            $this->recordTransition(
                $report,
                $fromStatus,
                $targetStatus,
                $action === 'returned' ? 'returned_for_correction' : $action,
                $validated['review_notes'] ?? null,
                (string) $request->user()->id
            );
        });
        app(MeReportingNotificationService::class)->performanceLifecycle(
            $report,
            $action
        );
        $this->recalculateReportIndicators($report);

        return redirect()
            ->route('budget.me.performance-reports.edit', $report)
            ->with(
                'success',
                $action === 'returned'
                    ? 'Report returned to the author as a draft for correction.'
                    : ($action === MePerformanceReport::STATUS_VERIFIED
                        ? 'Report verified and sent for final approval.'
                        : 'Report received final approval.')
            );
    }

    public function archive(Request $request, MePerformanceReport $report): RedirectResponse
    {
        $this->assertReportInScope($request, $report);
        if (! $report->isApproved()) {
            throw ValidationException::withMessages([
                'report' => 'Only a finally approved report can be archived.',
            ]);
        }

        $validated = $request->validate([
            'archive_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $fromStatus = (string) $report->status;

        DB::transaction(function () use ($request, $report, $validated, $fromStatus): void {
            $report->update([
                'status' => MePerformanceReport::STATUS_ARCHIVED,
                'archived_by' => $request->user()->id,
                'archived_at' => now(),
                'archive_notes' => $validated['archive_notes'] ?? null,
                'updated_by' => $request->user()->id,
            ]);

            $this->recordTransition(
                $report,
                $fromStatus,
                MePerformanceReport::STATUS_ARCHIVED,
                'archived',
                $validated['archive_notes'] ?? null,
                (string) $request->user()->id
            );
        });
        app(MeReportingNotificationService::class)->performanceLifecycle($report, 'archived');
        $this->recalculateReportIndicators($report);

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
        $repositoryItemId = $document->repository_item_id;
        $document->delete();
        if ($repositoryItemId && ! $report->documents()->where('repository_item_id', $repositoryItemId)->exists()) {
            MeRepositoryDocumentLink::query()
                ->where('repository_item_id', $repositoryItemId)
                ->where('linkable_type', MePerformanceReport::class)
                ->where('linkable_id', $report->id)
                ->where('purpose', 'report_attachment')
                ->delete();
        } else {
            Storage::disk('local')->delete($path);
        }

        return back()->with('success', 'Supporting document removed.');
    }

    public function replaceDocument(
        Request $request,
        MePerformanceReport $report,
        MePerformanceReportDocument $document
    ): RedirectResponse {
        $this->assertReportInScope($request, $report);
        abort_unless((string) $document->report_id === (string) $report->id, 404);
        abort_unless($this->userMayAuthorReport($request, $report), 403, 'Only the assigned report author can upload a new evidence version.');
        if (! $report->isEditable()) {
            throw ValidationException::withMessages([
                'replacement_file' => 'A new document version can only be uploaded while the report is a draft or returned for correction.',
            ]);
        }
        $validated = $request->validate([
            'replacement_file' => [
                'required', 'file', 'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,zip',
            ],
            'change_notes' => ['required', 'string', 'max:5000'],
        ]);

        $file = $request->file('replacement_file');
        $checksum = hash_file('sha256', $file->getRealPath());
        $document->load('repositoryItem.versions');
        $repositoryItem = $document->repositoryItem;
        if ($repositoryItem?->checksum_sha256 && hash_equals((string) $repositoryItem->checksum_sha256, $checksum)) {
            throw ValidationException::withMessages([
                'replacement_file' => 'This file is identical to the current version.',
            ]);
        }

        $folder = app(MeRepositoryFolderService::class)->forReport($report, (string) $request->user()->id);
        $path = $file->store('me/performance-reports/'.$report->id, 'local');
        try {
            DB::transaction(function () use ($request, $report, $document, $repositoryItem, $folder, $file, $path, $checksum, $validated): void {
                if (! $repositoryItem) {
                    $repositoryItem = MeKnowledgeEvidenceItem::query()->create([
                        'portfolio_id' => $report->portfolio_id,
                        'folder_id' => $folder->id,
                        'title' => $document->document_name,
                        'document_type' => 'supporting_evidence',
                        'repository_category' => 'evidence',
                        'description' => 'Supporting evidence synchronized from performance report '.$report->periodLabel().'.',
                        'file_path' => $document->file_path,
                        'original_filename' => $document->original_filename,
                        'mime_type' => $document->mime_type,
                        'file_size' => $document->file_size,
                        'version_number' => 1,
                        'created_by' => $document->uploaded_by,
                        'updated_by' => $request->user()->id,
                    ]);
                    if ($document->file_path) {
                        MeRepositoryDocumentVersion::query()->create([
                            'repository_item_id' => $repositoryItem->id,
                            'version_number' => 1,
                            'file_path' => $document->file_path,
                            'original_filename' => $document->original_filename,
                            'mime_type' => $document->mime_type,
                            'file_size' => $document->file_size,
                            'change_notes' => 'Original report attachment registered before replacement.',
                            'uploaded_by' => $document->uploaded_by,
                        ]);
                    }
                    $document->update(['repository_item_id' => $repositoryItem->id]);
                }

                $nextVersion = max(
                    (int) $repositoryItem->version_number,
                    (int) $repositoryItem->versions()->max('version_number')
                ) + 1;
                MeRepositoryDocumentVersion::query()->create([
                    'repository_item_id' => $repositoryItem->id,
                    'version_number' => $nextVersion,
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'checksum_sha256' => $checksum,
                    'change_notes' => trim((string) $validated['change_notes']),
                    'uploaded_by' => $request->user()->id,
                ]);
                $repositoryItem->update([
                    'folder_id' => $folder->id,
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'checksum_sha256' => $checksum,
                    'version_number' => $nextVersion,
                    'validation_status' => 'pending',
                    'validated_by' => null,
                    'validated_at' => null,
                    'validation_notes' => null,
                    'updated_by' => $request->user()->id,
                ]);
                $document->update([
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'validation_status' => 'pending',
                    'validated_by' => null,
                    'validated_at' => null,
                    'validation_notes' => null,
                    'uploaded_by' => $request->user()->id,
                ]);
                $repositoryItem->links()->firstOrCreate([
                    'linkable_type' => MePerformanceReport::class,
                    'linkable_id' => $report->id,
                    'purpose' => 'report_attachment',
                ], ['linked_by' => $request->user()->id]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'New document version uploaded. All earlier versions remain available in the audit history.');
    }

    protected function createReportFor(
        MeDataEntryForm $form,
        int $year,
        string $periodType,
        string $periodLabel,
        Request $request,
        ?ConsortiumThinkTank $member = null,
        ?MeDataCollectionAssignment $assignment = null,
        array $reportingScope = []
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
                && IndicatorReportingSchedule::isDueInPeriod($indicator, $periodType, $periodLabel))
            ->values();

        if ($dueIndicators->isEmpty()) {
            throw ValidationException::withMessages([
                'reporting_quarter' => 'None of this form’s linked indicators are due in the selected quarter under their approved reporting frequency.',
            ]);
        }

        $duplicate = MePerformanceReport::query()
            ->where('form_id', $form->id)
            ->where('reporting_year', $year)
            ->where('reporting_period_type', $periodType)
            ->where('reporting_period_label', $periodLabel)
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

        $assignedPeriod = $assignment?->collection?->reportingPeriod;
        if ($assignedPeriod) {
            $period = $assignedPeriod;
            $periodStart = Carbon::parse($period->period_start);
            $periodEnd = Carbon::parse($period->period_end);
        } else {
            [$periodStart, $periodEnd] = $this->periodDates($year, $periodType, $periodLabel);
            $period = $this->resolveReportingPeriod(
                $form,
                $year,
                $periodType,
                $periodLabel,
                $periodStart,
                $periodEnd,
                (string) $request->user()->id
            );
        }

        return DB::transaction(function () use (
            $form,
            $period,
            $year,
            $periodType,
            $periodLabel,
            $periodStart,
            $periodEnd,
            $dueIndicators,
            $request,
            $member,
            $assignment,
            $reportingScope
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
                'reporting_quarter' => $this->legacyQuarter($periodType, $periodLabel),
                'reporting_period_type' => $periodType,
                'reporting_period_label' => $periodLabel,
                'reporting_scope' => $reportingScope ?: null,
                'status' => MePerformanceReport::STATUS_DRAFT,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            foreach ($dueIndicators as $indicator) {
                $target = $this->targetForPeriod($indicator, $periodStart, $periodEnd, $periodType, "{$year}-{$periodLabel}");

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
            'indicator_results.*.actual_value' => ['nullable', 'numeric'],
            'indicator_results.*.actual_text' => ['nullable', 'string', 'max:20000'],
            'indicator_results.*.rollup_numerator' => ['nullable', 'numeric', 'min:0'],
            'indicator_results.*.rollup_denominator' => ['nullable', 'numeric', 'gt:0'],
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
            $actualText = trim((string) data_get($values, $reportResult->id.'.actual_text', '')) ?: null;
            if ($reportResult->indicator?->value_type === 'milestone') {
                $actual = null;
            } else {
                $actualText = null;
            }
            $numerator = data_get($values, $reportResult->id.'.rollup_numerator');
            $numerator = $numerator === null || $numerator === '' ? null : (float) $numerator;
            $denominator = data_get($values, $reportResult->id.'.rollup_denominator');
            $denominator = $denominator === null || $denominator === '' ? null : (float) $denominator;
            if ($reportResult->indicator?->organization_rollup_method === 'weighted_average'
                && $numerator !== null && $denominator !== null && $denominator > 0) {
                $actual = round(($numerator / $denominator) * 100, 4);
            }
            $target = $reportResult->target_value === null ? null : (float) $reportResult->target_value;
            $progress = $actual !== null && $target !== null && $target != 0.0
                ? round(($actual / $target) * 100, 2)
                : null;

            $indicatorResult = $reportResult->indicatorResult ?: new IndicatorResult();
            $indicatorResult->fill([
                'indicator_id' => $reportResult->indicator_id,
                'reporting_period_id' => $report->reporting_period_id,
                'think_tank_member_id' => $report->think_tank_member_id,
                'period_type' => match ($report->reporting_period_type) {
                    'annual' => 'year',
                    'quarter' => 'quarter',
                    default => 'custom',
                },
                'period_label' => $report->periodLabel(),
                'period_start' => $report->reportingPeriod->period_start,
                'period_end' => $report->reportingPeriod->period_end,
                'actual_value' => $actual,
                'actual_text' => $actualText,
                'rollup_numerator' => $numerator,
                'rollup_denominator' => $denominator,
                'unit_id' => $reportResult->indicator?->unit_id,
                'data_source' => Str::headline((string) $report->reporting_period_type).' performance report '.$report->form?->code,
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
                'actual_text' => $actualText,
                'rollup_numerator' => $numerator,
                'rollup_denominator' => $denominator,
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

    private function recalculateReportIndicators(MePerformanceReport $report): void
    {
        $report->loadMissing('indicatorResults:id,report_id,indicator_id');
        foreach ($report->indicatorResults->pluck('indicator_id')->filter()->unique() as $indicatorId) {
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
        $folder = app(MeRepositoryFolderService::class)->forReport($report, (string) $request->user()->id);
        foreach ($request->file('documents', []) as $index => $file) {
            $title = trim((string) ($names[$index] ?? ''))
                ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $checksum = hash_file('sha256', $file->getRealPath());
            $repositoryItem = MeKnowledgeEvidenceItem::query()
                ->where('portfolio_id', $report->portfolio_id)
                ->where('folder_id', $folder->id)
                ->where('checksum_sha256', $checksum)
                ->whereNull('retired_at')
                ->first();

            if ($repositoryItem) {
                $path = $repositoryItem->file_path;
            } else {
                $path = $file->store('me/performance-reports/'.$report->id, 'local');
                $storedPaths[] = $path;
                $repositoryItem = MeKnowledgeEvidenceItem::query()->create([
                    'portfolio_id' => $report->portfolio_id,
                    'folder_id' => $folder->id,
                    'title' => $title,
                    'document_type' => 'supporting_evidence',
                    'repository_category' => 'evidence',
                    'description' => 'Supporting evidence synchronized from performance report '.$report->periodLabel().'.',
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'checksum_sha256' => $checksum,
                    'version_number' => 1,
                    'validation_status' => 'pending',
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
                $repositoryItem->versions()->create([
                    'version_number' => 1,
                    'file_path' => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'checksum_sha256' => $checksum,
                    'change_notes' => 'Initial upload from a performance report.',
                    'uploaded_by' => $request->user()->id,
                ]);
            }

            $report->documents()->create([
                'repository_item_id' => $repositoryItem->id,
                'document_name' => $title,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
            $repositoryItem->links()->firstOrCreate([
                'linkable_type' => MePerformanceReport::class,
                'linkable_id' => $report->id,
                'purpose' => 'report_attachment',
            ], [
                'linked_by' => $request->user()->id,
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

    private function periodDates(int $year, string $periodType, string $periodLabel): array
    {
        [$startMonth, $months] = match ($periodType.'|'.$periodLabel) {
            'quarter|Q1' => [1, 3],
            'quarter|Q2' => [4, 3],
            'quarter|Q3' => [7, 3],
            'quarter|Q4' => [10, 3],
            'semi_annual|H1' => [1, 6],
            'semi_annual|H2' => [7, 6],
            'annual|ANNUAL' => [1, 12],
            default => throw ValidationException::withMessages([
                'reporting_period_label' => 'Choose a reporting period that belongs to the selected reporting frequency.',
            ]),
        };
        $start = Carbon::create($year, $startMonth, 1)->startOfDay();

        return [$start->copy(), $start->copy()->addMonths($months)->subDay()->endOfDay()];
    }

    private function resolveReportingPeriod(
        MeDataEntryForm $form,
        int $year,
        string $periodType,
        string $periodLabel,
        Carbon $start,
        Carbon $end,
        string $userId
    ): MeReportingPeriod {
        $existing = MeReportingPeriod::query()
            ->where('portfolio_id', $form->portfolio_id)
            ->where('period_type', $this->reportingPeriodStorageType($periodType))
            ->whereDate('period_start', $start->toDateString())
            ->whereDate('period_end', $end->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        return MeReportingPeriod::query()->create([
            'portfolio_id' => $form->portfolio_id,
            'code' => 'ME-'.$year.'-'.$periodLabel.'-'.Str::upper(substr(str_replace('-', '', (string) $form->portfolio_id), 0, 8)),
            'label' => $periodLabel.' '.$year,
            'period_type' => $this->reportingPeriodStorageType($periodType),
            'period_start' => $start,
            'period_end' => $end,
            'reporting_year' => $year,
            'status' => MeReportingPeriod::STATUS_ACTIVE,
            'lifecycle_status' => MeReportingPeriod::LIFECYCLE_OPEN,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function targetForPeriod(
        Indicator $indicator,
        Carbon $periodStart,
        Carbon $periodEnd,
        string $periodType,
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
            ->first(function ($target) use ($periodStart, $periodEnd, $acceptedLabels, $periodType): bool {
                if ($target->target_context === Indicator::SETUP_TARGET_CONTEXT) {
                    return false;
                }
                $targetPeriodType = match ($periodType) {
                    'quarter' => 'quarter',
                    'annual' => 'year',
                    default => 'custom',
                };
                if ($target->period_type === $targetPeriodType && in_array($target->period_label, $acceptedLabels, true)) {
                    return true;
                }

                return $target->period_start
                    && $target->period_start->betweenIncluded($periodStart, $periodEnd);
            })
            ?? $indicator->setupTarget;
    }

    protected function normalizeLegacyPeriodInput(Request $request): void
    {
        if (! $request->filled('reporting_period_type') && $request->filled('reporting_quarter')) {
            $request->merge([
                'reporting_period_type' => 'quarter',
                'reporting_period_label' => $request->input('reporting_quarter'),
            ]);
        }
    }

    protected function assertValidPeriodSelection(string $periodType, string $periodLabel): void
    {
        if (! isset(MePerformanceReport::PERIOD_LABELS[$periodType][$periodLabel])) {
            throw ValidationException::withMessages([
                'reporting_period_label' => 'Choose a reporting period that belongs to the selected reporting frequency.',
            ]);
        }
    }

    private function legacyQuarter(string $periodType, string $periodLabel): string
    {
        return match ($periodType.'|'.$periodLabel) {
            'quarter|Q1' => 'Q1',
            'quarter|Q2', 'semi_annual|H1' => 'Q2',
            'quarter|Q3' => 'Q3',
            default => 'Q4',
        };
    }

    private function reportingPeriodStorageType(string $periodType): string
    {
        return match ($periodType) {
            'quarter' => MeReportingPeriod::TYPE_QUARTER,
            'annual' => MeReportingPeriod::TYPE_YEAR,
            default => MeReportingPeriod::TYPE_CUSTOM,
        };
    }

    /** @return array<string, array<string, string>> */
    private function reportingTaxonomy(): array
    {
        return [
            'geographic_scopes' => MeIndicatorAchievement::GEOGRAPHIC_SCOPES,
            'recs' => MeIndicatorAchievement::RECS,
            'institution_types' => MeIndicatorAchievement::INSTITUTION_TYPES,
            'priority_themes' => MeIndicatorAchievement::PRIORITY_THEMES,
            'genders' => MeIndicatorAchievement::GENDERS,
            'age_groups' => MeIndicatorAchievement::AGE_GROUPS,
            'stakeholder_categories' => MeIndicatorAchievement::STAKEHOLDER_CATEGORIES,
            'countries' => MeDisaggregationDimension::query()
                ->where('code', 'country')
                ->with(['options' => fn ($query) => $query->where('is_active', true)])
                ->first()?->options?->pluck('name', 'name')->all() ?? [],
        ];
    }

    /** @param array<string, mixed> $scope */
    private function cleanReportingScope(array $scope): array
    {
        return collect($scope)
            ->map(fn ($value) => is_scalar($value) ? trim((string) $value) : null)
            ->filter(fn ($value) => filled($value))
            ->all();
    }

    /** @return array<string, mixed> */
    private function reportAnalytics(MePerformanceReport $report): array
    {
        $results = $report->indicatorResults;
        $achievements = $results->flatMap->achievements;
        $breakdowns = $achievements->flatMap->breakdowns;
        $completeResults = $results->filter(function ($result): bool {
            return $result->indicator?->value_type === 'milestone'
                ? filled($result->actual_text)
                : $result->actual_value !== null;
        })->count();
        $evidenceLinks = $achievements
            ->flatMap->documentLinks
            ->pluck('repository_item_id')
            ->filter()
            ->unique()
            ->count();

        $groupBreakdowns = function (string $field, array $labels) use ($breakdowns): array {
            return $breakdowns
                ->filter(fn ($breakdown) => filled($breakdown->{$field}))
                ->groupBy($field)
                ->map(fn ($rows, $value): array => [
                    'key' => (string) $value,
                    'label' => $labels[$value] ?? Str::headline((string) $value),
                    'count' => (int) $rows->sum('beneficiary_count'),
                ])
                ->sortByDesc('count')
                ->values()
                ->all();
        };

        return [
            'summary' => [
                'indicators_due' => $results->count(),
                'results_reported' => $completeResults,
                'achievements' => $achievements->count(),
                'beneficiaries' => (int) $breakdowns->sum('beneficiary_count'),
                'evidence_items' => $report->documents->count() + $evidenceLinks,
            ],
            'progress' => $results->map(function ($result): array {
                $progress = $result->progress_percent;
                if ($progress === null && $result->actual_value !== null && (float) $result->target_value !== 0.0) {
                    $progress = round(((float) $result->actual_value / (float) $result->target_value) * 100, 2);
                }

                return [
                    'code' => $result->indicator?->indicator_code ?: 'Indicator',
                    'name' => $result->indicator?->name ?: 'Linked indicator',
                    'actual' => $result->indicator?->value_type === 'milestone'
                        ? ($result->actual_text ?: 'Pending')
                        : ($result->actual_value !== null ? number_format((float) $result->actual_value, 2) : 'Pending'),
                    'target' => $result->target_value !== null ? number_format((float) $result->target_value, 2) : 'Not set',
                    'progress' => $progress !== null ? (float) $progress : null,
                    'bar_width' => $progress !== null ? max(0, min(100, (float) $progress)) : 0,
                ];
            })->values()->all(),
            'disaggregation' => [
                'gender' => $groupBreakdowns('gender', MeIndicatorAchievement::GENDERS),
                'age_group' => $groupBreakdowns('age_group', MeIndicatorAchievement::AGE_GROUPS),
                'stakeholder_category' => $groupBreakdowns('stakeholder_category', MeIndicatorAchievement::STAKEHOLDER_CATEGORIES),
                'priority_theme' => $groupBreakdowns('priority_theme', MeIndicatorAchievement::PRIORITY_THEMES),
                'country' => $groupBreakdowns('country', []),
            ],
        ];
    }
}
