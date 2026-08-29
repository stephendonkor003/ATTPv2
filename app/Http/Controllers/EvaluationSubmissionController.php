<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Mail\EvaluationCompleted;
use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalDocument;
use App\Models\EoiTechnicalProposalSubmission;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ReworkRequest;
use App\Models\User;
use App\Services\EoiQualificationService;
use App\Services\EvaluationAssignmentTargetResolver;
use App\Services\EvaluationReworkService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvaluationSubmissionController extends Controller
{
    use ScopesAssignedPortfolios;

    /* =====================================================
     * EVALUATION HUB
     * ===================================================== */
    public function myEvaluations()
    {
        return $this->renderEvaluationWorklist();
    }

    public function assignmentApplicants(EvaluationAssignment $assignment)
    {
        return $this->renderEvaluationWorklist($assignment);
    }

    private function renderEvaluationWorklist(?EvaluationAssignment $assignment = null)
    {
        $user = auth()->user();

        if ($assignment) {
            $ownsAssignment = (string) $assignment->user_id === (string) $user->id;
            abort_unless(
                $ownsAssignment || $user->can('evaluations.view_all'),
                403
            );

            if (! $ownsAssignment && $this->userHasAssignedPortfolioScope($user)) {
                abort_unless(
                    $this->evaluationAssignmentIsInAssignedPortfolio($assignment, $user),
                    403,
                    'This evaluation is outside your assigned portfolio.'
                );
            }
        }

        $assignments = EvaluationAssignment::query()
            ->with(['procurement', 'evaluation', 'technicalProposalRound'])
            ->whereHas('procurement', fn ($query) => $query->whereNull('procurements.deleted_at'))
            ->whereHas('evaluation')
            ->when(
                $assignment,
                fn ($query) => $query->whereKey($assignment->getKey()),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->latest('assigned_at')
            ->latest()
            ->get();

        $applicationsByAssignmentId = $assignments->mapWithKeys(function (EvaluationAssignment $item): array {
            $applications = $this->applicationsForAssignment($item);
            $applications->each(fn (FormSubmission $application) => $application->loadMissing([
                'form',
                'submitter',
                'values',
            ]));

            return [(string) $item->getKey() => $applications];
        });
        $submissions = $applicationsByAssignmentId
            ->flatten(1)
            ->unique(fn (FormSubmission $application): string => (string) $application->getKey())
            ->values();
        $allSubmissionIds = $submissions->pluck('id');
        $assignmentIds = $assignments->pluck('id');
        $applicationAssignments = $assignments->reject->isTechnicalProposal();

        $submissionRecords = ($assignmentIds->isEmpty() || $allSubmissionIds->isEmpty())
            ? collect()
            : EvaluationSubmission::query()
                ->whereIn('form_submission_id', $allSubmissionIds)
                ->where(function ($query) use ($assignmentIds, $applicationAssignments): void {
                    $query->whereIn('evaluation_assignment_id', $assignmentIds);

                    if ($applicationAssignments->isNotEmpty()) {
                        $query->orWhere(function ($legacy) use ($applicationAssignments): void {
                            $legacy->whereNull('evaluation_assignment_id')
                                ->whereIn('evaluation_id', $applicationAssignments->pluck('evaluation_id')->filter()->unique())
                                ->whereIn('procurement_id', $applicationAssignments->pluck('procurement_id')->filter()->unique())
                                ->whereIn('evaluator_id', $applicationAssignments->pluck('user_id')->filter()->unique());
                        });
                    }
                })
                ->with('openReworkRequest.requester')
                ->get();

        $evaluationSubmissions = collect();
        foreach ($assignments as $item) {
            foreach ($applicationsByAssignmentId->get((string) $item->getKey(), collect()) as $application) {
                $record = $this->submissionRecordFromCollection($submissionRecords, $item, $application);
                if ($record) {
                    $evaluationSubmissions->put($this->assignmentTaskKey($item, $application), $record);
                }
            }
        }

        $reworkTasks = collect();
        foreach ($assignments as $item) {
            foreach ($applicationsByAssignmentId->get((string) $item->getKey(), collect()) as $application) {
                $record = $evaluationSubmissions->get($this->assignmentTaskKey($item, $application));
                $openRework = $record?->openReworkRequest;

                if (! $record
                    || filled($record->submitted_at)
                    || $record->workflow_status !== EvaluationSubmission::WORKFLOW_REWORK_REQUESTED
                    || ! $openRework) {
                    continue;
                }

                $reworkTasks->push([
                    'assignment' => $item,
                    'application' => $application,
                    'submission' => $record,
                    'rework' => $openRework,
                    'edit_url' => route('my.eval.start', [$item, $application]),
                ]);
            }
        }
        $reworkTasks = $reworkTasks
            ->sortByDesc(fn (array $task) => $task['rework']->requested_at?->getTimestamp() ?? 0)
            ->values();

        $applicationsByAssignmentId = $applicationsByAssignmentId->map(
            function (Collection $applications, string $assignmentId) use (
                $assignments,
                $evaluationSubmissions
            ): Collection {
                $item = $assignments->first(
                    fn (EvaluationAssignment $candidate): bool => (string) $candidate->getKey() === $assignmentId
                );

                if (! $item) {
                    return $applications;
                }

                return $applications
                    ->sortBy(function (FormSubmission $application) use ($evaluationSubmissions, $item): string {
                        $record = $evaluationSubmissions->get($this->assignmentTaskKey($item, $application));
                        $isRework = $record?->workflow_status === EvaluationSubmission::WORKFLOW_REWORK_REQUESTED
                            && $record?->openReworkRequest !== null
                            && blank($record->submitted_at);
                        $priority = match (true) {
                            $isRework => 0,
                            blank($record?->submitted_at) => 1,
                            default => 2,
                        };
                        $activityOrder = $isRework
                            ? str_pad((string) (PHP_INT_MAX - ($record->openReworkRequest?->requested_at?->getTimestamp() ?? 0)), 20, '0', STR_PAD_LEFT)
                            : '99999999999999999999';

                        return $priority.'|'.$activityOrder.'|'.strtolower((string) $application->procurement_submission_code);
                    })
                    ->values();
            }
        );

        $reworkAssignmentIds = $reworkTasks
            ->pluck('assignment.id')
            ->map(fn ($id): string => (string) $id)
            ->flip();
        $assignments = $assignments
            ->sortBy(fn (EvaluationAssignment $item): int => $reworkAssignmentIds->has((string) $item->getKey()) ? 0 : 1)
            ->values();

        $taskCount = $assignments->sum(function (EvaluationAssignment $item) use ($applicationsByAssignmentId): int {
            return $applicationsByAssignmentId
                ->get((string) $item->getKey(), collect())
                ->count();
        });
        $completedCount = $evaluationSubmissions
            ->filter(fn (EvaluationSubmission $submission): bool => filled($submission->submitted_at))
            ->count();

        $stats = [
            'assignments' => $assignments->count(),
            'tasks' => $taskCount,
            'completed' => $completedCount,
            'drafts' => $evaluationSubmissions
                ->filter(fn (EvaluationSubmission $submission): bool => blank($submission->submitted_at))
                ->count(),
            'rework' => $reworkTasks->count(),
            'pending' => max(0, $taskCount - $completedCount - $reworkTasks->count()),
        ];

        return view('evaluations.my', compact(
            'assignments',
            'submissions',
            'evaluationSubmissions',
            'stats',
            'applicationsByAssignmentId',
            'reworkTasks'
        ));
    }

    /* =====================================================
     * START / CONTINUE EVALUATION
     * ===================================================== */
    public function start(EvaluationAssignment $assignment, FormSubmission $applicant)
    {
        $this->assertAssignmentOwner($assignment);
        $this->assertApplicantBelongsToAssignment($assignment, $applicant);
        $this->assertApplicantReadyForEvaluation($assignment, $applicant);

        $assignment->loadMissing([
            'procurement',
            'evaluation.sections.criteria',
            'technicalProposalRound',
        ]);
        $applicant->loadMissing(['form', 'submitter', 'values']);

        abort_unless($assignment->procurement && $assignment->evaluation, 404);
        abort_if($assignment->procurement?->trashed(), 404);
        abort_if(
            $assignment->evaluation->sections->flatMap->criteria->isEmpty(),
            422,
            'This evaluation form has no configured criteria. Please contact the system administrator.'
        );

        $submission = DB::transaction(function () use ($assignment, $applicant) {
            $this->lockEvaluationWorkItem($assignment, $applicant);

            return $this->evaluationSubmissionForUpdate($assignment, $applicant);
        });

        if ($submission->isSubmitted()) {
            return redirect()->route('my.eval.view', [$assignment, $applicant]);
        }

        $submission->load([
            'criteriaScores',
            'sectionScores',
            'openReworkRequest.requester',
        ]);
        $openRework = $submission->openReworkRequest;
        $proposalTarget = $this->proposalTargetForDisplay($assignment, $applicant, $submission);

        return view('evaluations.submit', compact(
            'assignment',
            'submission',
            'applicant',
            'proposalTarget',
            'openRework'
        ));
    }

    /* =====================================================
     * AUTOSAVE / DRAFT
     * ===================================================== */
    public function saveScores(
        Request $request,
        EvaluationAssignment $assignment,
        FormSubmission $applicant
    ) {
        $this->assertAssignmentOwner($assignment);
        $this->assertApplicantBelongsToAssignment($assignment, $applicant);
        $this->assertApplicantReadyForEvaluation($assignment, $applicant);

        $assignment->loadMissing(['procurement', 'evaluation.sections.criteria']);
        abort_unless($assignment->procurement && $assignment->evaluation, 404);
        abort_if($assignment->procurement?->trashed(), 404);

        $criteriaPayload = $request->input('criteria', []);
        $sectionPayload = $request->input('sections', []);

        if (! is_array($criteriaPayload)) {
            throw ValidationException::withMessages([
                'criteria' => 'Invalid evaluation criteria payload.',
            ]);
        }

        if (! is_array($sectionPayload)) {
            throw ValidationException::withMessages([
                'sections' => 'Invalid evaluation section payload.',
            ]);
        }

        $submission = DB::transaction(function () use (
            $criteriaPayload,
            $sectionPayload,
            $assignment,
            $applicant
        ) {
            $this->lockEvaluationWorkItem($assignment, $applicant);
            $evaluation = $assignment->evaluation;

            $submission = $this->evaluationSubmissionForUpdate($assignment, $applicant);

            abort_unless(
                $this->submissionIsMutable($submission),
                403,
                'Submitted evaluations cannot be modified.'
            );

            $criteriaLookup = $evaluation->sections
                ->flatMap(fn ($s) => $s->criteria)
                ->keyBy('id');

            if (! $this->sectionsBelongToEvaluation($sectionPayload, $evaluation)) {
                throw ValidationException::withMessages([
                    'sections' => 'The selected section does not belong to this evaluation.',
                ]);
            }

            /* ---------- CRITERIA ---------- */
            foreach ($criteriaPayload as $criteriaId => $data) {

                $criteria = $criteriaLookup->get($criteriaId);
                if (! $criteria) {
                    throw ValidationException::withMessages([
                        'criteria' => 'The selected criterion does not belong to this evaluation.',
                    ]);
                }

                if ($evaluation->usesCategoricalDecisions()) {
                    if (! is_array($data)) {
                        throw ValidationException::withMessages([
                            "criteria.{$criteriaId}" => 'Invalid criterion response.',
                        ]);
                    }

                    $rawDecision = $data['decision'] ?? null;
                    $comment = $this->validatedDraftText(
                        $data['comment'] ?? null,
                        "criteria.{$criteriaId}.comment"
                    );

                    if (($rawDecision === null || $rawDecision === '') && $comment === null) {
                        $submission->criteriaScores()
                            ->where('evaluation_criteria_id', $criteriaId)
                            ->delete();

                        continue;
                    }

                    $decision = null;
                    if ($rawDecision !== null && $rawDecision !== '') {
                        $decision = filter_var($rawDecision, FILTER_VALIDATE_INT);
                        if ($decision === false
                            || ! array_key_exists($decision, $evaluation->decisionOptions())) {
                            throw ValidationException::withMessages([
                                "criteria.{$criteriaId}.decision" => 'Select a valid decision.',
                            ]);
                        }
                    }

                    $submission->criteriaScores()->updateOrCreate(
                        ['evaluation_criteria_id' => $criteriaId],
                        [
                            'submission_id' => $submission->id,
                            'decision' => $decision,
                            'comment' => $comment,
                            'score' => null,
                        ]
                    );

                    continue;
                }

                abort_unless($evaluation->usesNumericScoring(), 422, 'Unsupported evaluation type.');

                // SERVICES
                if ($data === null || $data === '') {
                    $submission->criteriaScores()
                        ->where('evaluation_criteria_id', $criteriaId)
                        ->delete();

                    continue;
                }

                if (! is_numeric($data)) {
                    throw ValidationException::withMessages([
                        "criteria.{$criteriaId}" => 'The score must be numeric.',
                    ]);
                }

                $score = round((float) $data, 2);
                if ($score < 0 || $score > (float) $criteria->max_score) {
                    throw ValidationException::withMessages([
                        "criteria.{$criteriaId}" => "Enter a score from 0 to {$criteria->max_score}.",
                    ]);
                }

                $submission->criteriaScores()->updateOrCreate(
                    ['evaluation_criteria_id' => $criteriaId],
                    [
                        'submission_id' => $submission->id,
                        'score' => $score,
                        'decision' => null,
                        'comment' => null,
                    ]
                );
            }

            /* ---------- SECTIONS ---------- */
            foreach ($sectionPayload as $sectionId => $data) {

                abort_unless(is_array($data), 422, 'Invalid evaluation section payload.');

                $submission->sectionScores()->updateOrCreate(
                    ['evaluation_section_id' => $sectionId],
                    [
                        'submission_id' => $submission->id,
                        'strengths' => $this->validatedDraftText(
                            $data['strengths'] ?? null,
                            "sections.{$sectionId}.strengths"
                        ),
                        'weaknesses' => $this->validatedDraftText(
                            $data['weaknesses'] ?? null,
                            "sections.{$sectionId}.weaknesses"
                        ),
                    ]
                );
            }

            $this->recalculateSubmissionTotals($submission, $evaluation);

            return $submission->fresh();
        });

        return response()->json([
            'success' => true,
            'saved_at' => now()->toIso8601String(),
            'overall_score' => $submission?->overall_score,
        ]);
    }

    /* =====================================================
     * FINAL SUBMIT
     * ===================================================== */
    public function submit(
        Request $request,
        EvaluationAssignment $assignment,
        FormSubmission $applicant
    ) {
        $this->assertAssignmentOwner($assignment);
        $this->assertApplicantBelongsToAssignment($assignment, $applicant);
        $this->assertApplicantReadyForEvaluation($assignment, $applicant);

        $assignment->loadMissing(['procurement', 'evaluation.sections.criteria']);
        abort_unless($assignment->procurement && $assignment->evaluation, 404);
        abort_if($assignment->procurement?->trashed(), 404);

        $evaluation = $assignment->evaluation;

        /* ===============================
         | VALIDATION (BASE)
         =============================== */
        $request->validate($this->finalSubmissionRules($evaluation), [
            'video.required' => 'Complete identity verification or attach a verification video before final submission.',
            'video.mimes' => 'The verification recording must be a WebM or MP4 video.',
            'criteria.*.required' => 'Every evaluation criterion must be completed.',
            'criteria.*.decision.required' => 'Select a decision for every criterion.',
            'criteria.*.comment.required' => 'Add an evidence comment for every decision.',
            'sections.*.strengths.required' => 'Summarise the strengths for every scored section.',
            'sections.*.weaknesses.required' => 'Summarise the weaknesses for every scored section.',
        ]);

        $this->assertCompleteSubmissionPayload($request, $evaluation);

        $submission = null;

        DB::transaction(function () use ($request, $assignment, $applicant, $evaluation, &$submission) {
            $this->lockEvaluationWorkItem($assignment, $applicant);

            /* ===============================
             | GET / CREATE SUBMISSION
             =============================== */
            $submission = $this->evaluationSubmissionForUpdate($assignment, $applicant);

            abort_if($submission->isSubmitted(), 403);
            $isReworkSubmission = $submission->workflow_status
                === EvaluationSubmission::WORKFLOW_REWORK_REQUESTED;

            /* ===============================
             | BUILD CRITERIA LOOKUP
             =============================== */
            $criteriaLookup = $evaluation->sections
                ->flatMap(fn ($section) => $section->criteria)
                ->keyBy('id');
            $sectionPayload = $request->input('sections', []);
            abort_unless(
                $this->sectionsBelongToEvaluation($sectionPayload, $evaluation),
                422,
                'The selected section does not belong to this evaluation.'
            );

            /* =====================================================
             | CRITERIA SCORING
             | GOODS → YES/NO + COMMENT
             | SERVICES → NUMERIC SCORE
             ===================================================== */
            foreach ($request->criteria as $criteriaId => $data) {

                $criteria = $criteriaLookup->get($criteriaId);
                abort_if(! $criteria, 422, 'Invalid evaluation criteria.');

                /* ---------- CATEGORICAL (GOODS / EOI) ---------- */
                if ($evaluation->usesCategoricalDecisions()) {

                    abort_if(! is_array($data), 422, 'Invalid criteria payload.');

                    abort_if(
                        ! array_key_exists('decision', $data),
                        422,
                        'Decision is required.'
                    );

                    $decision = filter_var($data['decision'], FILTER_VALIDATE_INT);
                    abort_if(
                        $decision === false
                        || ! array_key_exists($decision, $evaluation->decisionOptions()),
                        422,
                        'Invalid decision value.'
                    );

                    abort_if(
                        trim($data['comment'] ?? '') === '',
                        422,
                        'Comment is required.'
                    );

                    $submission->criteriaScores()->updateOrCreate(
                        ['evaluation_criteria_id' => $criteriaId],
                        [
                            'submission_id' => $submission->id,
                            'decision' => $decision,
                            'comment' => trim($data['comment']),
                            'score' => null, // Categorical evaluations do not store numeric scores.
                        ]
                    );

                    continue;
                }

                /* ---------- SERVICES ---------- */
                abort_unless($evaluation->usesNumericScoring(), 422, 'Unsupported evaluation type.');
                abort_if(! is_numeric($data), 422, 'Score must be numeric.');

                $score = round((float) $data, 2);

                abort_if(
                    $score < 0 || $score > $criteria->max_score,
                    422,
                    'Score exceeds allowed maximum.'
                );

                $submission->criteriaScores()->updateOrCreate(
                    ['evaluation_criteria_id' => $criteriaId],
                    [
                        'submission_id' => $submission->id,
                        'score' => $score,
                        'decision' => null,
                        'comment' => null,
                    ]
                );
            }

            /* =====================================================
             | SECTION SUMMARIES
             | Strengths & Weaknesses always required
             | Section score only for SERVICES
             ===================================================== */
            foreach ($sectionPayload as $sectionId => $data) {

                abort_unless(is_array($data), 422, 'Invalid evaluation section payload.');

                abort_if(
                    trim($data['strengths'] ?? '') === '',
                    422,
                    'Section strengths are required.'
                );

                abort_if(
                    trim($data['weaknesses'] ?? '') === '',
                    422,
                    'Section weaknesses are required.'
                );

                $submission->sectionScores()->updateOrCreate(
                    ['evaluation_section_id' => $sectionId],
                    [
                        'submission_id' => $submission->id,
                        'strengths' => trim($data['strengths']),
                        'weaknesses' => trim($data['weaknesses']),
                    ]
                );
            }

            /* ===============================
             | FINAL TOTALS
             =============================== */
            $this->recalculateSubmissionTotals($submission, $evaluation);

            /* ===============================
             | VIDEO + FINALIZE
             =============================== */
            // Store identity video on the default (private) disk. Access is via authorized routes only.
            $submission->video_path = $request->file('video')
                ->store("evaluation_proofs/{$submission->id}");

            $submission->submitted_at = now();
            $submission->workflow_status = EvaluationSubmission::WORKFLOW_SUBMITTED;
            $submission->revision_number = max(0, (int) $submission->revision_number) + 1;
            $submission->save();

            $this->synchronizeAssignmentStatus(
                $assignment,
                $isReworkSubmission ? (string) $submission->getKey() : null
            );

            if ($isReworkSubmission
                && ! app(EvaluationReworkService::class)
                    ->completeOpenRequest($submission, auth()->user())) {
                throw ValidationException::withMessages([
                    'rework' => 'This rework request is no longer active. Refresh your workspace before resubmitting.',
                ]);
            }

            if ($evaluation->isEoi() && ! $assignment->isTechnicalProposal()) {
                app(EoiQualificationService::class)->synchronizeApplicantStage($applicant);
            }
        }, 3);

        if ($submission) {
            $submission->load([
                'procurement',
                'applicant.submitter',
                'evaluation.sections.criteria',
                'criteriaScores.criteria',
                'sectionScores.section',
                'evaluator',
            ]);

            $admins = User::whereHas('role', function ($q) {
                $q->where('name', 'System Admin');
            })->get();

            $reportUsers = User::whereHas('permissions', function ($q) {
                $q->where('name', 'evaluations.view_all');
            })->orWhereHas('role.permissions', function ($q) {
                $q->where('name', 'evaluations.view_all');
            })->get();

            $recipients = $admins->pluck('email')
                ->merge($reportUsers->pluck('email'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $evaluatorEmail = $submission->evaluator?->email;
            if ($evaluatorEmail) {
                $recipients[] = $evaluatorEmail;
            }

            $recipients = array_values(array_unique(array_filter($recipients)));

            foreach ($recipients as $email) {
                try {
                    Mail::to($email)->send(new EvaluationCompleted($submission));
                } catch (\Throwable $exception) {
                    Log::error('Evaluation completion email failed after submission.', [
                        'evaluation_submission_id' => $submission->id,
                        'recipient' => $email,
                        'exception' => $exception->getMessage(),
                    ]);
                }
            }
        }

        $isThinkTankEvaluator = auth()->user()?->isThinkTankUser();

        return redirect()
            ->route(
                $isThinkTankEvaluator ? 'think-tank.evaluations.index' : 'my.eval.index'
            )
            ->with('success', 'Evaluation submitted successfully.');
    }

    /* =====================================================
     * VIEW
     * ===================================================== */
    public function view(EvaluationAssignment $assignment, FormSubmission $applicant)
    {
        abort_unless($this->canViewAssignment($assignment), 403);
        $this->assertApplicantBelongsToAssignment($assignment, $applicant);

        $assignment->loadMissing([
            'procurement',
            'evaluation.sections.criteria',
            'technicalProposalRound',
        ]);
        $applicant->loadMissing(['form', 'submitter', 'values']);
        abort_unless($assignment->procurement && $assignment->evaluation, 404);

        $submission = $this->evaluationSubmissionQuery($assignment, $applicant)
            ->with([
                'criteriaScores.criteria',
                'sectionScores.section',
                'evaluator',
                'technicalProposalCandidate.round',
                'technicalProposalSubmission.documents',
            ])
            ->whereNotNull('submitted_at')
            ->firstOrFail();
        $proposalTarget = $this->proposalTargetForDisplay($assignment, $applicant, $submission);

        return view('evaluations.view', compact(
            'assignment',
            'submission',
            'applicant',
            'proposalTarget'
        ));
    }

    /**
     * Stream the evaluator identity video securely from the private disk.
     */
    public function video(EvaluationAssignment $assignment, FormSubmission $applicant)
    {
        abort_unless($this->canViewAssignment($assignment), 403);
        $this->assertApplicantBelongsToAssignment($assignment, $applicant);

        $submission = $this->evaluationSubmissionQuery($assignment, $applicant)
            ->whereNotNull('submitted_at')
            ->firstOrFail();

        $path = (string) ($submission->video_path ?? '');
        abort_if($path === '', 404, 'Video not found.');

        $privateDisk = Storage::disk('local');

        if (! $privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            // Best-effort migration from public -> private.
            $stream = Storage::disk('public')->readStream($path);
            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                Storage::disk('public')->delete($path);
            }
        }

        abort_unless($privateDisk->exists($path), 404, 'Video file missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return $privateDisk->response($path, null, $headers);
    }

    public function proposalDocument(
        EvaluationAssignment $assignment,
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalSubmission $proposalSubmission,
        EoiTechnicalProposalDocument $document
    ): StreamedResponse {
        abort_unless($this->canViewAssignment($assignment), 403);
        abort_unless($assignment->isTechnicalProposal(), 404);

        $assignment->loadMissing(['procurement', 'evaluation', 'technicalProposalRound']);
        abort_unless(
            (string) $candidate->round_id === (string) $assignment->technical_proposal_round_id
                && (string) $proposalSubmission->candidate_id === (string) $candidate->getKey()
                && (string) $document->proposal_submission_id === (string) $proposalSubmission->getKey(),
            404
        );

        $applicant = FormSubmission::query()
            ->whereKey($candidate->form_submission_id)
            ->where('procurement_id', $assignment->procurement_id)
            ->firstOrFail();
        $this->assertApplicantBelongsToAssignment($assignment, $applicant);

        $snapshotProposalId = EvaluationSubmission::query()
            ->where('evaluation_assignment_id', $assignment->getKey())
            ->where('form_submission_id', $applicant->getKey())
            ->value('technical_proposal_submission_id');

        if (! $snapshotProposalId) {
            $target = $this->targetResolver()->targetForApplicant(
                $assignment->procurement,
                $assignment->evaluation,
                $applicant,
                EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL,
                $assignment->technicalProposalRound
            );
            $snapshotProposalId = $target ? $target['proposal_submission']?->getKey() : null;
        }

        abort_unless(
            (string) $snapshotProposalId === (string) $proposalSubmission->getKey()
                && str_starts_with(
                    (string) $document->file_path,
                    'eoi-technical-proposals/'.$assignment->technical_proposal_round_id
                        .'/candidates/'.$candidate->getKey().'/revisions/'
                ),
            404
        );
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_filename, [
            'Content-Type' => $document->mime_type,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function compare(EvaluationAssignment $assignment)
    {
        abort_unless(auth()->user()->can('evaluations.view_all'), 403);
        abort_unless($this->canViewAssignment($assignment), 403);

        $assignment->loadMissing('evaluation');
        abort_unless($assignment->evaluation, 404);

        $evaluation = $assignment->evaluation;
        $isNumeric = $evaluation->usesNumericScoring();
        $decisionOptions = $isNumeric ? [] : $evaluation->decisionOptions();
        $relations = ['evaluator', 'applicant'];

        if (! $isNumeric) {
            $relations[] = 'criteriaScores.criteria';
        }

        $coverageAssignments = EvaluationAssignment::query()
            ->where('evaluation_id', $assignment->evaluation_id)
            ->where('procurement_id', $assignment->procurement_id)
            ->where('workflow_stage', $assignment->workflow_stage ?: EvaluationAssignment::STAGE_APPLICATION)
            ->when(
                $assignment->technical_proposal_round_id,
                fn ($query) => $query->where('technical_proposal_round_id', $assignment->technical_proposal_round_id),
                fn ($query) => $query->whereNull('technical_proposal_round_id')
            )
            ->when($assignment->form_submission_id, function ($query) use ($assignment): void {
                $query->where(function ($scope) use ($assignment): void {
                    $scope->whereNull('form_submission_id')
                        ->orWhere('form_submission_id', $assignment->form_submission_id);
                });
            })
            ->get(['id', 'user_id']);

        $submissionQuery = EvaluationSubmission::with($relations)
            ->where('evaluation_id', $assignment->evaluation_id)
            ->where('procurement_id', $assignment->procurement_id)
            ->where(function ($query) use ($assignment, $coverageAssignments): void {
                $query->whereIn('evaluation_assignment_id', $coverageAssignments->pluck('id'));

                if (! $assignment->isTechnicalProposal()) {
                    $query->orWhere(function ($legacy) use ($assignment, $coverageAssignments): void {
                        $legacy->whereNull('evaluation_assignment_id')
                            ->where('evaluation_id', $assignment->evaluation_id)
                            ->where('procurement_id', $assignment->procurement_id)
                            ->whereIn('evaluator_id', $coverageAssignments->pluck('user_id'));
                    });
                }
            })
            ->whereNotNull('submitted_at');

        if ($assignment->form_submission_id) {
            $submissionQuery->where('form_submission_id', $assignment->form_submission_id);
        }

        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToEvaluationSubmissions($submissionQuery);
        }

        $groupedSubmissions = $submissionQuery
            ->get()
            ->groupBy('form_submission_id');

        if ($isNumeric) {
            $comparisons = $groupedSubmissions->map(function ($group) {
                $scores = $group->whereNotNull('overall_score')->pluck('overall_score');
                $average = $scores->count() ? round($scores->avg(), 2) : 0;
                $highest = $scores->count() ? round($scores->max(), 2) : 0;
                $lowest = $scores->count() ? round($scores->min(), 2) : 0;
                $first = $group->first();

                return [
                    'submission_code' => $first->applicant?->procurement_submission_code ?: (string) $first->form_submission_id,
                    'average' => $average,
                    'highest' => $highest,
                    'lowest' => $lowest,
                    'spread' => round($highest - $lowest, 2),
                    'evaluations' => $group->values(),
                ];
            })
                ->sortByDesc('average')
                ->values();
        } else {
            $comparisons = $groupedSubmissions
                ->map(function ($group) use ($evaluation, $decisionOptions) {
                    $first = $group->first();
                    $criteriaScores = $group->flatMap(
                        fn (EvaluationSubmission $submission) => $submission->criteriaScores
                    );

                    $decisionCounts = collect($decisionOptions)
                        ->map(function (string $fallbackLabel, int $decision) use ($criteriaScores, $evaluation) {
                            return [
                                'decision' => $decision,
                                'label' => $evaluation->decisionLabel($decision) ?? $fallbackLabel,
                                'count' => $criteriaScores
                                    ->filter(fn ($score) => $score->decision !== null
                                        && $score->decision !== ''
                                        && (int) $score->decision === $decision)
                                    ->count(),
                            ];
                        })
                        ->values();

                    return [
                        'submission_code' => $first->applicant?->procurement_submission_code ?: (string) $first->form_submission_id,
                        'decision_counts' => $decisionCounts,
                        'total_decisions' => $decisionCounts->sum('count'),
                        'evaluations' => $group->values(),
                    ];
                })
                ->values();
        }

        return view('evaluations.compare', compact(
            'assignment',
            'evaluation',
            'comparisons',
            'decisionOptions',
            'isNumeric'
        ));
    }

    public function compareRedirect()
    {
        $user = auth()->user();
        abort_unless($user->can('evaluations.view_all'), 403);

        $assignmentQuery = EvaluationAssignment::query()
            ->where('status', 'submitted')
            ->latest();

        if ($this->userHasAssignedPortfolioScope($user)) {
            $this->applyAssignedPortfolioScopeToEvaluationAssignments($assignmentQuery, $user);
        }

        if (! $user->can('evaluations.view_all')) {
            $assignmentQuery->where('user_id', $user->id);
        }

        $assignment = $assignmentQuery->first();

        if (! $assignment) {
            return redirect()
                ->route($user->isThinkTankUser() ? 'think-tank.evaluations.index' : 'my.eval.index')
                ->with('warning', 'No submitted evaluations are available for comparison yet.');
        }

        return redirect()->route('my.eval.compare', $assignment);
    }

    /* =====================================================
     * ACCESS CONTROL
     * ===================================================== */
    private function canViewAssignment(EvaluationAssignment $assignment): bool
    {
        $user = auth()->user();

        if ((string) $assignment->user_id === (string) $user->id) {
            return true;
        }

        if (! $user->can('evaluations.view_all')) {
            return false;
        }

        return ! $this->userHasAssignedPortfolioScope($user)
            || $this->evaluationAssignmentIsInAssignedPortfolio($assignment, $user);
    }

    private function assertAssignmentOwner(EvaluationAssignment $assignment): void
    {
        $user = auth()->user();

        abort_unless(
            (string) $assignment->user_id === (string) $user->id,
            403,
            'Only the assigned evaluator can modify this evaluation.'
        );

    }

    private function assertApplicantBelongsToAssignment(
        EvaluationAssignment $assignment,
        FormSubmission $applicant
    ): void {
        abort_unless(
            (string) $applicant->procurement_id === (string) $assignment->procurement_id,
            404
        );

        if ($assignment->form_submission_id) {
            abort_unless(
                (string) $assignment->form_submission_id === (string) $applicant->id,
                403,
                'This application is not part of your assignment.'
            );
        }
    }

    private function assertApplicantReadyForEvaluation(
        EvaluationAssignment $assignment,
        FormSubmission $applicant
    ): void {
        $assignment->loadMissing(['procurement', 'evaluation', 'technicalProposalRound']);

        abort_unless($assignment->procurement && $assignment->evaluation, 404);
        abort_unless(
            $this->targetResolver()->isEligible(
                $assignment->procurement,
                $assignment->evaluation,
                $applicant,
                $assignment->workflow_stage ?: EvaluationAssignment::STAGE_APPLICATION,
                $assignment->technicalProposalRound
            ),
            409,
            $assignment->isTechnicalProposal()
                ? 'This technical proposal is not in the qualified second-round shortlist.'
                : 'This application is not currently ready for evaluation. The EOI panel must be completed before Technical Evaluation can begin.'
        );
    }

    private function targetResolver(): EvaluationAssignmentTargetResolver
    {
        return app(EvaluationAssignmentTargetResolver::class);
    }

    private function assignmentTaskKey(
        EvaluationAssignment $assignment,
        FormSubmission $applicant
    ): string {
        return (string) $assignment->getKey().':'.(string) $applicant->getKey();
    }

    private function submissionRecordFromCollection(
        Collection $records,
        EvaluationAssignment $assignment,
        FormSubmission $applicant
    ): ?EvaluationSubmission {
        $linked = $records->first(fn (EvaluationSubmission $record): bool => (string) $record->evaluation_assignment_id === (string) $assignment->getKey()
            && (string) $record->form_submission_id === (string) $applicant->getKey()
        );

        if ($linked || $assignment->isTechnicalProposal()) {
            return $linked;
        }

        return $records->first(fn (EvaluationSubmission $record): bool => blank($record->evaluation_assignment_id)
            && (string) $record->evaluation_id === (string) $assignment->evaluation_id
            && (string) $record->procurement_id === (string) $assignment->procurement_id
            && (string) $record->evaluator_id === (string) $assignment->user_id
            && (string) $record->form_submission_id === (string) $applicant->getKey()
        );
    }

    private function evaluationSubmissionQuery(
        EvaluationAssignment $assignment,
        FormSubmission $applicant
    ): \Illuminate\Database\Eloquent\Builder {
        return EvaluationSubmission::query()
            ->where('form_submission_id', $applicant->getKey())
            ->where(function ($query) use ($assignment): void {
                $query->where('evaluation_assignment_id', $assignment->getKey());

                if (! $assignment->isTechnicalProposal()) {
                    $query->orWhere(function ($legacy) use ($assignment): void {
                        $legacy->whereNull('evaluation_assignment_id')
                            ->where('evaluation_id', $assignment->evaluation_id)
                            ->where('procurement_id', $assignment->procurement_id)
                            ->where('evaluator_id', $assignment->user_id);
                    });
                }
            })
            ->orderByRaw('CASE WHEN evaluation_assignment_id IS NULL THEN 1 ELSE 0 END');
    }

    private function evaluationSubmissionForUpdate(
        EvaluationAssignment $assignment,
        FormSubmission $applicant
    ): EvaluationSubmission {
        $assignment->loadMissing(['procurement', 'evaluation', 'technicalProposalRound']);
        abort_unless($assignment->procurement && $assignment->evaluation, 404);

        $target = $this->targetResolver()->assertEligible(
            $assignment->procurement,
            $assignment->evaluation,
            $applicant,
            $assignment->workflow_stage ?: EvaluationAssignment::STAGE_APPLICATION,
            $assignment->technicalProposalRound
        );

        $submission = EvaluationSubmission::query()
            ->where('evaluation_assignment_id', $assignment->getKey())
            ->where('form_submission_id', $applicant->getKey())
            ->lockForUpdate()
            ->first();

        if (! $submission && ! $assignment->isTechnicalProposal()) {
            $submission = EvaluationSubmission::query()
                ->whereNull('evaluation_assignment_id')
                ->where('evaluation_id', $assignment->evaluation_id)
                ->where('procurement_id', $assignment->procurement_id)
                ->where('evaluator_id', $assignment->user_id)
                ->where('form_submission_id', $applicant->getKey())
                ->lockForUpdate()
                ->first();

            if ($submission) {
                $submission->forceFill([
                    'evaluation_assignment_id' => $assignment->getKey(),
                ])->save();
            }
        }

        if (! $submission) {
            $submission = EvaluationSubmission::create([
                'evaluation_assignment_id' => $assignment->getKey(),
                'evaluation_id' => $assignment->evaluation_id,
                'procurement_id' => $assignment->procurement_id,
                'evaluator_id' => $assignment->user_id,
                'form_submission_id' => $applicant->getKey(),
                'technical_proposal_candidate_id' => $target['candidate']?->getKey(),
                'technical_proposal_submission_id' => $target['proposal_submission']?->getKey(),
            ]);
        }

        return $submission;
    }

    /** @return array<string, mixed>|null */
    private function proposalTargetForDisplay(
        EvaluationAssignment $assignment,
        FormSubmission $applicant,
        ?EvaluationSubmission $submission = null
    ): ?array {
        if (! $assignment->isTechnicalProposal()) {
            return null;
        }

        $assignment->loadMissing(['procurement', 'evaluation', 'technicalProposalRound']);

        if ($submission?->technical_proposal_candidate_id && $submission?->technical_proposal_submission_id) {
            $submission->loadMissing([
                'technicalProposalCandidate',
                'technicalProposalSubmission.documents',
            ]);

            return [
                'stage' => EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL,
                'applicant' => $applicant,
                'candidate' => $submission->technicalProposalCandidate,
                'proposal_submission' => $submission->technicalProposalSubmission,
                'round' => $assignment->technicalProposalRound,
            ];
        }

        if (! $assignment->procurement || ! $assignment->evaluation) {
            return null;
        }

        $target = $this->targetResolver()->targetForApplicant(
            $assignment->procurement,
            $assignment->evaluation,
            $applicant,
            EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL,
            $assignment->technicalProposalRound
        );

        if (! $target) {
            return null;
        }

        $target['proposal_submission']?->loadMissing('documents');

        return $target;
    }

    private function submissionIsMutable(EvaluationSubmission $submission): bool
    {
        return ! $submission->isSubmitted();
    }

    private function sectionsBelongToEvaluation(array $sections, Evaluation $evaluation): bool
    {
        $validSectionIds = $evaluation->sections
            ->pluck('id')
            ->map(fn ($sectionId): string => (string) $sectionId);

        foreach (array_keys($sections) as $sectionId) {
            if (! $validSectionIds->containsStrict((string) $sectionId)) {
                return false;
            }
        }

        return true;
    }

    private function finalSubmissionRules(Evaluation $evaluation): array
    {
        $rules = [
            'criteria' => ['required', 'array'],
            'sections' => ['required', 'array'],
            'video' => ['required', 'file', 'mimes:webm,mp4', 'max:20480'],
        ];

        foreach ($evaluation->sections->flatMap->criteria as $criterion) {
            $criterionKey = "criteria.{$criterion->id}";

            if ($evaluation->usesNumericScoring()) {
                $rules[$criterionKey] = [
                    'required',
                    'numeric',
                    'min:0',
                    'max:'.(float) $criterion->max_score,
                ];

                continue;
            }

            $rules[$criterionKey] = ['required', 'array'];
            $rules["{$criterionKey}.decision"] = [
                'required',
                'integer',
                Rule::in(array_keys($evaluation->decisionOptions())),
            ];
            $rules["{$criterionKey}.comment"] = ['required', 'string', 'max:5000'];
        }

        foreach ($evaluation->sections->filter(fn ($section) => $section->criteria->isNotEmpty()) as $section) {
            $rules["sections.{$section->id}"] = ['required', 'array'];
            $rules["sections.{$section->id}.strengths"] = ['required', 'string', 'max:5000'];
            $rules["sections.{$section->id}.weaknesses"] = ['required', 'string', 'max:5000'];
        }

        return $rules;
    }

    private function assertCompleteSubmissionPayload(Request $request, Evaluation $evaluation): void
    {
        $expectedCriteria = $evaluation->sections
            ->flatMap->criteria
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->sort()
            ->values();
        $providedCriteria = collect(array_keys($request->input('criteria', [])))
            ->map(fn ($id): string => (string) $id)
            ->sort()
            ->values();

        $expectedSections = $evaluation->sections
            ->filter(fn ($section) => $section->criteria->isNotEmpty())
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->sort()
            ->values();
        $providedSections = collect(array_keys($request->input('sections', [])))
            ->map(fn ($id): string => (string) $id)
            ->sort()
            ->values();

        $errors = [];
        if ($expectedCriteria->all() !== $providedCriteria->all()) {
            $errors['criteria'] = 'Complete every configured criterion and remove invalid criterion responses.';
        }
        if ($expectedSections->all() !== $providedSections->all()) {
            $errors['sections'] = 'Complete the strengths and weaknesses for every scored section.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validatedDraftText(mixed $value, string $field): ?string
    {
        if ($value !== null && ! is_scalar($value)) {
            throw ValidationException::withMessages([
                $field => 'Enter plain text only.',
            ]);
        }

        $trimmed = trim((string) ($value ?? ''));

        if (mb_strlen($trimmed) > 5000) {
            throw ValidationException::withMessages([
                $field => 'The text may not be greater than 5,000 characters.',
            ]);
        }

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Serialize writes for one evaluator/application work item. The advisory
     * lock covers overlapping whole-procurement and applicant-specific
     * assignments; the row lock also protects assignment status changes.
     */
    private function lockEvaluationWorkItem(
        EvaluationAssignment $assignment,
        FormSubmission $applicant
    ): void {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [implode(':', [
                $assignment->evaluation_id,
                $assignment->procurement_id,
                $assignment->user_id,
                $applicant->id,
            ])]);
        }

        Procurement::query()
            ->withTrashed()
            ->whereKey($assignment->procurement_id)
            ->lockForUpdate()
            ->firstOrFail();
        FormSubmission::query()
            ->whereKey($applicant->getKey())
            ->where('procurement_id', $assignment->procurement_id)
            ->lockForUpdate()
            ->firstOrFail();

        EvaluationAssignment::query()
            ->whereKey($assignment->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ($assignment->isTechnicalProposal()) {
            $candidate = EoiTechnicalProposalCandidate::query()
                ->where('round_id', $assignment->technical_proposal_round_id)
                ->where('form_submission_id', $applicant->getKey())
                ->lockForUpdate()
                ->first();

            abort_unless(
                $candidate
                    && $assignment->technicalProposalRound
                    && $this->targetResolver()->technicalCandidateIsEligible(
                        $candidate,
                        $assignment->technicalProposalRound,
                        $assignment->procurement
                    ),
                409,
                'This technical proposal is no longer eligible for evaluation.'
            );
        }
    }

    private function recalculateSubmissionTotals(
        EvaluationSubmission $submission,
        Evaluation $evaluation
    ): void {
        $evaluation->loadMissing('sections.criteria');
        $sections = $evaluation->sections;

        foreach ($sections as $section) {
            $sectionIds = collect([(string) $section->id]);

            do {
                $before = $sectionIds->count();
                $childIds = $sections
                    ->filter(fn ($candidate) => filled($candidate->parent_section_id)
                        && $sectionIds->contains((string) $candidate->parent_section_id))
                    ->pluck('id')
                    ->map(fn ($id): string => (string) $id);
                $sectionIds = $sectionIds->merge($childIds)->unique()->values();
            } while ($sectionIds->count() > $before);

            $criteriaIds = $sections
                ->filter(fn ($candidate) => $sectionIds->contains((string) $candidate->id))
                ->flatMap->criteria
                ->pluck('id');

            $sectionScore = $evaluation->usesNumericScoring()
                ? round((float) $submission->criteriaScores()
                    ->whereIn('evaluation_criteria_id', $criteriaIds)
                    ->sum('score'), 2)
                : null;

            $submission->sectionScores()->updateOrCreate(
                ['evaluation_section_id' => $section->id],
                [
                    'submission_id' => $submission->id,
                    'section_score' => $sectionScore,
                ]
            );
        }

        $submission->recalculateTotals();
    }

    private function synchronizeAssignmentStatus(
        EvaluationAssignment $assignment,
        ?string $completingReworkSubmissionId = null
    ): void {
        $assignment->loadMissing(['evaluation', 'procurement', 'technicalProposalRound']);

        $submissionIds = $this->targetResolver()
            ->targetsForAssignment($assignment)
            ->pluck('id');

        $coveredSubmissions = EvaluationSubmission::query()
            ->where(function ($query) use ($assignment): void {
                $query->where('evaluation_assignment_id', $assignment->getKey());

                if (! $assignment->isTechnicalProposal()) {
                    $query->orWhere(function ($legacy) use ($assignment): void {
                        $legacy->whereNull('evaluation_assignment_id')
                            ->where('evaluation_id', $assignment->evaluation_id)
                            ->where('procurement_id', $assignment->procurement_id)
                            ->where('evaluator_id', $assignment->user_id);
                    });
                }
            })
            ->whereIn('form_submission_id', $submissionIds);
        $completedCount = $submissionIds->isEmpty()
            ? 0
            : (clone $coveredSubmissions)
                ->whereNotNull('submitted_at')
                ->distinct('form_submission_id')
                ->count('form_submission_id');
        $remainingReworkSubmissions = (clone $coveredSubmissions)
            ->when(
                $completingReworkSubmissionId,
                fn ($query) => $query->whereKeyNot($completingReworkSubmissionId)
            );
        $hasOpenRework = $submissionIds->isNotEmpty()
            && $remainingReworkSubmissions
                ->whereHas('reworkRequests', fn ($query) => $query
                    ->where('status', ReworkRequest::STATUS_PENDING))
                ->exists();

        $assignment->update([
            'status' => match (true) {
                $hasOpenRework => 'rework',
                $submissionIds->isNotEmpty() && $completedCount >= $submissionIds->count() => 'submitted',
                default => 'assigned',
            },
        ]);
    }

    private function applicationsForAssignment(
        EvaluationAssignment $assignment
    ): Collection {
        return $this->targetResolver()->targetsForAssignment($assignment)->values();
    }

    public function panelHub()
    {
        $user = auth()->user();

        /* ===============================
         | LOAD ASSIGNMENTS USER CAN SEE
         =============================== */
        $assignments = EvaluationAssignment::with([
            'procurement',
            'evaluation',
        ])
            ->when(
                $this->userHasAssignedPortfolioScope($user),
                fn ($q) => $this->applyAssignedPortfolioScopeToEvaluationAssignments($q, $user)
            )
            ->when(
                ! $user->can('evaluations.view_all'),
                fn ($q) => $q->where('user_id', $user->id)
            )
            ->get();

        /* ===============================
         | UNIQUE PROCUREMENTS
         =============================== */
        $procurements = $assignments
            ->pluck('procurement')
            ->unique('id')
            ->values();

        /* ===============================
         | FORM SUBMISSIONS (APPLICANTS)
         =============================== */
        $formSubmissions = FormSubmission::with('submitter')
            ->whereIn('procurement_id', $procurements->pluck('id'))
            ->get();

        $submissions = $formSubmissions
            ->groupBy('procurement_id')
            ->map(fn ($items) => $items->values());

        /* ===============================
         | EVALUATION SUBMISSIONS (FULL MODELS)
         =============================== */
        $evaluationSubmissions = EvaluationSubmission::with([
            'evaluator',
            'evaluation',
            'criteriaScores.criteria',
            'sectionScores.section',
        ])
            ->whereIn('form_submission_id', $formSubmissions->pluck('id'))
            ->whereNotNull('submitted_at')
            ->get();

        $evaluations = $evaluationSubmissions
            ->groupBy('form_submission_id')
            ->map(fn ($items) => $items->values());

        return view('evaluations.panel.index', compact(
            'procurements',
            'submissions',
            'evaluations'
        ));
    }
}
