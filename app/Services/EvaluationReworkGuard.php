<?php

namespace App\Services;

use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ReworkRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class EvaluationReworkGuard
{
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
