<?php

namespace App\Http\Controllers;

use App\Models\EvaluationSubmission;
use App\Models\Procurement;
use Barryvdh\DomPDF\Facade\Pdf;

class EvaluationReportController extends Controller
{
    public function index()
    {
        $procurements = Procurement::orderBy('title')->get();

        $submissions = EvaluationSubmission::with([
                'procurement',
                'applicant.submitter',
                'evaluation',
                'evaluator',
            ])
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->get();

        return view('reports.evaluations.index', compact('procurements', 'submissions'));
    }

    public function submission(EvaluationSubmission $submission)
    {
        $submission->load([
            'procurement',
            'applicant.submitter',
            'evaluation.sections.criteria',
            'criteriaScores.criteria',
            'sectionScores.section',
            'evaluator',
        ]);

        $overallMax = $this->overallMax($submission);

        return view('reports.evaluations.submission', compact('submission', 'overallMax'));
    }

    public function submissionPdf(EvaluationSubmission $submission)
    {
        $submission->load([
            'procurement',
            'applicant.submitter',
            'evaluation.sections.criteria',
            'criteriaScores.criteria',
            'sectionScores.section',
            'evaluator',
        ]);

        $overallMax = $this->overallMax($submission);

        $pdf = Pdf::loadView('reports.evaluations.pdf.submission', compact('submission', 'overallMax'));

        return $pdf->download('evaluation-submission-' . $submission->id . '.pdf');
    }

    public function procurement(Procurement $procurement)
    {
        $submissions = EvaluationSubmission::with([
                'procurement',
                'applicant.submitter',
                'evaluation.sections.criteria',
                'criteriaScores',
                'sectionScores',
                'evaluator',
            ])
            ->where('procurement_id', $procurement->id)
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->get();

        $summary = $this->buildSummary($submissions);
        $rankings = $this->buildApplicantRankings($submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);
        $evaluationStats = $this->buildEvaluationStats($submissions);

        return view('reports.evaluations.procurement', compact(
            'procurement',
            'submissions',
            'summary',
            'rankings',
            'evaluatorBreakdown',
            'evaluationStats'
        ));
    }

    public function procurementPdf(Procurement $procurement)
    {
        $submissions = EvaluationSubmission::with([
                'procurement',
                'applicant.submitter',
                'evaluation.sections.criteria',
                'criteriaScores',
                'sectionScores',
                'evaluator',
            ])
            ->where('procurement_id', $procurement->id)
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->get();

        $summary = $this->buildSummary($submissions);
        $rankings = $this->buildApplicantRankings($submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);
        $evaluationStats = $this->buildEvaluationStats($submissions);

        $pdf = Pdf::loadView('reports.evaluations.pdf.procurement', compact(
            'procurement',
            'submissions',
            'summary',
            'rankings',
            'evaluatorBreakdown',
            'evaluationStats'
        ));

        return $pdf->download('evaluation-procurement-' . $procurement->id . '.pdf');
    }

    public function consolidated()
    {
        $submissions = EvaluationSubmission::with([
                'procurement',
                'applicant.submitter',
                'evaluation',
                'evaluator',
            ])
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->get();

        $summary = $this->buildSummary($submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);
        $procurementStats = $this->buildProcurementStats($submissions);

        return view('reports.evaluations.consolidated', compact(
            'submissions',
            'summary',
            'evaluatorBreakdown',
            'procurementStats'
        ));
    }

    public function consolidatedPdf()
    {
        $submissions = EvaluationSubmission::with([
                'procurement',
                'applicant.submitter',
                'evaluation',
                'evaluator',
            ])
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at')
            ->get();

        $summary = $this->buildSummary($submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);
        $procurementStats = $this->buildProcurementStats($submissions);

        $pdf = Pdf::loadView('reports.evaluations.pdf.consolidated', compact(
            'submissions',
            'summary',
            'evaluatorBreakdown',
            'procurementStats'
        ));

        return $pdf->download('evaluation-consolidated.pdf');
    }

    private function overallMax(EvaluationSubmission $submission): ?float
    {
        if ($submission->evaluation?->type !== 'services') {
            return null;
        }

        return $submission->evaluation->sections
            ->flatMap(fn ($section) => $section->criteria)
            ->sum('max_score');
    }

