<?php

namespace App\Services;

use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ReworkRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class EvaluationReworkGuard
{
    /** Never replace an evaluation awaiting correction with an older finalized row. */
    public function excludePendingTasks(iterable $submissions): Collection
    {
        $records = collect($submissions);
        if ($records->isEmpty()) {
            return $records;
        }
        $pending = ReworkRequest::query()->where('status', ReworkRequest::STATUS_PENDING)
            ->whereIn('procurement_id', $records->pluck('procurement_id')->filter()->unique())
            ->whereIn('evaluation_id', $records->pluck('evaluation_id')->filter()->unique())
            ->with(['submission.assignment', 'submission.technicalProposalCandidate'])->get();
        if ($pending->isEmpty()) {
            return $records;
        }
        $keys = $pending->map(function (ReworkRequest $rework): string {
            if (! data_get($rework->source_snapshot, 'assignment.workflow_stage')) {
                return $rework->submission ? $this->taskKey($rework->submission) : '';
            }

            return implode('|', [
                (string) $rework->procurement_id, (string) $rework->evaluation_id,
                (string) $rework->evaluator_id, (string) $rework->form_submission_id,
                data_get($rework->source_snapshot, 'assignment.workflow_stage'),
                data_get($rework->source_snapshot, 'assignment.technical_proposal_round_id')
                    ?? data_get($rework->source_snapshot, 'technical_proposal.round.id') ?? 'application',
            ]);
        })->filter()->flip();
        // Some report summaries select only six columns. Resolve their stage/round
        // from the source row instead of assuming a missing foreign key is application-stage.
        $contexts = EvaluationSubmission::query()->whereIn('id', $records->pluck('id')->filter())
            ->with(['assignment', 'technicalProposalCandidate'])->get()->keyBy('id');

        return $records->filter(fn ($record) => ! $record->isSubmitted()
            || ! $keys->has($this->taskKey($contexts->get($record->id, $record))))->values();
    }

    public function taskKey(EvaluationSubmission $submission): string
    {
        return implode('|', [
            (string) $submission->procurement_id, (string) $submission->evaluation_id,
            (string) $submission->evaluator_id, (string) $submission->form_submission_id,
            $submission->isTechnicalProposalEvaluation() ? 'technical_proposal' : 'application',
            (string) ($submission->assignment?->technical_proposal_round_id ?? $submission->technicalProposalCandidate?->round_id ?? 'application'),
        ]);
    }

    public function lockForDownstreamTransition(Procurement|string $procurement): Procurement
    {
        return $this->lockAndAssertNoPendingRework(
            $procurement,
            'Complete or resolve all pending evaluation rework before starting an award, contract, or purchase-order workflow.'
        );
    }

    public function lockAndAssertNoPendingRework(
        Procurement|string $procurement,
        string $message
    ): Procurement {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('A transaction is required before locking a procurement workflow.');
        }

        $procurementId = $procurement instanceof Procurement
            ? $procurement->getKey()
            : $procurement;
        $lockedProcurement = Procurement::query()
            ->withTrashed()
            ->whereKey($procurementId)
            ->lockForUpdate()
            ->firstOrFail();

        if ($this->procurementHasPendingRework($lockedProcurement)) {
            throw ValidationException::withMessages([
                'procurement' => $message,
            ]);
        }

        return $lockedProcurement;
    }

    public function assertApplicantStatusCanChange(FormSubmission $applicant): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('A transaction is required before changing an evaluation applicant status.');
        }

        if (ReworkRequest::query()
            ->where('form_submission_id', $applicant->getKey())
            ->where('status', ReworkRequest::STATUS_PENDING)
            ->exists()) {
            throw ValidationException::withMessages([
                'status' => 'This application has an evaluation awaiting rework and cannot move to an ineligible status yet.',
            ]);
        }
    }

    public function assertTechnicalProposalCanContinue(FormSubmission $applicant): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('A transaction is required before continuing a technical-proposal workflow.');
        }

        if (ReworkRequest::query()
            ->where('form_submission_id', $applicant->getKey())
            ->where('status', ReworkRequest::STATUS_PENDING)
            ->exists()) {
            throw ValidationException::withMessages([
                'proposal' => 'This applicant has an EOI evaluation awaiting rework. Proposal uploads and compliance decisions are paused until the evaluator resubmits.',
            ]);
        }

        if (in_array($applicant->status, [
            FormSubmission::STATUS_EOI_NOT_QUALIFIED,
            FormSubmission::STATUS_TECHNICAL_PROPOSAL_DISQUALIFIED,
            FormSubmission::STATUS_WITHDRAWN,
        ], true)) {
            throw ValidationException::withMessages([
                'proposal' => 'This applicant does not currently have a valid EOI qualification for the technical-proposal stage.',
            ]);
        }
    }

    public function procurementHasPendingRework(Procurement|string $procurement): bool
    {
        $procurementId = $procurement instanceof Procurement
            ? $procurement->getKey()
            : $procurement;

        return ReworkRequest::query()
            ->where('procurement_id', $procurementId)
            ->where('status', ReworkRequest::STATUS_PENDING)
            ->exists();
    }
}
