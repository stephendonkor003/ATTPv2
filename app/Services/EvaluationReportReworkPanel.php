<?php

namespace App\Services;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\EoiReportCommunication;
use App\Models\EoiReportCommunicationRecipient;
use App\Models\EoiTechnicalProposalRound;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\Procurement;
use App\Models\ReworkRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** Operational report controls, deliberately separate from exported report data. */
class EvaluationReportReworkPanel
{
    use ScopesAssignedPortfolios;

    public function forProcurements(iterable $procurements, ?string $method = null, ?EvaluationSubmission $onlySubmission = null): array
    {
        $panel = ['groups' => [], 'available_count' => 0, 'pending_count' => 0];
        $user = request()->user();
        if (! $user?->can('evaluations.manage') || ! $user->can('evaluations.view_all')) {
            return $panel;
        }

        foreach (collect($procurements)->unique(fn ($p) => (string) $p->id) as $procurement) {
            if ($this->userHasAssignedPortfolioScope($user)
                && ! $this->procurementIsInAssignedPortfolio($procurement, $user)) {
                continue;
            }
            $procurement->loadMissing(['evaluationAssignments.evaluation', 'evaluationAssignments.technicalProposalRound']);
            $rows = $this->rows($procurement)->filter(fn ($row) => (! $method || $row['evaluation']->type === $method)
                && (! $onlySubmission || (string) $row['submission']->id === (string) $onlySubmission->id));
            foreach ($rows->groupBy(fn ($row) => (string) $row['submission']->evaluator_id) as $evaluatorId => $evaluatorRows) {
                $evaluator = $evaluatorRows->first()['evaluator'];
                $entries = $evaluatorRows->map(fn ($row) => [
                    'id' => (string) $row['submission']->id,
                    'applicant' => $row['applicant']?->display_name ?? 'Applicant unavailable',
                    'evaluation' => $row['evaluation']->name,
                    'phase' => $row['workflow_context'].(filled($row['evaluation']->evaluation_phase) ? ' / '.Str::headline($row['evaluation']->evaluation_phase) : ''),
                    'submitted_at' => $row['submission']->submitted_at?->format('d M Y H:i') ?? '',
                    'can_request' => (bool) $row['can_request_rework'],
                    'status' => $row['status'] === 'rework' ? 'rework' : ($row['can_request_rework'] ? 'submitted' : 'blocked'),
                    'reason' => $row['open_rework']?->reason,
                    'blocking_reason' => $row['blocking_reason'],
                    'requires_override' => (bool) $row['requires_proposal_round_override'],
                    'view_url' => $row['view_url'],
                ])->values()->all();
                $panel['groups'][] = [
                    'id' => (string) $procurement->id.'|'.$evaluatorId,
                    'evaluator_id' => (string) $evaluatorId,
                    'name' => $evaluator?->name ?? 'Evaluator unavailable',
                    'email' => $evaluator?->email ?? '',
                    'procurement' => $procurement->title,
                    'procurement_id' => (string) $procurement->id,
                    'action_url' => route('eval.panel.rework.batch', $procurement),
                    'return_to' => $onlySubmission
                        ? route('reports.evaluations.procurement', $procurement, false)
                        : request()->getRequestUri(),
                    'entries' => $entries,
                ];
                $panel['available_count'] += collect($entries)->where('can_request', true)->count();
                $panel['pending_count'] += collect($entries)->where('status', 'rework')->count();
            }
        }

        return $panel;
    }

    /** Current record for each form, evaluator, applicant, stage and round, including unfinished work. */
    public function currentSubmissions(Procurement $procurement, ?string $evaluatorId = null, bool $lock = false): Collection
    {
        $query = EvaluationSubmission::query()->where('procurement_id', $procurement->id)
            ->when($evaluatorId, fn ($q) => $q->where('evaluator_id', $evaluatorId))
            ->with(['assignment', 'technicalProposalCandidate']);
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->sortByDesc(fn ($s) => implode('|', [
            ($s->submitted_at ?? $s->updated_at ?? $s->created_at)?->format('Y-m-d H:i:s.u') ?? '',
            str_pad((string) $s->revision_number, 8, '0', STR_PAD_LEFT),
            (string) $s->id,
        ]))->unique(fn ($s) => $this->contextKey($s))->values();
    }

    private function contextKey(EvaluationSubmission $submission): string
    {
        return app(EvaluationReworkGuard::class)->taskKey($submission);
    }

    public function rows(Procurement $procurement): Collection
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

        $currentIds = $this->currentSubmissions($procurement)->pluck('id');

        return EvaluationSubmission::query()
            ->where(fn (Builder $q) => $q->whereIn('id', $currentIds)
                ->orWhereHas('openReworkRequest'))
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
                    $openRework !== null => 'rework',
                    filled($submission->submitted_at) => 'submitted',
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
}
