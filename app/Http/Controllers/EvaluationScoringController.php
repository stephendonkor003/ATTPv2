<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationCriteriaScore;
use App\Models\EvaluationSection;
use App\Models\EvaluationSectionScore;
use App\Models\EvaluationSubmission;
use Illuminate\Http\Request;

class EvaluationScoringController extends Controller
{
    use ScopesAssignedPortfolios;

    public function saveCriteriaScore(Request $request)
    {
        $request->validate([
            'submission_id' => 'required|exists:evaluation_submissions,id',
            'evaluation_criteria_id' => 'required|exists:evaluation_criteria,id',
        ]);

        $submission = EvaluationSubmission::with(['procurement', 'evaluation'])
            ->findOrFail($request->submission_id);
        $this->assertScoringSubmissionAccessible($submission);

        $criteria = EvaluationCriteria::with('section.evaluation')
            ->findOrFail($request->evaluation_criteria_id);
        $this->assertCriteriaBelongsToSubmissionEvaluation($criteria, $submission);

        $evaluation = $criteria->section->evaluation;

        if ($evaluation->type === 'goods') {
            $request->validate([
                'decision' => 'required|boolean',
                'comment' => 'required|string',
            ]);

            EvaluationCriteriaScore::updateOrCreate(
                [
                    'submission_id' => $submission->id,
                    'evaluation_criteria_id' => $criteria->id,
                ],
                [
                    'decision' => (int) $request->decision,
                    'comment' => $request->comment,
                    'score' => null,
                ]
            );

            EvaluationSectionScore::firstOrCreate([
                'submission_id' => $submission->id,
                'evaluation_section_id' => $criteria->section->id,
            ]);

            return response()->json(['success' => true]);
        }

        $request->validate([
            'score' => 'required|numeric|min:0',
        ]);

        if ($request->score > $criteria->max_score) {
            return response()->json(['error' => 'Score exceeds max'], 422);
        }

        EvaluationCriteriaScore::updateOrCreate(
            [
                'submission_id' => $submission->id,
                'evaluation_criteria_id' => $criteria->id,
            ],
            ['score' => round($request->score, 2)]
        );

        $sectionScore = EvaluationSectionScore::firstOrCreate(
            [
                'submission_id' => $submission->id,
                'evaluation_section_id' => $criteria->section->id,
            ],
            ['section_score' => 0]
        );

        $sectionScore->recalculateSectionScore();

        return response()->json([
            'success' => true,
            'section_score' => $sectionScore->section_score,
            'overall_score' => $sectionScore->submission->overall_score,
        ]);
    }

    public function saveSectionNotes(Request $request)
    {
        $request->validate([
            'submission_id' => 'required|exists:evaluation_submissions,id',
            'evaluation_section_id' => 'required|exists:evaluation_sections,id',
        ]);

        $submission = EvaluationSubmission::with(['procurement', 'evaluation'])
            ->findOrFail($request->submission_id);
        $this->assertScoringSubmissionAccessible($submission);

        $section = EvaluationSection::with('evaluation')
            ->findOrFail($request->evaluation_section_id);
        abort_unless((string) $section->evaluation_id === (string) $submission->evaluation_id, 422);

        EvaluationSectionScore::updateOrCreate(
            [
                'submission_id' => $submission->id,
                'evaluation_section_id' => $section->id,
            ],
            [
                'strengths' => $request->strengths,
                'weaknesses' => $request->weaknesses,
            ]
        );

        return response()->json(['success' => true]);
    }

    private function assertScoringSubmissionAccessible(EvaluationSubmission $submission): void
    {
        $user = auth()->user();

        if ($this->userHasAssignedPortfolioScope($user)) {
            abort_unless(
                $this->evaluationSubmissionIsInAssignedPortfolio($submission, $user),
                403,
                'This evaluation submission is not assigned to your portfolio.'
            );
        }

        abort_unless(
            $user->can('evaluations.view_all') || (string) $submission->evaluator_id === (string) $user->id,
            403
        );
    }

    private function assertCriteriaBelongsToSubmissionEvaluation(
        EvaluationCriteria $criteria,
        EvaluationSubmission $submission
    ): void {
        abort_unless(
            (string) $criteria->section?->evaluation_id === (string) $submission->evaluation_id,
            422,
            'The selected criterion does not belong to this evaluation.'
        );
    }
}
