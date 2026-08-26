<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Mail\EvaluationCompleted;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            abort_unless(
                (string) $assignment->user_id === (string) $user->id
                    || $user->can('evaluations.view_all'),
                403
            );
        }

        $assignments = EvaluationAssignment::query()
            ->with(['procurement', 'evaluation'])
            ->whereHas('procurement', fn ($query) => $query->whereNull('procurements.deleted_at'))
            ->whereHas('evaluation')
            ->when(
                $this->userHasAssignedPortfolioScope($user),
                fn ($q) => $this->applyAssignedPortfolioScopeToEvaluationAssignments($q, $user)
            )
            ->when(
                $assignment,
                fn ($query) => $query->whereKey($assignment->getKey()),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->latest('assigned_at')
            ->latest()
            ->get();

        $submissionIds = $assignments
            ->whereNotNull('form_submission_id')
            ->pluck('form_submission_id')
            ->unique();

        $procurementIds = $assignments
            ->whereNull('form_submission_id')
            ->pluck('procurement_id')
            ->unique();

        $submissions = ($submissionIds->isEmpty() && $procurementIds->isEmpty())
            ? collect()
            : FormSubmission::with(['form', 'submitter', 'values'])
                ->where(fn ($query) => $query
                    ->where('status', FormSubmission::STATUS_SUBMITTED)
                    ->orWhereNull('status'))
                ->where(function ($q) use ($submissionIds, $procurementIds) {
                    if ($submissionIds->isNotEmpty()) {
                        $q->orWhereIn('id', $submissionIds);
                    }
                    if ($procurementIds->isNotEmpty()) {
                        $q->orWhereIn('procurement_id', $procurementIds);
                    }
                })
                ->latest()
                ->get();

        $evaluationIds = $assignments->pluck('evaluation_id')->filter()->unique();
        $evaluatorIds = $assignments->pluck('user_id')->filter()->unique();
        $allProcurementIds = $assignments->pluck('procurement_id')->filter()->unique();
        $allSubmissionIds = $submissions->pluck('id');
        $taskTupleKeys = $assignments
            ->flatMap(function (EvaluationAssignment $item) use ($submissions) {
                $applications = $item->form_submission_id
                    ? $submissions->where('id', $item->form_submission_id)
                    : $submissions->where('procurement_id', $item->procurement_id);

                return $applications->map(fn (FormSubmission $application): string => implode(':', [
                    $item->user_id,
                    $item->evaluation_id,
                    $item->procurement_id,
                    $application->id,
                ]));
            })
            ->flip();

        $evaluationSubmissions = ($evaluationIds->isEmpty()
            || $evaluatorIds->isEmpty()
            || $allProcurementIds->isEmpty()
            || $allSubmissionIds->isEmpty())
            ? collect()
            : EvaluationSubmission::query()
                ->whereIn('evaluator_id', $evaluatorIds)
                ->whereIn('evaluation_id', $evaluationIds)
                ->whereIn('procurement_id', $allProcurementIds)
                ->whereIn('form_submission_id', $allSubmissionIds)
                ->get()
                ->filter(fn (EvaluationSubmission $submission): bool => $taskTupleKeys->has(implode(':', [
                    $submission->evaluator_id,
                    $submission->evaluation_id,
                    $submission->procurement_id,
                    $submission->form_submission_id,
                ])))
                ->keyBy(fn (EvaluationSubmission $submission): string => implode(':', [
                    $submission->evaluation_id,
                    $submission->procurement_id,
                    $submission->form_submission_id,
                ]));

        $taskCount = $assignments->sum(function (EvaluationAssignment $item) use ($submissions): int {
            return $item->form_submission_id
                ? $submissions->where('id', $item->form_submission_id)->count()
                : $submissions->where('procurement_id', $item->procurement_id)->count();
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
            'pending' => max(0, $taskCount - $completedCount),
        ];

        return view('evaluations.my', compact(
            'assignments',
            'submissions',
            'evaluationSubmissions',
            'stats'
        ));
    }

    /* =====================================================
     * START / CONTINUE EVALUATION
     * ===================================================== */
    public function start(EvaluationAssignment $assignment, FormSubmission $applicant)
    {
        $this->assertAssignmentOwner($assignment);
        $this->assertApplicantBelongsToAssignment($assignment, $applicant);
        $this->assertApplicantReadyForEvaluation($applicant);

        $assignment->loadMissing([
            'procurement',
            'evaluation.sections.criteria',
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

            return EvaluationSubmission::firstOrCreate([
                'evaluation_id' => $assignment->evaluation_id,
                'procurement_id' => $assignment->procurement_id,
                'evaluator_id' => $assignment->user_id,
                'form_submission_id' => $applicant->id,
            ]);
        });

        if ($submission->isSubmitted()) {
            return redirect()->route('my.eval.view', [$assignment, $applicant]);
        }

        $submission->load(['criteriaScores', 'sectionScores']);

        return view('evaluations.submit', compact(
            'assignment',
            'submission',
            'applicant'
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
        $this->assertApplicantReadyForEvaluation($applicant);

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

            $submission = EvaluationSubmission::firstOrCreate([
                'evaluation_id' => $evaluation->id,
                'procurement_id' => $assignment->procurement_id,
                'evaluator_id' => $assignment->user_id,
                'form_submission_id' => $applicant->id,
            ]);

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
        $this->assertApplicantReadyForEvaluation($applicant);

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
            $submission = EvaluationSubmission::firstOrCreate([
                'evaluation_id' => $evaluation->id,
                'procurement_id' => $assignment->procurement_id,
                'evaluator_id' => $assignment->user_id,
                'form_submission_id' => $applicant->id,
            ]);

            abort_if($submission->isSubmitted(), 403);

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
            $submission->save();

            $this->synchronizeAssignmentStatus($assignment);
        });

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

        $assignment->loadMissing(['procurement', 'evaluation.sections.criteria']);
        $applicant->loadMissing(['form', 'submitter', 'values']);
        abort_unless($assignment->procurement && $assignment->evaluation, 404);

        $submission = EvaluationSubmission::with([
            'criteriaScores.criteria',
            'sectionScores.section',
            'evaluator',
        ])
            ->where([
                'evaluation_id' => $assignment->evaluation_id,
                'procurement_id' => $assignment->procurement_id,
                'evaluator_id' => $assignment->user_id,
                'form_submission_id' => $applicant->id,
            ])
            ->whereNotNull('submitted_at')
            ->firstOrFail();

        return view('evaluations.view', compact(
            'assignment',
            'submission',
            'applicant'
        ));
    }

    /**
     * Stream the evaluator identity video securely from the private disk.
     */
    public function video(EvaluationAssignment $assignment, FormSubmission $applicant)
    {
        abort_unless($this->canViewAssignment($assignment), 403);
        $this->assertApplicantBelongsToAssignment($assignment, $applicant);

        $submission = EvaluationSubmission::where([
            'evaluation_id' => $assignment->evaluation_id,
            'procurement_id' => $assignment->procurement_id,
            'evaluator_id' => $assignment->user_id,
            'form_submission_id' => $applicant->id,
        ])->whereNotNull('submitted_at')->firstOrFail();

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

        $submissionQuery = EvaluationSubmission::with($relations)
            ->where('evaluation_id', $assignment->evaluation_id)
            ->where('procurement_id', $assignment->procurement_id)
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

        if ($this->userHasAssignedPortfolioScope($user)
            && ! $this->evaluationAssignmentIsInAssignedPortfolio($assignment, $user)) {
            return false;
        }

        return $user->can('evaluations.view_all')
            || (string) $assignment->user_id === (string) $user->id;
    }

    private function assertAssignmentOwner(EvaluationAssignment $assignment): void
    {
        $user = auth()->user();

        abort_unless(
            (string) $assignment->user_id === (string) $user->id,
            403,
            'Only the assigned evaluator can modify this evaluation.'
        );

        if ($this->userHasAssignedPortfolioScope($user)) {
            abort_unless(
                $this->evaluationAssignmentIsInAssignedPortfolio($assignment, $user),
                403,
                'This evaluation is outside your assigned portfolio.'
            );
        }
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

    private function assertApplicantReadyForEvaluation(FormSubmission $applicant): void
    {
        abort_unless(
            $applicant->status === null || $applicant->status === FormSubmission::STATUS_SUBMITTED,
            409,
            'This application is not currently ready for evaluation.'
        );
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

        EvaluationAssignment::query()
            ->whereKey($assignment->getKey())
            ->lockForUpdate()
            ->firstOrFail();
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

    private function synchronizeAssignmentStatus(EvaluationAssignment $assignment): void
    {
        $submissionIds = FormSubmission::query()
            ->where('procurement_id', $assignment->procurement_id)
            ->where(fn ($query) => $query
                ->where('status', FormSubmission::STATUS_SUBMITTED)
                ->orWhereNull('status'))
            ->when(
                $assignment->form_submission_id,
                fn ($query) => $query->whereKey($assignment->form_submission_id)
            )
            ->pluck('id');

        $completedCount = $submissionIds->isEmpty()
            ? 0
            : EvaluationSubmission::query()
                ->where('evaluation_id', $assignment->evaluation_id)
                ->where('procurement_id', $assignment->procurement_id)
                ->where('evaluator_id', $assignment->user_id)
                ->whereIn('form_submission_id', $submissionIds)
                ->whereNotNull('submitted_at')
                ->distinct('form_submission_id')
                ->count('form_submission_id');

        $assignment->update([
            'status' => $submissionIds->isNotEmpty() && $completedCount >= $submissionIds->count()
                ? 'submitted'
                : 'assigned',
        ]);
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
