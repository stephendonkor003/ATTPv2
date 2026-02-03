<?php

namespace App\Http\Controllers;

use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\User;
use App\Mail\EvaluationCompleted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class EvaluationSubmissionController extends Controller
{
    /* =====================================================
     * EVALUATION HUB
     * ===================================================== */
    public function myEvaluations()
    {
        $user = auth()->user();

        $assignments = EvaluationAssignment::with(['procurement', 'evaluation'])
            ->when(
                !$user->can('evaluations.view_all'),
                fn ($q) => $q->where('user_id', $user->id)
            )
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

        $submissions = FormSubmission::with(['form', 'submitter'])
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

        return view('evaluations.my', compact('assignments', 'submissions'));
    }

    /* =====================================================
     * START / CONTINUE EVALUATION
     * ===================================================== */
    public function start(EvaluationAssignment $assignment, FormSubmission $applicant)
    {
        abort_if(!$this->canAccessAssignment($assignment), 403);
        abort_if($applicant->procurement_id !== $assignment->procurement_id, 404);
        if ($assignment->form_submission_id) {
            abort_if($assignment->form_submission_id !== $applicant->id, 403);
        }

        $submission = EvaluationSubmission::firstOrCreate([
            'evaluation_id'      => $assignment->evaluation_id,
            'procurement_id'     => $assignment->procurement_id,
            'evaluator_id'       => auth()->id(),
            'form_submission_id' => $applicant->id,
        ]);

        $applicants = FormSubmission::with('submitter')
            ->where('procurement_id', $assignment->procurement_id)
            ->get();

        return view('evaluations.submit', compact(
            'assignment',
            'submission',
            'applicant',
            'applicants'
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
        abort_if(!$this->canAccessAssignment($assignment), 403);
        abort_if($applicant->procurement_id !== $assignment->procurement_id, 404);
        if ($assignment->form_submission_id) {
            abort_if($assignment->form_submission_id !== $applicant->id, 403);
        }

        DB::transaction(function () use ($request, $assignment, $applicant) {

            $evaluation = $assignment->evaluation;

            $submission = EvaluationSubmission::firstOrCreate([
                'evaluation_id'      => $evaluation->id,
                'procurement_id'     => $assignment->procurement_id,
                'evaluator_id'       => auth()->id(),
                'form_submission_id' => $applicant->id,
            ]);

            $criteriaLookup = $evaluation->sections
                ->flatMap(fn ($s) => $s->criteria)
                ->keyBy('id');

            /* ---------- CRITERIA ---------- */
            foreach ($request->input('criteria', []) as $criteriaId => $data) {

                $criteria = $criteriaLookup->get($criteriaId);
                abort_if(!$criteria, 422);

                if ($evaluation->type === 'goods') {

                    if (!isset($data['decision'])) {
                        continue; // allow partial autosave
                    }

                    $submission->criteriaScores()->updateOrCreate(
                        ['evaluation_criteria_id' => $criteriaId],
                        [
                            'submission_id' => $submission->id,
                            'decision'      => (int) $data['decision'],
                            'comment'       => $data['comment'] ?? null,
                            'score'         => null,
                        ]
                    );

                    continue;
                }

                // SERVICES
                if (!is_numeric($data)) {
                    continue;
                }

                abort_if($data > $criteria->max_score, 422);

                $submission->criteriaScores()->updateOrCreate(
                    ['evaluation_criteria_id' => $criteriaId],
                    [
                        'submission_id' => $submission->id,
                        'score'         => round((float) $data, 2),
                    ]
                );
            }

            /* ---------- SECTIONS ---------- */
            foreach ($request->input('sections', []) as $sectionId => $data) {

                $submission->sectionScores()->updateOrCreate(
                    ['evaluation_section_id' => $sectionId],
                    [
                        'submission_id' => $submission->id,
                        'section_score' => $evaluation->type === 'services'
                            ? round((float) ($data['score'] ?? 0), 2)
                            : null,
                        'strengths'  => $data['strengths'] ?? null,
                        'weaknesses' => $data['weaknesses'] ?? null,
                    ]
                );
            }

            $submission->recalculateTotals();
        });

        return response()->json(['success' => true]);
    }

    /* =====================================================
     * FINAL SUBMIT
     * ===================================================== */
   public function submit(
    Request $request,
    EvaluationAssignment $assignment,
    FormSubmission $applicant
) {
    /* ===============================
     | ACCESS CONTROL
     =============================== */
    abort_if(!$this->canAccessAssignment($assignment), 403);
    abort_if(
        $applicant->procurement_id !== $assignment->procurement_id,
        404
    );
    if ($assignment->form_submission_id) {
        abort_if($assignment->form_submission_id !== $applicant->id, 403);
    }

    $evaluation = $assignment->evaluation;

    /* ===============================
     | VALIDATION (BASE)
     =============================== */
    $request->validate([
        'criteria' => 'required|array',
        'sections' => 'required|array',
        'video'    => 'required|file|mimes:webm,mp4|max:20480',
    ]);

    $submission = null;

    DB::transaction(function () use ($request, $assignment, $applicant, $evaluation, &$submission) {

        /* ===============================
         | GET / CREATE SUBMISSION
         =============================== */
        $submission = EvaluationSubmission::firstOrCreate([
            'evaluation_id'      => $evaluation->id,
            'procurement_id'     => $assignment->procurement_id,
            'evaluator_id'       => auth()->id(),
            'form_submission_id' => $applicant->id,
        ]);

        abort_if($submission->isSubmitted(), 403);

        /* ===============================
         | BUILD CRITERIA LOOKUP
         =============================== */
        $criteriaLookup = $evaluation->sections
            ->flatMap(fn ($section) => $section->criteria)
            ->keyBy('id');

        /* =====================================================
         | CRITERIA SCORING
         | GOODS → YES/NO + COMMENT
         | SERVICES → NUMERIC SCORE
         ===================================================== */
        foreach ($request->criteria as $criteriaId => $data) {

            $criteria = $criteriaLookup->get($criteriaId);
            abort_if(!$criteria, 422, 'Invalid evaluation criteria.');

            /* ---------- GOODS ---------- */
            if ($evaluation->type === 'goods') {

                abort_if(!is_array($data), 422, 'Invalid criteria payload.');

                abort_if(
                    !array_key_exists('decision', $data),
                    422,
                    'Decision is required.'
                );

                abort_if(
                    !in_array((int) $data['decision'], [0, 1], true),
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
                        'decision'      => (int) $data['decision'],
                        'comment'       => trim($data['comment']),
                        'score'         => null, // ✅ goods do NOT store score
                    ]
                );

                continue;
            }

            /* ---------- SERVICES ---------- */
            abort_if(!is_numeric($data), 422, 'Score must be numeric.');

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
                    'score'         => $score,
                    'decision'      => null,
                    'comment'       => null,
                ]
            );
        }

        /* =====================================================
         | SECTION SUMMARIES
         | Strengths & Weaknesses always required
         | Section score only for SERVICES
         ===================================================== */
        foreach ($request->sections as $sectionId => $data) {

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
                    'section_score' => $evaluation->type === 'services'
                        ? round((float) ($data['score'] ?? 0), 2)
                        : null,
                    'strengths'  => trim($data['strengths']),
                    'weaknesses' => trim($data['weaknesses']),
                ]
            );
        }

        /* ===============================
         | FINAL TOTALS
         =============================== */
        $submission->recalculateTotals();

        /* ===============================
         | VIDEO + FINALIZE
         =============================== */
        // Store identity video on the default (private) disk. Access is via authorized routes only.
        $submission->video_path = $request->file('video')
            ->store("evaluation_proofs/{$submission->id}");

        $submission->submitted_at = now();
        $submission->save();
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
            $q->where('name', 'prescreening.reports.view_all');
        })->orWhereHas('role.permissions', function ($q) {
            $q->where('name', 'prescreening.reports.view_all');
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
            Mail::to($email)->send(new EvaluationCompleted($submission));
        }
    }

    return redirect()
        ->route('eval.assign.applicants', $assignment)
        ->with('success', 'Evaluation submitted successfully.');
}


    /* =====================================================
     * VIEW
     * ===================================================== */
    public function view(EvaluationAssignment $assignment, FormSubmission $applicant)
    {
        abort_if(!$this->canAccessAssignment($assignment), 403);
        if ($assignment->form_submission_id) {
            abort_if($assignment->form_submission_id !== $applicant->id, 403);
        }

        $submission = EvaluationSubmission::with([
            'criteriaScores.criteria',
            'sectionScores.section',
            'evaluator',
        ])
        ->where([
            'evaluation_id'      => $assignment->evaluation_id,
            'procurement_id'     => $assignment->procurement_id,
            'evaluator_id'       => auth()->id(),
            'form_submission_id' => $applicant->id,
        ])
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
        abort_if(!$this->canAccessAssignment($assignment), 403);
        abort_if($applicant->procurement_id !== $assignment->procurement_id, 404);

        if ($assignment->form_submission_id) {
            abort_if($assignment->form_submission_id !== $applicant->id, 403);
        }

        $submission = EvaluationSubmission::where([
            'evaluation_id'      => $assignment->evaluation_id,
            'procurement_id'     => $assignment->procurement_id,
            'evaluator_id'       => auth()->id(),
            'form_submission_id' => $applicant->id,
        ])->firstOrFail();

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

    /* =====================================================
     * ACCESS CONTROL
     * ===================================================== */
    private function canAccessAssignment(EvaluationAssignment $assignment): bool
    {
        $user = auth()->user();

        return $user->can('evaluations.view_all')
            || $assignment->user_id === $user->id;
    }


  public function panelHub()
{
    $user = auth()->user();

    /* ===============================
     | LOAD ASSIGNMENTS USER CAN SEE
     =============================== */
    $assignments = EvaluationAssignment::with([
            'procurement',
            'evaluation'
        ])
        ->when(
            !$user->can('evaluations.view_all'),
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
            'sectionScores.section'
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