    private function buildSummary($submissions): array
    {
        $total = $submissions->count();
        $procurements = $submissions->pluck('procurement_id')->unique()->count();
        $evaluators = $submissions->pluck('evaluator_id')->unique()->count();
        $avgOverall = $submissions->whereNotNull('overall_score')->avg('overall_score');

        return [
            'total' => $total,
            'procurements' => $procurements,
            'evaluators' => $evaluators,
            'avg_overall' => $avgOverall ? round($avgOverall, 2) : 0,
        ];
    }

    private function buildEvaluatorBreakdown($submissions)
    {
        return $submissions
            ->groupBy(fn ($s) => $s->evaluator?->name ?? 'Unassigned')
            ->map(function ($group) {
                $avg = $group->whereNotNull('overall_score')->avg('overall_score');
                return [
                    'total' => $group->count(),
                    'avg_overall' => $avg ? round($avg, 2) : 0,
                ];
            });
    }

    private function buildApplicantRankings($submissions)
    {
        return $submissions
            ->groupBy('form_submission_id')
            ->map(function ($group) {
                $scores = $group->whereNotNull('overall_score')->pluck('overall_score');
                $average = $scores->count() ? round($scores->avg(), 2) : 0;
                $highest = $scores->count() ? round($scores->max(), 2) : 0;
                $lowest = $scores->count() ? round($scores->min(), 2) : 0;

                return [
                    'submission' => $group->first()->applicant,
                    'average' => $average,
                    'highest' => $highest,
                    'lowest' => $lowest,
                    'spread' => round($highest - $lowest, 2),
                    'evaluators' => $group->pluck('evaluator_id')->filter()->unique()->count(),
                    'evaluations' => $group->count(),
                ];
            })
            ->sortByDesc('average')
            ->values()
            ->map(function ($row, $index) {
                $row['rank'] = $index + 1;
                return $row;
            });
    }

    private function buildProcurementStats($submissions)
    {
        return $submissions
            ->groupBy('procurement_id')
            ->map(function ($group) {
                $procurement = $group->first()->procurement;
                $avg = $group->whereNotNull('overall_score')->avg('overall_score');

                return [
                    'procurement' => $procurement,
                    'total' => $group->count(),
                    'evaluators' => $group->pluck('evaluator_id')->unique()->count(),
                    'avg_overall' => $avg ? round($avg, 2) : 0,
                ];
            })
            ->values();
    }

    private function buildEvaluationStats($submissions)
    {
        return $submissions
            ->groupBy('evaluation_id')
            ->map(function ($group) {
                $evaluation = $group->first()->evaluation;
                $evaluation->loadMissing('sections.criteria');

                $criteriaStats = $evaluation->sections
                    ->flatMap(fn ($section) => $section->criteria)
                    ->map(function ($criterion) use ($group, $evaluation) {
                        $scores = $group->flatMap(function ($submission) use ($criterion) {
                            return $submission->criteriaScores
                                ->where('evaluation_criteria_id', $criterion->id);
                        });

                        if ($evaluation->type === 'goods') {
                            $yes = $scores->where('decision', 1)->count();
                            $no = $scores->where('decision', 0)->count();
                            $total = $yes + $no;
                            $rate = $total > 0 ? round(($yes / $total) * 100, 1) : 0;

                            return [
                                'name' => $criterion->name,
                                'total' => $total,
                                'yes' => $yes,
                                'no' => $no,
                                'rate' => $rate,
                            ];
                        }

                        $avg = $scores->count() ? round($scores->avg('score'), 2) : 0;

                        return [
                            'name' => $criterion->name,
                            'max' => $criterion->max_score,
                            'avg' => $avg,
                            'total' => $scores->count(),
                        ];
                    });

                $avgOverall = $group->whereNotNull('overall_score')->avg('overall_score');

                return [
                    'evaluation' => $evaluation,
                    'type' => $evaluation->type,
                    'total' => $group->count(),
                    'avg_overall' => $avgOverall ? round($avgOverall, 2) : 0,
                    'criteria_stats' => $criteriaStats,
                ];
            })
            ->values();
    }
}
