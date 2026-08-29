<?php

namespace App\Services;

use App\Models\EoiReportCommunication;
use App\Models\EoiReportCommunicationRecipient;
use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalRound;
use App\Models\EoiTechnicalProposalSubmission;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ReworkRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EvaluationReworkService
{
    public function __construct(
        private readonly EoiQualificationService $qualificationService,
        private readonly EvaluationAssignmentTargetResolver $targetResolver
    ) {}

    public function request(
        EvaluationSubmission $submission,
        User $requester,
        string $reason,
        bool $overrideProposalRoundLock = false
    ): ReworkRequest {
        return DB::transaction(function () use (
            $submission,
            $requester,
            $reason,
            $overrideProposalRoundLock
        ): ReworkRequest {
            $this->lockWorkItem($submission);

            $procurement = Procurement::withTrashed()
                ->whereKey($submission->procurement_id)
                ->lockForUpdate()
                ->firstOrFail();
            $assignment = $this->resolveAssignment($submission, true);
            $lockedSubmission = EvaluationSubmission::query()
                ->whereKey($submission->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertContextMatches($lockedSubmission, $assignment, $procurement);

            if (! $lockedSubmission->evaluation_assignment_id) {
                $lockedSubmission->forceFill([
                    'evaluation_assignment_id' => $assignment->getKey(),
                ])->save();
            }

            if (ReworkRequest::query()
                ->where('evaluation_submission_id', $lockedSubmission->getKey())
                ->where('status', ReworkRequest::STATUS_PENDING)
                ->exists()) {
                throw ValidationException::withMessages([
                    'rework' => 'This evaluation is already awaiting rework from the evaluator.',
                ]);
            }

            if (! $lockedSubmission->isSubmitted()) {
                throw ValidationException::withMessages([
                    'rework' => 'Only a currently submitted evaluation can be sent for rework.',
                ]);
            }

            $applicant = $this->lockAndAssertTargetEligible(
                $procurement,
                $assignment,
                $lockedSubmission
            );
            $workflowLockOverride = $this->assertNoDownstreamDecision(
                $procurement,
                $assignment,
                $lockedSubmission,
                $requester,
                $overrideProposalRoundLock
            );

            $cycle = ((int) ReworkRequest::query()
                ->where('evaluation_submission_id', $lockedSubmission->getKey())
                ->max('cycle')) + 1;
            $sourceRevision = max(1, (int) $lockedSubmission->revision_number);
            $event = [
                'event' => 'rework_requested',
                'cycle' => $cycle,
                'reason' => $reason,
                'actor' => $this->actorSnapshot($requester),
            ];

            if ($workflowLockOverride !== null) {
                $event['workflow_lock_override'] = $workflowLockOverride;
            }

            $sourceSnapshot = $this->snapshot($lockedSubmission, $event);

            $rework = ReworkRequest::create([
                'evaluation_submission_id' => $lockedSubmission->getKey(),
                'evaluation_assignment_id' => $assignment->getKey(),
                'procurement_id' => $lockedSubmission->procurement_id,
                'form_submission_id' => $lockedSubmission->form_submission_id,
                'evaluation_id' => $lockedSubmission->evaluation_id,
                'evaluator_id' => $lockedSubmission->evaluator_id,
                'requested_by' => $requester->getKey(),
                'cycle' => $cycle,
                'message' => Str::limit($reason, 255, ''),
                'reason' => $reason,
                'snapshot_schema_version' => 1,
                'status' => ReworkRequest::STATUS_PENDING,
                'requested_at' => now(),
                'original_submitted_at' => $lockedSubmission->submitted_at,
                'source_revision_number' => $sourceRevision,
                'source_snapshot' => $sourceSnapshot,
                'source_snapshot_hash' => $this->snapshotHash($sourceSnapshot),
            ]);

            $lockedSubmission->forceFill([
                'submitted_at' => null,
                'workflow_status' => EvaluationSubmission::WORKFLOW_REWORK_REQUESTED,
                'revision_number' => $sourceRevision,
            ])->save();

            $assignment->forceFill(['status' => 'rework'])->save();

            if ($assignment->isApplicationStage()
                && $lockedSubmission->evaluation?->isEoi()) {
                $this->qualificationService->synchronizeApplicantStage($applicant);
            }

            return $rework;
        }, 3);
    }

    public function completeOpenRequest(
        EvaluationSubmission $submission,
        User $evaluator
    ): ?ReworkRequest {
        $rework = ReworkRequest::query()
            ->where('evaluation_submission_id', $submission->getKey())
            ->where('status', ReworkRequest::STATUS_PENDING)
            ->lockForUpdate()
            ->orderByDesc('cycle')
            ->first();

        if (! $rework) {
            return null;
        }

        if ((string) $rework->evaluator_id !== (string) $evaluator->getKey()
            || (string) $submission->evaluator_id !== (string) $evaluator->getKey()
            || ! $submission->isSubmitted()
            || $submission->workflow_status !== EvaluationSubmission::WORKFLOW_SUBMITTED
            || (int) $submission->revision_number <= (int) $rework->source_revision_number) {
            throw ValidationException::withMessages([
                'rework' => 'Only the assigned evaluator can complete this rework with a newly submitted revision.',
            ]);
        }

        if (! is_array($rework->source_snapshot)
            || ! is_string($rework->source_snapshot_hash)
            || ! hash_equals(
                $rework->source_snapshot_hash,
                $this->snapshotHash($rework->source_snapshot)
            )) {
            throw ValidationException::withMessages([
                'rework' => 'The original evaluation audit snapshot failed its integrity check. Contact an administrator before resubmitting.',
            ]);
        }

        $completedSnapshot = $this->snapshot($submission, [
            'event' => 'rework_completed',
            'cycle' => (int) $rework->cycle,
            'rework_request_id' => (string) $rework->getKey(),
            'actor' => $this->actorSnapshot($evaluator),
        ]);

        $rework->forceFill([
            'status' => ReworkRequest::STATUS_COMPLETED,
            'completed_by' => $evaluator->getKey(),
            'completed_at' => $submission->submitted_at ?: now(),
            'completed_revision_number' => (int) $submission->revision_number,
            'completed_snapshot' => $completedSnapshot,
            'completed_snapshot_hash' => $this->snapshotHash($completedSnapshot),
        ])->save();

        return $rework;
    }

    private function resolveAssignment(
        EvaluationSubmission $submission,
        bool $lock = false
    ): EvaluationAssignment {
        $base = EvaluationAssignment::query()
            ->where('evaluation_id', $submission->evaluation_id)
            ->where('procurement_id', $submission->procurement_id)
            ->where('user_id', $submission->evaluator_id)
            ->where(function ($query) use ($submission): void {
                $query->whereNull('form_submission_id')
                    ->orWhere('form_submission_id', $submission->form_submission_id);
            });

        if ($submission->evaluation_assignment_id) {
            $direct = (clone $base)->whereKey($submission->evaluation_assignment_id);

            if ($lock) {
                $direct->lockForUpdate();
            }

            $assignment = $direct->first();
            if ($assignment) {
                return $assignment;
            }

            throw ValidationException::withMessages([
                'rework' => 'The exact evaluator assignment for this report is no longer active.',
            ]);
        }

        $legacy = $base
            ->where(function ($query): void {
                $query->whereNull('workflow_stage')
                    ->orWhere('workflow_stage', EvaluationAssignment::STAGE_APPLICATION);
            })
            ->whereNull('technical_proposal_round_id')
            ->orderByRaw(
                'CASE WHEN form_submission_id = ? THEN 0 ELSE 1 END',
                [$submission->form_submission_id]
            );

        if ($lock) {
            $legacy->lockForUpdate();
        }

        $candidates = $legacy->get();
        $exactCandidates = $candidates->filter(
            fn (EvaluationAssignment $candidate): bool => (string) $candidate->form_submission_id === (string) $submission->form_submission_id
        )->values();

        if ($exactCandidates->count() === 1) {
            return $exactCandidates->first();
        }

        $procurementWideCandidates = $candidates
            ->filter(fn (EvaluationAssignment $candidate): bool => blank($candidate->form_submission_id))
            ->values();

        if ($exactCandidates->isEmpty() && $procurementWideCandidates->count() === 1) {
            return $procurementWideCandidates->first();
        }

        if ($candidates->isEmpty()) {
            throw ValidationException::withMessages([
                'rework' => 'The evaluator assignment for this report is no longer active.',
            ]);
        }

        throw ValidationException::withMessages([
            'rework' => 'This legacy report matches more than one evaluator assignment and cannot be reopened safely.',
        ]);
    }

    private function assertContextMatches(
        EvaluationSubmission $submission,
        EvaluationAssignment $assignment,
        Procurement $procurement
    ): void {
        abort_unless(
            ! $procurement->trashed()
                && (string) $submission->procurement_id === (string) $procurement->getKey()
                && (string) $assignment->evaluation_id === (string) $submission->evaluation_id
                && (string) $assignment->procurement_id === (string) $submission->procurement_id
                && (string) $assignment->user_id === (string) $submission->evaluator_id
                && (blank($assignment->form_submission_id)
                    || (string) $assignment->form_submission_id === (string) $submission->form_submission_id),
            404
        );
    }

    private function lockAndAssertTargetEligible(
        Procurement $procurement,
        EvaluationAssignment $assignment,
        EvaluationSubmission $submission
    ): FormSubmission {
        $applicant = FormSubmission::query()
            ->whereKey($submission->form_submission_id)
            ->where('procurement_id', $procurement->getKey())
            ->lockForUpdate()
            ->first();
        $evaluator = User::query()
            ->whereKey($submission->evaluator_id)
            ->lockForUpdate()
            ->first();

        if (! $applicant || ! $evaluator) {
            throw ValidationException::withMessages([
                'rework' => 'The applicant or evaluator account for this report is no longer available.',
            ]);
        }

        if ($evaluator->hasActiveLoginBlock()
            || (bool) $evaluator->is_blacklisted
            || ! $evaluator->hasPermission('evaluations.evaluate')) {
            throw ValidationException::withMessages([
                'rework' => 'The assigned evaluator cannot currently access the evaluation workspace. Reactivate or reassign the evaluator before requesting rework.',
            ]);
        }

        $assignment->loadMissing(['evaluation', 'technicalProposalRound']);

        if (! $assignment->evaluation) {
            throw ValidationException::withMessages([
                'rework' => 'The evaluation form for this report is no longer available.',
            ]);
        }

        $round = null;
        if ($assignment->isTechnicalProposal()) {
            $round = EoiTechnicalProposalRound::query()
                ->whereKey($assignment->technical_proposal_round_id)
                ->where('procurement_id', $procurement->getKey())
                ->lockForUpdate()
                ->first();

            if (! $round
                || ! $submission->technical_proposal_candidate_id
                || ! $submission->technical_proposal_submission_id) {
                throw ValidationException::withMessages([
                    'rework' => 'The technical-proposal source for this evaluation is no longer available.',
                ]);
            }

            $candidate = EoiTechnicalProposalCandidate::query()
                ->whereKey($submission->technical_proposal_candidate_id)
                ->where('round_id', $round->getKey())
                ->where('form_submission_id', $applicant->getKey())
                ->lockForUpdate()
                ->first();
            $proposal = EoiTechnicalProposalSubmission::query()
                ->whereKey($submission->technical_proposal_submission_id)
                ->where('candidate_id', $submission->technical_proposal_candidate_id)
                ->whereHas('documents')
                ->lockForUpdate()
                ->first();

            if (! $candidate || ! $proposal) {
                throw ValidationException::withMessages([
                    'rework' => 'The exact technical-proposal revision evaluated in this report is no longer available.',
                ]);
            }

            $assignment->setRelation('technicalProposalRound', $round);
        }

        $isUnreleasedNegativeEoiOutcome = $assignment->isApplicationStage()
            && $assignment->evaluation->isEoi()
            && $applicant->status === FormSubmission::STATUS_EOI_NOT_QUALIFIED;
        $target = $isUnreleasedNegativeEoiOutcome
            ? ['candidate' => null]
            : $this->targetResolver->assertEligible(
                $procurement,
                $assignment->evaluation,
                $applicant,
                $assignment->workflowStage(),
                $round
            );

        if ($assignment->isTechnicalProposal()
            && (string) $target['candidate']?->getKey()
                !== (string) $submission->technical_proposal_candidate_id) {
            throw ValidationException::withMessages([
                'rework' => 'The qualified technical-proposal candidate no longer matches this evaluation.',
            ]);
        }

        return $applicant;
    }

    /**
     * @return null|array<string, mixed>
     */
    private function assertNoDownstreamDecision(
        Procurement $procurement,
        EvaluationAssignment $assignment,
        EvaluationSubmission $submission,
        User $requester,
        bool $overrideProposalRoundLock
    ): ?array {
        $isSystemAdministrator = $requester->isAdmin() || $requester->isSuperAdmin();

        if ($overrideProposalRoundLock && ! $isSystemAdministrator) {
            throw ValidationException::withMessages([
                'override_proposal_round_lock' => 'Only a System or Super Administrator can override an existing technical-proposal round.',
            ]);
        }

        if (filled($procurement->awarded_submission_id)
            || filled($procurement->awarded_at)
            || $procurement->contractNegotiations()->exists()
            || $procurement->purchaseOrders()->exists()) {
            throw ValidationException::withMessages([
                'rework' => 'This evaluation cannot be reopened after an award, contract negotiation, or purchase order has started.',
            ]);
        }

        $submission->loadMissing('evaluation');

        if ($assignment->isApplicationStage()
            && $submission->evaluation?->isEoi()) {
            $hasReleasedApplicantRecord = EoiReportCommunicationRecipient::query()
                ->where('form_submission_id', $submission->form_submission_id)
                ->whereHas('communication', fn ($query) => $query
                    ->where('procurement_id', $procurement->getKey())
                    ->where('type', EoiReportCommunication::TYPE_EVALUATION_RECORDS))
                ->where(function ($query): void {
                    $query->whereNotNull('record_file_path')
                        ->orWhereNotNull('emailed_at')
                        ->orWhereIn('delivery_status', [
                            EoiReportCommunicationRecipient::STATUS_PENDING,
                            EoiReportCommunicationRecipient::STATUS_PROCESSING,
                            EoiReportCommunicationRecipient::STATUS_SENT,
                        ]);
                })
                ->exists();

            if ($hasReleasedApplicantRecord) {
                throw ValidationException::withMessages([
                    'rework' => 'This EOI evaluation cannot be reopened after the applicant evaluation record has been released.',
                ]);
            }

            $proposalRounds = EoiTechnicalProposalRound::query()
                ->where('procurement_id', $procurement->getKey())
                ->where('status', '!=', EoiTechnicalProposalRound::STATUS_CANCELLED)
                ->select(['id', 'round_number', 'title', 'status', 'published_at'])
                ->orderBy('round_number')
                ->lockForUpdate()
                ->get();

            if ($proposalRounds->isNotEmpty() && ! $overrideProposalRoundLock) {
                throw ValidationException::withMessages([
                    'rework' => 'This EOI evaluation cannot be reopened because the technical-proposal round has already started or been prepared.',
                ]);
            }

            if ($proposalRounds->isNotEmpty()) {
                return [
                    'type' => 'eoi_technical_proposal_round',
                    'scope' => 'single_evaluation_submission',
                    'confirmed' => true,
                    'authorized_as' => match (true) {
                        $requester->isAdmin() => 'system_admin_role',
                        $requester->hasRole('Super Admin') => 'super_admin_role',
                        default => 'platform_admin_account',
                    },
                    'rounds' => $proposalRounds->map(fn (EoiTechnicalProposalRound $round): array => [
                        'id' => (string) $round->getKey(),
                        'round_number' => (int) $round->round_number,
                        'title' => $round->title,
                        'status' => $round->status,
                        'published_at' => $round->published_at?->toIso8601String(),
                    ])->all(),
                ];
            }
        }

        return null;
    }

    private function lockWorkItem(EvaluationSubmission $submission): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [implode(':', [
            $submission->evaluation_id,
            $submission->procurement_id,
            $submission->evaluator_id,
            $submission->form_submission_id,
        ])]);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function snapshot(EvaluationSubmission $submission, array $event): array
    {
        $submission->load([
            'assignment.technicalProposalRound',
            'evaluation',
            'procurement',
            'applicant.submitter',
            'evaluator',
            'criteriaScores.criteria.section',
            'sectionScores.section',
            'technicalProposalCandidate.round',
            'technicalProposalSubmission.documents',
        ]);
        $identityVideo = $this->identityVideoEvidence($submission);

        return [
            'schema_version' => 1,
            'captured_at' => now()->toIso8601String(),
            'event' => $event,
            'submission' => [
                'id' => (string) $submission->getKey(),
                'evaluation_assignment_id' => $submission->evaluation_assignment_id,
                'technical_proposal_candidate_id' => $submission->technical_proposal_candidate_id,
                'technical_proposal_submission_id' => $submission->technical_proposal_submission_id,
                'revision_number' => (int) $submission->revision_number,
                'workflow_status' => (string) $submission->workflow_status,
                'overall_score' => $submission->overall_score,
                'comments' => $submission->comments,
                'video_path' => $identityVideo['path'],
                'video_duration' => $identityVideo['duration'],
                'video_disk' => $identityVideo['disk'],
                'video_file_size' => $identityVideo['file_size'],
                'video_sha256' => $identityVideo['sha256'],
                'submitted_at' => $submission->submitted_at?->toIso8601String(),
            ],
            'evaluation' => [
                'id' => (string) $submission->evaluation_id,
                'name' => $submission->evaluation?->name,
                'type' => $submission->evaluation?->type,
                'phase' => $submission->evaluation?->evaluation_phase,
                'response_mode' => $submission->evaluation?->usesNumericScoring()
                    ? 'numeric'
                    : 'categorical',
            ],
            'assignment' => [
                'id' => $submission->assignment?->getKey(),
                'workflow_stage' => $submission->assignment?->workflowStage(),
                'technical_proposal_round_id' => $submission->assignment?->technical_proposal_round_id,
                'status' => $submission->assignment?->status,
                'assigned_at' => $submission->assignment?->assigned_at?->toIso8601String(),
            ],
            'procurement' => [
                'id' => (string) $submission->procurement_id,
                'reference_no' => $submission->procurement?->reference_no,
                'title' => $submission->procurement?->title,
            ],
            'applicant' => [
                'id' => (string) $submission->form_submission_id,
                'submission_code' => $submission->applicant?->procurement_submission_code,
                'name' => $submission->applicant?->display_name,
            ],
            'evaluator' => [
                'id' => (string) $submission->evaluator_id,
                'name' => $submission->evaluator?->name,
                'email' => $submission->evaluator?->email,
            ],
            'technical_proposal' => [
                'round' => [
                    'id' => $submission->technicalProposalCandidate?->round?->getKey()
                        ?? $submission->assignment?->technicalProposalRound?->getKey(),
                    'number' => $submission->technicalProposalCandidate?->round?->round_number
                        ?? $submission->assignment?->technicalProposalRound?->round_number,
                    'title' => $submission->technicalProposalCandidate?->round?->title
                        ?? $submission->assignment?->technicalProposalRound?->title,
                    'status' => $submission->technicalProposalCandidate?->round?->status
                        ?? $submission->assignment?->technicalProposalRound?->status,
                ],
                'candidate' => [
                    'id' => $submission->technicalProposalCandidate?->getKey(),
                    'status' => $submission->technicalProposalCandidate?->status,
                ],
                'submission' => [
                    'id' => $submission->technicalProposalSubmission?->getKey(),
                    'revision_number' => $submission->technicalProposalSubmission?->revision_number,
                    'source' => $submission->technicalProposalSubmission?->source,
                    'received_via' => $submission->technicalProposalSubmission?->received_via,
                    'received_at' => $submission->technicalProposalSubmission?->received_at?->toIso8601String(),
                    'documents' => $submission->technicalProposalSubmission?->documents
                        ?->sortBy('id')
                        ->map(fn ($document): array => [
                            'id' => (string) $document->getKey(),
                            'label' => $document->document_label,
                            'original_filename' => $document->original_filename,
                            'mime_type' => $document->mime_type,
                            'file_size' => $document->file_size,
                            'sha256' => $document->sha256,
                        ])->values()->all() ?? [],
                ],
            ],
            'criteria_scores' => $submission->criteriaScores
                ->sortBy('evaluation_criteria_id')
                ->map(fn ($score): array => [
                    'criterion_id' => (string) $score->evaluation_criteria_id,
                    'criterion' => $score->criteria?->name,
                    'criterion_description' => $score->criteria?->description,
                    'section_id' => $score->criteria?->evaluation_section_id,
                    'section' => $score->criteria?->section?->name,
                    'section_description' => $score->criteria?->section?->description,
                    'parent_section_id' => $score->criteria?->section?->parent_section_id,
                    'section_sort_order' => $score->criteria?->section?->sort_order,
                    'maximum_score' => $score->criteria?->max_score,
                    'score' => $score->score,
                    'decision' => $score->decision,
                    'decision_label' => $submission->evaluation?->decisionLabel($score->decision),
                    'comment' => $score->comment,
                ])->values()->all(),
            'section_scores' => $submission->sectionScores
                ->sortBy('evaluation_section_id')
                ->map(fn ($score): array => [
                    'section_id' => (string) $score->evaluation_section_id,
                    'section' => $score->section?->name,
                    'description' => $score->section?->description,
                    'parent_section_id' => $score->section?->parent_section_id,
                    'sort_order' => $score->section?->sort_order,
                    'section_score' => $score->section_score,
                    'strengths' => $score->strengths,
                    'weaknesses' => $score->weaknesses,
                ])->values()->all(),
        ];
    }

    /**
     * @return array{path: ?string, duration: mixed, disk: ?string, file_size: ?int, sha256: ?string}
     */
    private function identityVideoEvidence(EvaluationSubmission $submission): array
    {
        $path = filled($submission->video_path) ? (string) $submission->video_path : null;
        $disk = null;
        $fileSize = null;
        $sha256 = null;

        if ($path) {
            $candidateDisks = collect([
                (string) config('filesystems.default', 'local'),
                'local',
                'public',
            ])->filter(fn (string $candidate): bool => config("filesystems.disks.{$candidate}") !== null)
                ->unique();

            foreach ($candidateDisks as $candidateDisk) {
                try {
                    $storage = Storage::disk($candidateDisk);

                    if (! $storage->exists($path)) {
                        continue;
                    }

                    $disk = $candidateDisk;
                    $fileSize = $storage->size($path);
                    $stream = $storage->readStream($path);

                    if (is_resource($stream)) {
                        try {
                            $hash = hash_init('sha256');
                            hash_update_stream($hash, $stream);
                            $sha256 = hash_final($hash);
                        } finally {
                            fclose($stream);
                        }
                    }
                    break;
                } catch (Throwable) {
                    // Try the next known disk; legacy videos may predate private storage.
                }
            }
        }

        return [
            'path' => $path,
            'duration' => $submission->video_duration,
            'disk' => $disk,
            'file_size' => $fileSize,
            'sha256' => $sha256,
        ];
    }

    /** @return array{id: string, name: ?string, email: ?string} */
    private function actorSnapshot(User $actor): array
    {
        return [
            'id' => (string) $actor->getKey(),
            'name' => $actor->name,
            'email' => $actor->email,
        ];
    }

    /** @param array<string, mixed> $snapshot */
    private function snapshotHash(array $snapshot): string
    {
        return hash('sha256', json_encode(
            $this->canonicalizeSnapshotValue($snapshot),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    }

    private function canonicalizeSnapshotValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->canonicalizeSnapshotValue($item),
                $value
            );
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeSnapshotValue($item);
        }

        return $value;
    }
}
