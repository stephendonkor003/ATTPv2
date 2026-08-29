<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\EoiReportCommunication;
use App\Models\EoiReportCommunicationRecipient;
use App\Models\EoiTechnicalProposalRound;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\Procurement;
use App\Models\ProcurementAuditLog;
use App\Models\ReworkRequest;
use App\Services\EoiQualificationService;
use App\Services\EoiReportCommunicationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PanelEvaluationController extends Controller
{
    use ScopesAssignedPortfolios;

    public function __construct(
        private readonly EoiQualificationService $eoiQualificationService,
        private readonly EoiReportCommunicationService $eoiReportCommunicationService
    ) {}

    /**
     * Procurement-first panel library. Only aggregate data is prepared here;
     * criterion evidence remains on the procurement and report workspaces.
     */
    public function index(): View
    {
        $procurementCards = $this->panelProcurementQuery()
            ->orderBy('title')
            ->get()
            ->map(fn (Procurement $procurement): array => $this->procurementCard($procurement))
            ->sortByDesc(fn (array $card): int => $card['latest_at']?->getTimestamp() ?? 0)
            ->values();

        $summary = [
            'procurements' => $procurementCards->count(),
            'applications' => $procurementCards->sum('application_count'),
            'reports' => $procurementCards->sum('report_count'),
            'ready' => $procurementCards->where('status', 'ready')->count(),
            'in_progress' => $procurementCards->whereIn('status', ['awaiting', 'in_progress'])->count(),
        ];

        return view('evaluations.panel.index', compact('procurementCards', 'summary'));
    }

    /**
     * One procurement's panel journey and method entry points.
     */
    public function show(Procurement $procurement): View
    {
        $procurement = $this->findVisibleProcurement($procurement);
        $card = $this->procurementCard($procurement);
        $evaluationRows = $this->evaluationRows($procurement);

        $eoiStats = null;
        $qualifiedApplicantIds = collect();

        if ($card['methods']->contains('type', Evaluation::TYPE_EOI)) {
            $eoiReport = $this->eoiQualificationService->buildProcurementReport($procurement);
            $eoiStats = $eoiReport['stats'];
            $qualifiedApplicantIds = $this->eoiReportCommunicationService
                ->qualifiedRows($eoiReport)
                ->pluck('applicant.id')
                ->filter()
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->values();
        }

        $communicationSummary = $this->communicationSummary($procurement, $card, $qualifiedApplicantIds);
        $journeySteps = $this->journeySteps(
            $procurement,
            $card,
            $eoiStats,
            $communicationSummary
        );

        return view('evaluations.panel.show', compact(
            'procurement',
            'card',
            'eoiStats',
            'communicationSummary',
            'journeySteps',
            'evaluationRows'
        ));
    }

    /**
     * Keep panel method cards stable while delegating detailed evidence to the
     * existing, method-aware report suite.
     */
    public function method(Procurement $procurement, string $method): RedirectResponse
    {
        $procurement = $this->findVisibleProcurement($procurement);
        $method = Str::lower(trim($method));

        abort_unless(in_array($method, Evaluation::MANAGED_TYPES, true), 404);
        abort_unless(request()->user()?->can('evaluations.view_all'), 403);

        $card = $this->procurementCard($procurement);
        abort_unless($card['methods']->contains('type', $method), 404);

        if ($method === Evaluation::TYPE_EOI) {
            return redirect()->route('reports.evaluations.eoi.procurement', $procurement);
        }

        return redirect()->route('reports.evaluations.method.procurement', [$method, $procurement]);
    }

    private function panelProcurementQuery(): Builder
    {
        $managedTypes = Evaluation::MANAGED_TYPES;

        $query = Procurement::query()
            ->withCount('submissions')
            ->with([
                'thinkTankPlanningItem:id,procurement_id,procurement_category,procurement_method',
                'evaluationAssignments' => fn ($assignments) => $assignments
                    ->whereHas('evaluation', fn (Builder $evaluation) => $evaluation
                        ->whereIn('type', $managedTypes))
                    ->select([
                        'id',
                        'evaluation_id',
                        'procurement_id',
                        'form_submission_id',
                        'workflow_stage',
                        'technical_proposal_round_id',
                        'user_id',
                        'status',
                        'assigned_at',
                    ]),
                'evaluationAssignments.evaluation:id,name,type,evaluation_phase,procurement_id',
                'evaluationAssignments.technicalProposalRound:id,procurement_id,round_number,title,status',
                'evaluations' => fn ($evaluations) => $evaluations
                    ->whereIn('type', $managedTypes)
                    ->select([
                        'evaluations.id',
                        'evaluations.name',
                        'evaluations.type',
                        'evaluations.evaluation_phase',
                        'evaluations.procurement_id',
                    ]),
                'directEvaluations' => fn ($evaluations) => $evaluations
                    ->whereIn('type', $managedTypes)
                    ->select(['id', 'name', 'type', 'evaluation_phase', 'procurement_id']),
                'evaluationSubmissions' => fn ($submissions) => $submissions
                    ->whereNotNull('submitted_at')
                    ->whereHas('evaluation', fn (Builder $evaluation) => $evaluation
                        ->whereIn('type', $managedTypes))
                    ->select([
                        'id',
                        'evaluation_id',
                        'procurement_id',
                        'evaluator_id',
                        'form_submission_id',
                        'overall_score',
                        'submitted_at',
                    ]),
                'evaluationSubmissions.evaluation:id,name,type,evaluation_phase,procurement_id',
            ]);

        if ($this->userHasAssignedPortfolioScope(request()->user())) {
            $this->applyAssignedPortfolioScopeToProcurements($query, request()->user());
        }

        return $query;
    }

    private function findVisibleProcurement(Procurement $procurement): Procurement
    {
        return $this->panelProcurementQuery()
            ->whereKey($procurement->getKey())
            ->firstOrFail();
    }

    private function procurementCard(Procurement $procurement): array
    {
        $assignments = $procurement->evaluationAssignments
            ->filter(fn (EvaluationAssignment $assignment): bool => $assignment->evaluation !== null)
            ->unique(fn (EvaluationAssignment $assignment): string => implode('|', [
                (string) $assignment->evaluation_id,
                (string) $assignment->user_id,
                (string) $assignment->form_submission_id,
            ]))
            ->values();
        $activeSubmissions = $this->activeSubmissions(
            $procurement->evaluationSubmissions,
            $assignments
        );
        $configuredEvaluations = collect()
            ->merge($assignments->pluck('evaluation'))
            ->merge($procurement->evaluations)
            ->merge($procurement->directEvaluations)
            ->merge($activeSubmissions->pluck('evaluation'))
            ->filter(fn ($evaluation): bool => $evaluation instanceof Evaluation
                && in_array($evaluation->type, Evaluation::MANAGED_TYPES, true))
            ->unique(fn (Evaluation $evaluation): string => (string) $evaluation->getKey())
            ->values();
        $definitions = Evaluation::configurationTypes();

        $methods = collect(Evaluation::MANAGED_TYPES)
            ->map(function (string $type) use (
                $procurement,
                $configuredEvaluations,
                $assignments,
                $activeSubmissions,
                $definitions
            ): ?array {
                $evaluations = $configuredEvaluations->where('type', $type)->values();

                if ($evaluations->isEmpty()) {
                    return null;
                }

                $evaluationIds = $evaluations->pluck('id')->map(fn ($id): string => (string) $id);
                $methodAssignments = $assignments
                    ->filter(fn (EvaluationAssignment $assignment): bool => $evaluationIds
                        ->contains((string) $assignment->evaluation_id))
                    ->values();
                $methodSubmissions = $activeSubmissions
                    ->filter(fn (EvaluationSubmission $submission): bool => $evaluationIds
                        ->contains((string) $submission->evaluation_id))
                    ->values();
                $completedAssignments = $methodAssignments
                    ->filter(fn (EvaluationAssignment $assignment): bool => $assignment->status === 'submitted')
                    ->count();
                $panelComplete = $methodAssignments->isNotEmpty()
                    && $completedAssignments === $methodAssignments->count();
                $status = match (true) {
                    $methodAssignments->isEmpty() => 'setup_required',
                    $methodSubmissions->isEmpty() => 'awaiting',
                    $panelComplete => 'ready',
                    default => 'in_progress',
                };
                $definition = $definitions[$type];
                $reportUrl = null;

                if (request()->user()?->can('evaluations.view_all')) {
                    $reportUrl = $type === Evaluation::TYPE_EOI
                        ? route('reports.evaluations.eoi.procurement', $procurement)
                        : route('reports.evaluations.method.procurement', [$type, $procurement]);
                }

                return [
                    'type' => $type,
                    'label' => $definition['label'],
                    'mode' => $definition['mode'],
                    'description' => $definition['description'],
                    'icon' => match ($type) {
                        Evaluation::TYPE_SERVICES => 'feather-bar-chart-2',
                        Evaluation::TYPE_GOODS => 'feather-package',
                        default => 'feather-user-check',
                    },
                    'templates' => $evaluations->pluck('name')->filter()->unique()->sort()->values(),
                    'phases' => $evaluations->pluck('evaluation_phase')
                        ->filter()
                        ->map(fn ($phase): string => Str::headline((string) $phase))
                        ->unique()
                        ->sort()
                        ->values(),
                    'assignment_count' => $methodAssignments->count(),
                    'completed_assignment_count' => $completedAssignments,
                    'report_count' => $methodSubmissions->count(),
                    'applicant_count' => $methodSubmissions
                        ->pluck('form_submission_id')->filter()->unique()->count(),
                    'evaluator_count' => $methodAssignments
                        ->pluck('user_id')->filter()->unique()->count(),
                    'completion_percent' => $methodAssignments->isEmpty()
                        ? 0
                        : (int) round(($completedAssignments / $methodAssignments->count()) * 100),
                    'panel_complete' => $panelComplete,
                    'status' => $status,
                    'latest_at' => $methodSubmissions->max('submitted_at')
                        ?? $methodAssignments->max('assigned_at'),
                    'method_url' => Route::has('eval.panel.method')
                        ? route('eval.panel.method', [$procurement, $type])
                        : $reportUrl,
                    'report_url' => $reportUrl,
                ];
            })
            ->filter()
            ->values();

        $status = match (true) {
            $methods->isEmpty() || $methods->every(fn (array $method): bool => $method['status'] === 'setup_required') => 'setup_required',
            $methods->every(fn (array $method): bool => $method['status'] === 'ready') => 'ready',
            $methods->contains(fn (array $method): bool => in_array($method['status'], ['in_progress', 'ready'], true)) => 'in_progress',
            default => 'awaiting',
        };
        $latestAt = $activeSubmissions->max('submitted_at') ?? $assignments->max('assigned_at');
        $title = $procurement->title ?: 'Untitled procurement';
        $search = Str::lower(collect([
            $title,
            $procurement->reference_no,
            $procurement->status,
            $procurement->thinkTankPlanningItem?->procurement_category,
            $procurement->thinkTankPlanningItem?->procurement_method,
        ])->merge($methods->pluck('label'))->merge($methods->flatMap->templates)->filter()->implode(' '));

        return [
            'procurement' => $procurement,
            'methods' => $methods,
            'method_types' => $methods->pluck('type')->values(),
            'application_count' => (int) $procurement->submissions_count,
            'assignment_count' => $assignments->count(),
            'evaluator_count' => $assignments->pluck('user_id')->filter()->unique()->count(),
            'report_count' => $activeSubmissions->count(),
            'evaluated_applicant_count' => $activeSubmissions
                ->pluck('form_submission_id')->filter()->unique()->count(),
            'status' => $status,
            'completion_percent' => $methods->isEmpty()
                ? 0
                : (int) round($methods->avg('completion_percent')),
            'latest_at' => $latestAt,
            'search' => $search,
            'show_url' => Route::has('eval.panel.procurement')
                ? route('eval.panel.procurement', $procurement)
                : '#',
        ];
    }

    private function activeSubmissions(Collection $submissions, Collection $assignments): Collection
    {
        return $submissions
            ->filter(function (EvaluationSubmission $submission) use ($assignments): bool {
                if (! $submission->submitted_at) {
                    return false;
                }

                return $assignments->contains(
                    fn (EvaluationAssignment $assignment): bool => (string) $assignment->procurement_id === (string) $submission->procurement_id
                        && (string) $assignment->evaluation_id === (string) $submission->evaluation_id
                        && (string) $assignment->user_id === (string) $submission->evaluator_id
                        && (blank($assignment->form_submission_id)
                            || (string) $assignment->form_submission_id === (string) $submission->form_submission_id)
                );
            })
            ->unique(fn (EvaluationSubmission $submission): string => implode('|', [
                (string) $submission->evaluation_id,
                (string) $submission->evaluator_id,
                (string) $submission->form_submission_id,
            ]))
            ->values();
    }

    private function evaluationRows(Procurement $procurement): Collection
    {
        $currentUser = request()->user();
        $isSystemAdministrator = $currentUser
            && ($currentUser->isAdmin() || $currentUser->isSuperAdmin());
        $assignments = $procurement->evaluationAssignments
            ->filter(fn (EvaluationAssignment $assignment): bool => $assignment->evaluation !== null)
            ->values();
        $hasProposalRound = EoiTechnicalProposalRound::query()
            ->where('procurement_id', $procurement->getKey())
            ->where('status', '!=', EoiTechnicalProposalRound::STATUS_CANCELLED)
            ->exists();
        $releasedEoiApplicantIds = EoiReportCommunicationRecipient::query()
            ->whereHas('communication', fn (Builder $query) => $query
                ->where('procurement_id', $procurement->getKey())
                ->where('type', EoiReportCommunication::TYPE_EVALUATION_RECORDS))
            ->where(function (Builder $query): void {
                $query->whereNotNull('record_file_path')
                    ->orWhereNotNull('emailed_at')
                    ->orWhereIn('delivery_status', [
                        EoiReportCommunicationRecipient::STATUS_PENDING,
                        EoiReportCommunicationRecipient::STATUS_PROCESSING,
                        EoiReportCommunicationRecipient::STATUS_SENT,
                    ]);
            })
            ->pluck('form_submission_id')
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->flip();
        $hasFinalDownstreamDecision = filled($procurement->awarded_submission_id)
            || filled($procurement->awarded_at)
            || $procurement->contractNegotiations()->exists()
            || $procurement->purchaseOrders()->exists();

        return EvaluationSubmission::query()
            ->where('procurement_id', $procurement->getKey())
            ->where(function (Builder $query): void {
                $query->whereNotNull('submitted_at')
                    ->orWhereHas('reworkRequests', fn (Builder $reworkQuery) => $reworkQuery
                        ->where('status', ReworkRequest::STATUS_PENDING));
            })
            ->whereHas('evaluation', fn (Builder $query) => $query
                ->whereIn('type', Evaluation::MANAGED_TYPES))
            ->with([
                'applicant.submitter:id,name,email',
                'applicant.values' => fn ($values) => $values
                    ->whereIn('field_key', ['official_name', 'consortium_name', 'think_tank_name'])
                    ->select(['id', 'submission_id', 'field_key', 'value']),
                'criteriaScores:id,submission_id,evaluation_criteria_id,score,decision',
                'evaluation:id,name,type,evaluation_phase',
                'evaluator:id,name,email',
                'openReworkRequest.requester:id,name',
                'latestCompletedReworkRequest' => fn ($query) => $query->select([
                    'id',
                    'evaluation_submission_id',
                    'requested_by',
                    'cycle',
                    'requested_at',
                    'completed_at',
                    'source_revision_number',
                    'completed_revision_number',
                    'notified_at',
                    'notification_error',
                ]),
                'latestCompletedReworkRequest.requester:id,name',
            ])
            ->withCount([
                'reworkRequests as completed_rework_count' => fn ($query) => $query
                    ->where('status', ReworkRequest::STATUS_COMPLETED),
            ])
            ->latest('updated_at')
            ->get()
            ->map(function (EvaluationSubmission $submission) use (
                $assignments,
                $hasFinalDownstreamDecision,
                $hasProposalRound,
                $currentUser,
                $isSystemAdministrator,
                $procurement,
                $releasedEoiApplicantIds
            ): ?array {
                $assignment = $assignments->first(
                    fn (EvaluationAssignment $candidate): bool => filled($submission->evaluation_assignment_id)
                        && (string) $candidate->getKey() === (string) $submission->evaluation_assignment_id
                );

                if (! $assignment) {
                    $assignment = $assignments->first(
                        fn (EvaluationAssignment $candidate): bool => ! $candidate->isTechnicalProposal()
                            && (string) $candidate->evaluation_id === (string) $submission->evaluation_id
                            && (string) $candidate->user_id === (string) $submission->evaluator_id
                            && (blank($candidate->form_submission_id)
                                || (string) $candidate->form_submission_id === (string) $submission->form_submission_id)
                    );
                }

                if (! $assignment || ! $submission->evaluation) {
                    return null;
                }

                $openRework = $submission->openReworkRequest;
                $status = match (true) {
                    filled($submission->submitted_at) => 'submitted',
                    $openRework !== null => 'rework',
                    default => 'draft',
                };
                $blockingReason = null;
                $requiresProposalRoundOverride = false;

                if ($hasFinalDownstreamDecision) {
                    $blockingReason = 'Locked because an award or contracting process has started.';
                } elseif ($assignment->isApplicationStage()
                    && $submission->evaluation->isEoi()
                    && $releasedEoiApplicantIds->has((string) $submission->form_submission_id)) {
                    $blockingReason = 'Locked because this applicant evaluation record has been released.';
                } elseif ($assignment->isApplicationStage()
                    && $submission->evaluation->isEoi()
                    && $hasProposalRound) {
                    $blockingReason = 'Locked because a technical-proposal round has been prepared.';
                    $requiresProposalRoundOverride = true;
                }

                $canOverrideProposalRoundLock = $requiresProposalRoundOverride
                    && $isSystemAdministrator;

                $result = $submission->evaluation->usesNumericScoring()
                    ? ($submission->overall_score !== null
                        ? number_format((float) $submission->overall_score, 2).' points'
                        : 'Score pending')
                    : $this->categoricalResult($submission);
                $technicalProposalRound = $assignment->technicalProposalRound;
                $workflowLabel = $assignment->isTechnicalProposalStage()
                    ? 'Technical proposal evaluation'
                    : 'Application evaluation';
                $workflowRoundLabel = $assignment->isTechnicalProposalStage()
                    ? ($technicalProposalRound
                        ? 'Round '.number_format((int) $technicalProposalRound->round_number)
                            .(filled($technicalProposalRound->title) ? ': '.$technicalProposalRound->title : '')
                        : 'Technical proposal round')
                    : null;

                return [
                    'submission' => $submission,
                    'assignment' => $assignment,
                    'evaluation' => $submission->evaluation,
                    'evaluator' => $submission->evaluator,
                    'applicant' => $submission->applicant,
                    'status' => $status,
                    'status_label' => match ($status) {
                        'submitted' => 'Submitted',
                        'rework' => 'Rework requested',
                        default => 'Draft in progress',
                    },
                    'result' => $result,
                    'workflow_label' => $workflowLabel,
                    'workflow_round_label' => $workflowRoundLabel,
                    'workflow_context' => $workflowRoundLabel
                        ? $workflowLabel.' - '.$workflowRoundLabel
                        : $workflowLabel,
                    'open_rework' => $openRework,
                    'completed_rework_count' => (int) $submission->completed_rework_count,
                    'latest_completed_rework' => $submission->latestCompletedReworkRequest,
                    'blocking_reason' => $blockingReason,
                    'requires_proposal_round_override' => $requiresProposalRoundOverride,
                    'can_override_proposal_round_lock' => $canOverrideProposalRoundLock,
                    'can_request_rework' => $currentUser?->can('evaluations.manage')
                        && $status === 'submitted'
                        && ($blockingReason === null || $canOverrideProposalRoundLock),
                    'view_url' => $status === 'submitted'
                        ? route('reports.evaluations.submission', $submission)
                        : null,
                    'rework_url' => route('eval.panel.rework', [$procurement, $submission]),
                    'activity_at' => $submission->submitted_at
                        ?? $openRework?->requested_at
                        ?? $submission->updated_at,
                ];
            })
            ->filter()
            ->sortBy(fn (array $row): string => implode('|', [
                match ($row['status']) {
                    'rework' => '0',
                    'submitted' => '1',
                    default => '2',
                },
                str_pad((string) (PHP_INT_MAX - ($row['activity_at']?->getTimestamp() ?? 0)), 20, '0', STR_PAD_LEFT),
            ]))
            ->values();
    }

    private function categoricalResult(EvaluationSubmission $submission): string
    {
        $decisions = $submission->criteriaScores
            ->pluck('decision')
            ->filter(fn ($decision): bool => $decision !== null && $decision !== '')
            ->countBy()
            ->map(function (int $count, int|string $decision) use ($submission): string {
                $label = $submission->evaluation?->decisionLabel($decision)
                    ?? Str::headline((string) $decision);

                return number_format($count).' '.$label;
            })
            ->values();

        return $decisions->isEmpty()
            ? 'Decisions pending'
            : $decisions->implode(' · ');
    }

    private function communicationSummary(
        Procurement $procurement,
        array $card,
        Collection $qualifiedApplicantIds
    ): array {
        if (! $card['methods']->contains('type', Evaluation::TYPE_EOI)) {
            return [
                'has_proposal_round' => false,
                'evaluation_record_batches' => 0,
                'proposal_invitation_batches' => 0,
                'initiated_without_notification' => false,
                'current_qualified_applicants' => 0,
                'qualified_shortlist_enrolled' => true,
                'proposal_candidates' => 0,
                'notified_qualified' => 0,
                'offline_candidates' => 0,
                'failed_candidates' => 0,
                'proposal_respondents' => 0,
                'proposal_documents' => 0,
                'latest_at' => null,
            ];
        }

        $communications = EoiReportCommunication::query()
            ->where('procurement_id', $procurement->getKey())
            ->with([
                'recipients:id,communication_id,form_submission_id,delivery_status,proposal_submitted_at',
                'recipients.proposalDocuments:id,recipient_id',
            ])
            ->orderByDesc('sent_at')
            ->get(['id', 'procurement_id', 'technical_proposal_round_id', 'type', 'sent_at']);
        $proposalInvitations = $communications
            ->where('type', EoiReportCommunication::TYPE_PROPOSAL_INVITATION);
        $latestRound = EoiTechnicalProposalRound::query()
            ->where('procurement_id', $procurement->getKey())
            ->whereIn('status', [
                EoiTechnicalProposalRound::STATUS_PUBLISHED,
                EoiTechnicalProposalRound::STATUS_CLOSED,
            ])
            ->with('candidates.submissions.documents')
            ->orderByDesc('round_number')
            ->first();
        $roundInvitations = $latestRound
            ? $proposalInvitations->where('technical_proposal_round_id', $latestRound->getKey())
            : $proposalInvitations;
        $initiatedWithoutNotification = $latestRound !== null
            && $roundInvitations->isEmpty()
            && ProcurementAuditLog::query()
                ->where('procurement_id', $procurement->getKey())
                ->where('action', 'technical_proposal_initiated_without_notification')
                ->get(['metadata'])
                ->contains(fn (ProcurementAuditLog $audit): bool => (string) data_get($audit->metadata, 'round_id') === (string) $latestRound->getKey()
                );
        $roundRecipients = $roundInvitations->flatMap->recipients;
        $sentRecipients = $roundRecipients
            ->where('delivery_status', EoiReportCommunicationRecipient::STATUS_SENT);
        $skippedRecipients = $roundRecipients
            ->where('delivery_status', EoiReportCommunicationRecipient::STATUS_SKIPPED);
        $failedRecipients = $roundRecipients
            ->where('delivery_status', EoiReportCommunicationRecipient::STATUS_FAILED);
        $legacyRespondents = $sentRecipients
            ->filter(fn (EoiReportCommunicationRecipient $recipient): bool => $recipient->proposal_submitted_at !== null || $recipient->proposalDocuments->isNotEmpty());
        $proposalCandidates = $latestRound?->candidates ?? collect();
        $proposalCandidateIds = $proposalCandidates
            ->pluck('form_submission_id')
            ->filter()
            ->map(fn ($id): string => (string) $id)
            ->unique();
        $enrolledApplicantIds = $latestRound
            ? $proposalCandidateIds
            : $roundRecipients
                ->pluck('form_submission_id')
                ->filter()
                ->map(fn ($id): string => (string) $id)
                ->unique();
        $qualifiedShortlistEnrolled = $qualifiedApplicantIds
            ->diff($enrolledApplicantIds)
            ->isEmpty();
        $respondents = $latestRound
            ? $proposalCandidates->filter(fn ($candidate): bool => $candidate->submissions->isNotEmpty())
            : $legacyRespondents;
        $proposalDocuments = $latestRound
            ? $respondents->sum(fn ($candidate): int => $candidate->submissions
                ->sum(fn ($submission): int => $submission->documents->count()))
            : $legacyRespondents->sum(
                fn (EoiReportCommunicationRecipient $recipient): int => $recipient->proposalDocuments->count()
            );
        $latestAt = $communications->max('sent_at');
        $latestSubmissionAt = $latestRound?->candidates->max('last_submitted_at');

        if ($latestRound?->published_at && (! $latestAt || $latestRound->published_at->greaterThan($latestAt))) {
            $latestAt = $latestRound->published_at;
        }

        if ($latestSubmissionAt && (! $latestAt || $latestSubmissionAt->greaterThan($latestAt))) {
            $latestAt = $latestSubmissionAt;
        }

        return [
            'has_proposal_round' => $latestRound !== null,
            'evaluation_record_batches' => $communications
                ->where('type', EoiReportCommunication::TYPE_EVALUATION_RECORDS)->count(),
            'proposal_invitation_batches' => $roundInvitations->count(),
            'initiated_without_notification' => $initiatedWithoutNotification,
            'current_qualified_applicants' => $qualifiedApplicantIds->count(),
            'qualified_shortlist_enrolled' => $qualifiedShortlistEnrolled,
            'proposal_candidates' => $latestRound
                ? $proposalCandidates->count()
                : $roundRecipients->pluck('form_submission_id')->filter()->unique()->count(),
            'notified_qualified' => $sentRecipients
                ->pluck('form_submission_id')->filter()->unique()->count(),
            'offline_candidates' => $initiatedWithoutNotification
                ? $proposalCandidates->count()
                : $skippedRecipients->pluck('form_submission_id')->filter()->unique()->count(),
            'failed_candidates' => $failedRecipients
                ->pluck('form_submission_id')->filter()->unique()->count(),
            'proposal_respondents' => $latestRound
                ? $respondents->count()
                : $legacyRespondents->pluck('form_submission_id')->filter()->unique()->count(),
            'proposal_documents' => $proposalDocuments,
            'latest_at' => $latestAt,
        ];
    }

    private function journeySteps(
        Procurement $procurement,
        array $card,
        ?array $eoiStats,
        array $communicationSummary
    ): Collection {
        $publicationComplete = (int) ($procurement->publication_version ?? 0) > 0
            || in_array($procurement->status, ['published', 'closed'], true)
            || filled($procurement->awarded_submission_id);
        $intakeComplete = $card['application_count'] > 0 && (
            filled($procurement->awarded_submission_id)
            || $card['assignment_count'] > 0
            || $card['report_count'] > 0
            || in_array($procurement->status, ['closed'], true)
            || ($procurement->application_end_date?->isPast() ?? false)
        );
        $hasEoi = $card['methods']->contains('type', Evaluation::TYPE_EOI);
        $eoiMethod = $card['methods']->firstWhere('type', Evaluation::TYPE_EOI);
        $otherMethods = $card['methods']->where('type', '!=', Evaluation::TYPE_EOI)->values();

        $steps = collect([
            [
                'key' => 'publication',
                'label' => 'Procurement published',
                'detail' => $publicationComplete
                    ? 'The opportunity is available in the procurement workflow.'
                    : 'Publication must be completed before applicant intake can close.',
                'meta' => Str::headline((string) ($procurement->status ?: 'Draft')),
                'icon' => 'feather-send',
                'complete' => $publicationComplete,
            ],
            [
                'key' => 'applications',
                'label' => 'Applications received',
                'detail' => number_format($card['application_count']).' application(s) are linked to this procurement.',
                'meta' => $procurement->application_end_date
                    ? 'Closes '.$procurement->application_end_date->format('d M Y')
                    : 'Closing date not set',
                'icon' => 'feather-inbox',
                'complete' => $intakeComplete,
            ],
        ]);

        if ($hasEoi) {
            $eoiPanelComplete = (bool) ($eoiStats
                && ($eoiStats['total_applicants'] ?? 0) > 0
                && ($eoiStats['panel_incomplete'] ?? 0) === 0
                && ($eoiStats['submitted_evaluations'] ?? 0) > 0);
            $qualified = (int) ($eoiStats['advance'] ?? 0);
            $notified = (int) $communicationSummary['notified_qualified'];
            $offline = (int) $communicationSummary['offline_candidates'];
            $failed = (int) $communicationSummary['failed_candidates'];
            $responded = (int) $communicationSummary['proposal_respondents'];
            $initiatedWithoutNotification = (bool) $communicationSummary['initiated_without_notification'];
            $qualifiedForProposal = (int) $communicationSummary['current_qualified_applicants'];
            $qualifiedShortlistEnrolled = (bool) $communicationSummary['qualified_shortlist_enrolled'];
            $proposalCandidates = $communicationSummary['has_proposal_round']
                ? (int) $communicationSummary['proposal_candidates']
                : $qualified;
            $notificationComplete = $eoiPanelComplete
                && ($qualifiedForProposal === 0
                    || ($qualifiedShortlistEnrolled && $proposalCandidates > 0 && (
                        $initiatedWithoutNotification
                        || (
                            $communicationSummary['proposal_invitation_batches'] > 0
                            && $failed === 0
                            && ($notified + $offline) >= $qualifiedForProposal
                        )
                    )));
            $proposalIntakeComplete = $notificationComplete
                && ($proposalCandidates === 0 || $responded >= $proposalCandidates);
            $proposalReportUrl = route('reports.evaluations.eoi.procurement', $procurement);
            $proposalAdminActions = $communicationSummary['has_proposal_round']
                ? [
                    [
                        'label' => 'Review proposal rules',
                        'url' => $proposalReportUrl.'#technicalProposalWorkspace',
                        'icon' => 'feather-shield',
                        'style' => 'outline',
                        'disabled' => false,
                    ],
                    [
                        'label' => 'Upload for an applicant',
                        'url' => $proposalReportUrl.'?admin_upload=1#technicalProposalWorkspace',
                        'icon' => 'feather-upload-cloud',
                        'style' => 'primary',
                        'disabled' => $proposalCandidates === 0,
                    ],
                ]
                : [
                    [
                        'label' => 'Set rules & notify applicants',
                        'url' => $proposalReportUrl.'?compose_proposal=1#eoiCommunicationsTitle',
                        'icon' => 'feather-sliders',
                        'style' => 'primary',
                        'disabled' => ! $eoiPanelComplete || $qualified === 0,
                    ],
                    [
                        'label' => 'Upload for an applicant',
                        'url' => null,
                        'icon' => 'feather-upload-cloud',
                        'style' => 'outline',
                        'disabled' => true,
                    ],
                ];
            $proposalAdminNote = $communicationSummary['has_proposal_round']
                ? ($initiatedWithoutNotification
                    ? 'This round was initiated without email or portal notification. Select an enrolled applicant in the proposal workspace to capture one or several documents on their behalf.'
                    : 'Published rules remain read-only for audit integrity. Select an enrolled applicant in the proposal workspace to capture one or several documents on their behalf.')
                : ($eoiPanelComplete && $qualified > 0
                    ? 'Define the rules, submission channels, deadline and templates, then choose whether to notify applicants or initiate the stage for administrator-managed upload only.'
                    : 'Rule setup becomes available when the EOI panel has a final Qualified Applicant.');

            $steps->push(
                [
                    'key' => 'eoi_assignment',
                    'label' => 'EOI panel assigned',
                    'detail' => number_format($eoiMethod['evaluator_count'] ?? 0).' active panel member(s) across '.number_format($eoiMethod['assignment_count'] ?? 0).' assignment(s).',
                    'meta' => number_format($eoiMethod['completion_percent'] ?? 0).'% assignment completion',
                    'icon' => 'feather-users',
                    'complete' => ($eoiMethod['assignment_count'] ?? 0) > 0,
                ],
                [
                    'key' => 'eoi_evaluation',
                    'label' => 'EOI qualification completed',
                    'detail' => $eoiPanelComplete
                        ? number_format($qualified).' applicant(s) qualify to advance.'
                        : number_format($eoiStats['panel_incomplete'] ?? $card['application_count']).' applicant panel(s) still need a final task.',
                    'meta' => number_format($eoiStats['submitted_evaluations'] ?? 0).' active reports',
                    'icon' => 'feather-user-check',
                    'complete' => $eoiPanelComplete,
                ],
                [
                    'key' => 'qualified_notification',
                    'label' => 'Qualified applicants notified or enrolled',
                    'detail' => $proposalCandidates === 0 && $eoiPanelComplete
                        ? 'No qualified applicant requires a technical proposal invitation.'
                        : ($initiatedWithoutNotification
                            ? number_format($proposalCandidates).' qualified applicant(s) enrolled for administrator-managed proposal intake. No email or portal notification was sent.'
                            : number_format($notified).' emailed; '.number_format($offline).' offline applicant(s) enrolled for admin capture; '.number_format($failed).' delivery failure(s).'),
                    'meta' => $initiatedWithoutNotification
                        ? 'Technical proposal round initiated without notification'
                        : number_format($communicationSummary['proposal_invitation_batches']).' invitation batch(es) for '.number_format($proposalCandidates).' applicant(s)',
                    'icon' => $initiatedWithoutNotification ? 'feather-user-plus' : 'feather-mail',
                    'complete' => $notificationComplete,
                    'actions' => $proposalAdminActions,
                    'action_note' => $proposalAdminNote,
                ],
                [
                    'key' => 'technical_proposals',
                    'label' => 'Technical proposals received',
                    'detail' => number_format($responded).' of '.number_format($proposalCandidates).' enrolled applicant(s) have submitted '.number_format($communicationSummary['proposal_documents']).' document(s).',
                    'meta' => $proposalCandidates > 0 ? number_format($responded).' / '.number_format($proposalCandidates).' responses' : 'Awaiting proposal intake',
                    'icon' => 'feather-upload-cloud',
                    'complete' => $proposalIntakeComplete,
                ]
            );

            if ($otherMethods->isNotEmpty()) {
                $steps->push([
                    'key' => 'next_evaluation',
                    'label' => 'Next evaluation stage',
                    'detail' => $otherMethods->pluck('label')->implode(' and ').' evaluation follows technical proposal intake.',
                    'meta' => $otherMethods->where('status', 'ready')->count().' / '.$otherMethods->count().' method(s) ready',
                    'icon' => 'feather-layers',
                    'complete' => $otherMethods->every(fn (array $method): bool => $method['status'] === 'ready'),
                ]);
            }
        } else {
            if ($card['methods']->isEmpty()) {
                $steps->push([
                    'key' => 'evaluation_setup',
                    'label' => 'Evaluation method setup',
                    'detail' => 'Configure a Services, Goods, or EOI evaluation before assigning the panel.',
                    'meta' => 'No managed evaluation is linked yet',
                    'icon' => 'feather-settings',
                    'complete' => false,
                ]);
            } else {
                $steps->push(
                    [
                        'key' => 'panel_assignment',
                        'label' => 'Panel assigned',
                        'detail' => number_format($card['evaluator_count']).' active evaluator(s) are assigned.',
                        'meta' => number_format($card['assignment_count']).' assignment(s)',
                        'icon' => 'feather-users',
                        'complete' => $card['assignment_count'] > 0,
                    ],
                    [
                        'key' => 'panel_evaluation',
                        'label' => 'Panel evaluation completed',
                        'detail' => number_format($card['report_count']).' active report(s) cover '.number_format($card['evaluated_applicant_count']).' applicant(s).',
                        'meta' => number_format($card['completion_percent']).'% assignment completion',
                        'icon' => 'feather-check-circle',
                        'complete' => $card['status'] === 'ready',
                    ]
                );
            }
        }

        $steps->push([
            'key' => 'award',
            'label' => 'Award decision',
            'detail' => filled($procurement->awarded_submission_id)
                ? 'The selected submission has been recorded for this procurement.'
                : 'The award decision follows completion of every required evaluation stage.',
            'meta' => $procurement->awarded_at?->format('d M Y') ?? 'Not awarded yet',
            'icon' => 'feather-award',
            'complete' => filled($procurement->awarded_submission_id),
        ]);

        $currentFound = false;

        return $steps->map(function (array $step) use (&$currentFound): array {
            if ($step['complete']) {
                $step['state'] = 'complete';
            } elseif (! $currentFound) {
                $step['state'] = 'current';
                $currentFound = true;
            } else {
                $step['state'] = 'upcoming';
            }

            unset($step['complete']);

            return $step;
        })->values();
    }
}
