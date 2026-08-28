<?php

namespace App\Services;

use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalRound;
use App\Models\EoiTechnicalProposalSubmission;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\FormSubmission;
use App\Models\Procurement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class EvaluationAssignmentTargetResolver
{
    /**
     * The current published/closed proposal round. It is returned even while
     * its compliance shortlist is empty so the UI cannot fall back to a stale
     * older round and can explain that qualification is still pending.
     */
    public function latestAssignableRound(Procurement $procurement): ?EoiTechnicalProposalRound
    {
        if ($procurement->relationLoaded('technicalProposalRounds')) {
            return $procurement->technicalProposalRounds
                ->filter(fn (EoiTechnicalProposalRound $round): bool => in_array(
                    $round->status,
                    $this->assignableRoundStatuses(),
                    true
                ))
                ->sortByDesc('round_number')
                ->first();
        }

        return EoiTechnicalProposalRound::query()
            ->where('procurement_id', $procurement->getKey())
            ->whereIn('status', $this->assignableRoundStatuses())
            ->orderByDesc('round_number')
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return Collection<int, array{round: EoiTechnicalProposalRound, eligible_count: int}>
     */
    public function technicalProposalOptions(Procurement $procurement): Collection
    {
        return EoiTechnicalProposalRound::query()
            ->where('procurement_id', $procurement->getKey())
            ->whereIn('status', $this->assignableRoundStatuses())
            ->orderByDesc('round_number')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (EoiTechnicalProposalRound $round) use ($procurement): array {
                return [
                    'round' => $round,
                    'eligible_count' => $this->eligibleTechnicalCandidateQuery($procurement, $round)->count(),
                ];
            })
            ->filter(fn (array $option): bool => $option['eligible_count'] > 0)
            ->values();
    }

    /**
     * @return array{
     *   round: ?EoiTechnicalProposalRound,
     *   targets: Collection<int, FormSubmission>,
     *   eligible_count: int
     * }
     */
    public function technicalProposalContext(
        Procurement $procurement,
        ?EoiTechnicalProposalRound $round = null
    ): array {
        $round ??= $this->latestAssignableRound($procurement);

        if (! $round) {
            return [
                'round' => null,
                'targets' => collect(),
                'eligible_count' => 0,
            ];
        }

        $this->assertTechnicalRoundContext($procurement, $round);
        $targets = $this->technicalTargetQuery($procurement, $round)
            ->with([
                'submitter',
                'values',
                'technicalProposalCandidates' => function ($candidates) use ($procurement, $round): void {
                    $this->applyEligibleTechnicalCandidateScope($candidates, $procurement, $round)
                        ->with(['round', 'applicant', 'latestSubmission.documents']);
                },
            ])
            ->orderBy('submitted_at')
            ->orderBy('created_at')
            ->get();

        return [
            'round' => $round,
            'targets' => $targets,
            'eligible_count' => $targets->count(),
        ];
    }

    /**
     * Build a stage-aware applicant query. The returned builder always
     * selects FormSubmission records so callers can use one worklist API for
     * ordinary applications and technical proposals.
     */
    public function eligibleTargetQuery(
        Procurement $procurement,
        Evaluation $evaluation,
        string $stage = EvaluationAssignment::STAGE_APPLICATION,
        ?EoiTechnicalProposalRound $round = null
    ): Builder {
        $this->assertWorkflowStage($stage);

        if ($stage === EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL) {
            if (! $round) {
                throw ValidationException::withMessages([
                    'technical_proposal_round_id' => 'Select the technical-proposal round being evaluated.',
                ]);
            }

            $this->assertTechnicalRoundContext($procurement, $round);

            return $this->technicalTargetQuery($procurement, $round);
        }

        return FormSubmission::query()
            ->where('procurement_id', $procurement->getKey())
            ->availableForEvaluation()
            ->when(
                ! $evaluation->isEoi(),
                fn (Builder $query): Builder => $query->where(function (Builder $statusQuery): void {
                    $statusQuery->whereNull('status')
                        ->orWhere('status', '!=', FormSubmission::STATUS_EOI_EVALUATION);
                })
            );
    }

    /** @return Collection<int, FormSubmission> */
    public function eligibleTargets(
        Procurement $procurement,
        Evaluation $evaluation,
        string $stage = EvaluationAssignment::STAGE_APPLICATION,
        ?EoiTechnicalProposalRound $round = null
    ): Collection {
        $query = $this->eligibleTargetQuery($procurement, $evaluation, $stage, $round)
            ->with(['submitter', 'values']);

        if ($stage === EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL && $round) {
            $query->with([
                'technicalProposalCandidates' => function ($candidates) use ($procurement, $round): void {
                    $this->applyEligibleTechnicalCandidateScope($candidates, $procurement, $round)
                        ->with(['round', 'latestSubmission.documents']);
                },
            ]);
        }

        return $query
            ->orderBy('submitted_at')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Resolve the exact workflow source behind an applicant evaluation.
     *
     * @return null|array{
     *   stage: string,
     *   applicant: FormSubmission,
     *   candidate: ?EoiTechnicalProposalCandidate,
     *   proposal_submission: ?EoiTechnicalProposalSubmission,
     *   round: ?EoiTechnicalProposalRound
     * }
     */
    public function targetForApplicant(
        Procurement $procurement,
        Evaluation $evaluation,
        FormSubmission $applicant,
        string $stage = EvaluationAssignment::STAGE_APPLICATION,
        ?EoiTechnicalProposalRound $round = null
    ): ?array {
        if (! $this->eligibleTargetQuery($procurement, $evaluation, $stage, $round)
            ->whereKey($applicant->getKey())
            ->exists()) {
            return null;
        }

        if ($stage === EvaluationAssignment::STAGE_APPLICATION) {
            return $this->targetContext($stage, $applicant);
        }

        $candidate = $this->eligibleTechnicalCandidateQuery($procurement, $round)
            ->where('form_submission_id', $applicant->getKey())
            ->with(['applicant', 'round', 'latestSubmission.documents'])
            ->first();

        if (! $candidate || ! $this->technicalCandidateIsEligible($candidate, $round, $procurement)) {
            return null;
        }

        return $this->targetContext(
            $stage,
            $applicant,
            $candidate,
            $candidate->latestSubmission,
            $round
        );
    }

    public function isEligible(
        Procurement $procurement,
        Evaluation $evaluation,
        FormSubmission $applicant,
        string $stage = EvaluationAssignment::STAGE_APPLICATION,
        ?EoiTechnicalProposalRound $round = null
    ): bool {
        try {
            return $this->targetForApplicant(
                $procurement,
                $evaluation,
                $applicant,
                $stage,
                $round
            ) !== null;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * @return array{
     *   stage: string,
     *   applicant: FormSubmission,
     *   candidate: ?EoiTechnicalProposalCandidate,
     *   proposal_submission: ?EoiTechnicalProposalSubmission,
     *   round: ?EoiTechnicalProposalRound
     * }
     */
    public function assertEligible(
        Procurement $procurement,
        Evaluation $evaluation,
        FormSubmission $applicant,
        string $stage = EvaluationAssignment::STAGE_APPLICATION,
        ?EoiTechnicalProposalRound $round = null
    ): array {
        $target = $this->targetForApplicant(
            $procurement,
            $evaluation,
            $applicant,
            $stage,
            $round
        );

        if (! $target) {
            throw ValidationException::withMessages([
                'submission_id' => $stage === EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL
                    ? 'This applicant has not passed the selected technical-proposal compliance round.'
                    : 'This application is not currently eligible for evaluation.',
            ]);
        }

        return $target;
    }

    /** @return Collection<int, FormSubmission> */
    public function targetsForAssignment(EvaluationAssignment $assignment): Collection
    {
        $assignment->loadMissing(['procurement', 'evaluation', 'technicalProposalRound']);

        if (! $assignment->procurement || ! $assignment->evaluation) {
            return collect();
        }

        $targets = $this->eligibleTargets(
            $assignment->procurement,
            $assignment->evaluation,
            $assignment->workflowStage(),
            $assignment->technicalProposalRound
        );

        if (! $assignment->form_submission_id) {
            return $targets;
        }

        return $targets
            ->where('id', $assignment->form_submission_id)
            ->values();
    }

    /**
     * Behavior-only technical gate which can operate on relation-loaded
     * models in unit tests and avoids treating FormSubmission status alone as
     * proof that proposal compliance was completed.
     */
    public function technicalCandidateIsEligible(
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalRound $round,
        ?Procurement $procurement = null
    ): bool {
        $procurementId = (string) ($procurement?->getKey() ?: $round->procurement_id);
        $roundId = (string) $round->getKey();
        $candidateId = (string) $candidate->getKey();

        if ($procurementId === ''
            || $roundId === ''
            || $candidateId === ''
            || (string) $round->procurement_id !== $procurementId
            || ! in_array($round->status, $this->assignableRoundStatuses(), true)
            || (string) $candidate->round_id !== $roundId
            || $candidate->status !== EoiTechnicalProposalCandidate::STATUS_QUALIFIED
            || ! in_array($candidate->eoi_outcome_code, $this->qualifiedEoiOutcomes(), true)) {
            return false;
        }

        $applicant = $candidate->relationLoaded('applicant')
            ? $candidate->getRelation('applicant')
            : $candidate->applicant()->first();

        if (! $applicant
            || (string) $candidate->form_submission_id !== (string) $applicant->getKey()
            || (string) $applicant->procurement_id !== $procurementId
            || $applicant->status !== FormSubmission::STATUS_TECHNICAL_EVALUATION) {
            return false;
        }

        $proposalSubmission = $candidate->relationLoaded('latestSubmission')
            ? $candidate->getRelation('latestSubmission')
            : $candidate->latestSubmission()->with('documents')->first();

        if (! $proposalSubmission
            || ! $proposalSubmission->getKey()
            || (string) $proposalSubmission->candidate_id !== $candidateId
            || (int) $proposalSubmission->revision_number < 1) {
            return false;
        }

        $documents = $proposalSubmission->relationLoaded('documents')
            ? $proposalSubmission->getRelation('documents')
            : $proposalSubmission->documents()->get(['id', 'proposal_submission_id']);

        return $documents->contains(
            fn ($document): bool => (string) $document->proposal_submission_id
                === (string) $proposalSubmission->getKey()
        );
    }

    private function technicalTargetQuery(
        Procurement $procurement,
        EoiTechnicalProposalRound $round
    ): Builder {
        return FormSubmission::query()
            ->where('procurement_id', $procurement->getKey())
            ->where('status', FormSubmission::STATUS_TECHNICAL_EVALUATION)
            ->whereHas(
                'technicalProposalCandidates',
                fn (Builder $candidates): Builder => $this->applyEligibleTechnicalCandidateScope(
                    $candidates,
                    $procurement,
                    $round
                )
            );
    }

    private function eligibleTechnicalCandidateQuery(
        Procurement $procurement,
        EoiTechnicalProposalRound $round
    ): Builder {
        return $this->applyEligibleTechnicalCandidateScope(
            EoiTechnicalProposalCandidate::query(),
            $procurement,
            $round
        );
    }

    private function applyEligibleTechnicalCandidateScope(
        Builder|Relation $query,
        Procurement $procurement,
        EoiTechnicalProposalRound $round
    ): Builder|Relation {
        return $query
            ->where('round_id', $round->getKey())
            ->where('status', EoiTechnicalProposalCandidate::STATUS_QUALIFIED)
            ->whereIn('eoi_outcome_code', $this->qualifiedEoiOutcomes())
            ->whereHas('round', fn (Builder $roundQuery): Builder => $roundQuery
                ->whereKey($round->getKey())
                ->where('procurement_id', $procurement->getKey())
                ->whereIn('status', $this->assignableRoundStatuses()))
            ->whereHas('applicant', fn (Builder $applicantQuery): Builder => $applicantQuery
                ->where('procurement_id', $procurement->getKey())
                ->where('status', FormSubmission::STATUS_TECHNICAL_EVALUATION))
            ->whereHas('submissions', function (Builder $submissionQuery): void {
                $submissionQuery
                    ->whereHas('documents')
                    ->where('revision_number', '=', function ($latestRevisionQuery): void {
                        $latestRevisionQuery
                            ->selectRaw('MAX(latest_proposals.revision_number)')
                            ->from('eoi_technical_proposal_submissions as latest_proposals')
                            ->whereColumn(
                                'latest_proposals.candidate_id',
                                'eoi_technical_proposal_submissions.candidate_id'
                            );
                    });
            });
    }

    private function assertWorkflowStage(string $stage): void
    {
        if (! in_array($stage, EvaluationAssignment::WORKFLOW_STAGES, true)) {
            throw ValidationException::withMessages([
                'workflow_stage' => 'Select a valid evaluation workflow stage.',
            ]);
        }
    }

    private function assertTechnicalRoundContext(
        Procurement $procurement,
        EoiTechnicalProposalRound $round
    ): void {
        if ((string) $round->procurement_id !== (string) $procurement->getKey()) {
            throw ValidationException::withMessages([
                'technical_proposal_round_id' => 'The selected proposal round does not belong to this procurement.',
            ]);
        }

        if (! in_array($round->status, $this->assignableRoundStatuses(), true)) {
            throw ValidationException::withMessages([
                'technical_proposal_round_id' => 'Only a published or closed proposal round can be evaluated.',
            ]);
        }
    }

    /**
     * @return array{
     *   stage: string,
     *   applicant: FormSubmission,
     *   candidate: ?EoiTechnicalProposalCandidate,
     *   proposal_submission: ?EoiTechnicalProposalSubmission,
     *   round: ?EoiTechnicalProposalRound
     * }
     */
    private function targetContext(
        string $stage,
        FormSubmission $applicant,
        ?EoiTechnicalProposalCandidate $candidate = null,
        ?EoiTechnicalProposalSubmission $proposalSubmission = null,
        ?EoiTechnicalProposalRound $round = null
    ): array {
        return [
            'stage' => $stage,
            'applicant' => $applicant,
            'candidate' => $candidate,
            'proposal_submission' => $proposalSubmission,
            'round' => $round,
        ];
    }

    /** @return array<int, string> */
    private function qualifiedEoiOutcomes(): array
    {
        return [
            EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
            EoiQualificationService::OUTCOME_AVERAGE_QUALIFIED,
        ];
    }

    /** @return array<int, string> */
    private function assignableRoundStatuses(): array
    {
        return [
            EoiTechnicalProposalRound::STATUS_PUBLISHED,
            EoiTechnicalProposalRound::STATUS_CLOSED,
        ];
    }
}
