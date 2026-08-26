<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Evaluation;
use App\Models\EvaluationSubmission;
use App\Models\Procurement;
use App\Services\EoiQualificationService;
use App\Support\PdfBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class EvaluationReportController extends Controller
{
    use ScopesAssignedPortfolios;

    public function index()
    {
        $procurementQuery = Procurement::query()->orderBy('title');
        $this->applyEvaluationReportProcurementScope($procurementQuery);
        $procurements = $procurementQuery->get();

        $submissionQuery = EvaluationSubmission::with([
            'procurement',
            'applicant.submitter',
            'applicant.values',
            'evaluation',
            'evaluator',
        ])
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at');
        $this->applyEvaluationReportSubmissionScope($submissionQuery);
        $submissions = $submissionQuery->get();

        $eoiProcurementQuery = Procurement::query()
            ->where(function ($query): void {
                $query->whereHas('evaluationAssignments.evaluation', function ($evaluation): void {
                    $evaluation->where('type', Evaluation::TYPE_EOI);
                })->orWhereHas('submissions.evaluationSubmissions.evaluation', function ($evaluation): void {
                    $evaluation->where('type', Evaluation::TYPE_EOI);
                })->orWhereHas('evaluations', function ($evaluation): void {
                    $evaluation->where('type', Evaluation::TYPE_EOI);
                });
            })
            ->withCount('submissions')
            ->with([
                'evaluationAssignments' => function ($assignments): void {
                    $assignments
                        ->whereHas('evaluation', fn ($evaluation) => $evaluation
                            ->where('type', Evaluation::TYPE_EOI))
                        ->with(['evaluation:id,name,type', 'evaluator:id,name,email']);
                },
            ])
            ->orderBy('title');
        $this->applyEvaluationReportProcurementScope($eoiProcurementQuery);
        $eoiProcurements = $eoiProcurementQuery->get();

        $eoiSubmissionGroups = $submissions
            ->filter(fn (EvaluationSubmission $submission): bool => $submission->evaluation?->isEoi() ?? false)
            ->groupBy(fn (EvaluationSubmission $submission): string => (string) $submission->procurement_id);
        $eoiProcurementStats = $eoiProcurements->mapWithKeys(function (Procurement $procurement) use ($eoiSubmissionGroups): array {
            $completed = $eoiSubmissionGroups->get((string) $procurement->getKey(), collect());
            $panelMemberIds = $procurement->evaluationAssignments
                ->pluck('user_id')
                ->merge($completed->pluck('evaluator_id'))
                ->filter()
                ->unique();

            return [(string) $procurement->getKey() => [
                'applicants' => (int) $procurement->submissions_count,
                'evaluated_applicants' => $completed
                    ->pluck('form_submission_id')
                    ->filter()
                    ->unique()
                    ->count(),
                'completed_reports' => $completed->count(),
                'panel_members' => $panelMemberIds->count(),
                'templates' => $procurement->evaluationAssignments
                    ->pluck('evaluation.name')
                    ->filter()
                    ->unique()
                    ->values(),
            ]];
        });

        return view('reports.evaluations.index', compact(
            'procurements',
            'submissions',
            'eoiProcurements',
            'eoiProcurementStats'
        ));
    }

    public function submission(EvaluationSubmission $submission)
    {
        $this->assertEvaluationSubmissionScope($submission);
        $submission->load([
            'procurement',
            'applicant.submitter',
            'applicant.values',
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
        $this->assertEvaluationSubmissionScope($submission);

        return $this->downloadSubmissionReport($submission);
    }

    public function submissionAnonymisedPdf(EvaluationSubmission $submission)
    {
        $this->assertEvaluationSubmissionScope($submission);

        return $this->downloadSubmissionReport($submission, true);
    }

    public function eoiProcurement(
        Procurement $procurement,
        EoiQualificationService $qualificationService
    ) {
        $this->assertEvaluationProcurementScope($procurement);
        $report = $qualificationService->buildProcurementReport($procurement);

        abort_if(
            $report['evaluations']->isEmpty(),
            404,
            'An Expression of Interest evaluation was not found for this procurement.'
        );

        return view('reports.evaluations.eoi-procurement', compact('report'));
    }

    public function eoiProcurementPdf(
        Procurement $procurement,
        EoiQualificationService $qualificationService
    ) {
        $this->assertEvaluationProcurementScope($procurement);
        $report = $qualificationService->buildProcurementReport($procurement);

        abort_if(
            $report['evaluations']->isEmpty(),
            404,
            'An Expression of Interest evaluation was not found for this procurement.'
        );

        $pdf = Pdf::loadView(
            'reports.evaluations.pdf.eoi-procurement',
            array_merge(['report' => $report], PdfBranding::viewData())
        )->setPaper('a4', 'landscape');

        $name = Str::slug($procurement->reference_no ?: $procurement->title ?: $procurement->getKey());

        return $pdf->download('eoi-qualification-'.$name.'.pdf');
    }

    private function downloadSubmissionReport(EvaluationSubmission $submission, bool $anonymised = false)
    {
        $submission->load([
            'procurement',
            'applicant.submitter',
            'applicant.values',
            'evaluation.sections.criteria',
            'criteriaScores.criteria',
            'sectionScores.section',
            'evaluator',
        ]);

        $overallMax = $this->overallMax($submission);

        $name = $anonymised
            ? 'applicant'
            : Str::slug($submission->applicant?->display_name ?: 'submission');
        $code = $anonymised
            ? Str::slug((string) $submission->id)
            : Str::slug($submission->applicant?->procurement_submission_code ?: $submission->id);
        $prefix = $anonymised ? 'evaluation-submission-anonymised-' : 'evaluation-submission-';

        $pdf = Pdf::loadView('reports.evaluations.pdf.submission', array_merge([
            'submission' => $submission,
            'overallMax' => $overallMax,
            'anonymised' => $anonymised,
        ], PdfBranding::viewData()))->setPaper('a4', 'portrait');

        return $pdf->download($prefix.trim($name.'-'.$code, '-').'.pdf');
    }

    public function procurement(Procurement $procurement)
    {
        $this->assertEvaluationProcurementScope($procurement);

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
        $this->assertEvaluationProcurementScope($procurement);

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
        ))->setPaper('a4', 'landscape');

        return $pdf->download('evaluation-procurement-'.$procurement->id.'.pdf');
    }

    public function consolidated()
    {
        $submissionQuery = EvaluationSubmission::with([
            'procurement',
            'applicant.submitter',
            'evaluation',
            'criteriaScores',
            'evaluator',
        ])
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at');
        $this->applyEvaluationReportSubmissionScope($submissionQuery);
        $submissions = $submissionQuery->get();

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
        $submissionQuery = EvaluationSubmission::with([
            'procurement',
            'applicant.submitter',
            'evaluation',
            'criteriaScores',
            'evaluator',
        ])
            ->whereNotNull('submitted_at')
            ->orderByDesc('submitted_at');
        $this->applyEvaluationReportSubmissionScope($submissionQuery);
        $submissions = $submissionQuery->get();

        $summary = $this->buildSummary($submissions);
        $evaluatorBreakdown = $this->buildEvaluatorBreakdown($submissions);
        $procurementStats = $this->buildProcurementStats($submissions);

        $pdf = Pdf::loadView('reports.evaluations.pdf.consolidated', compact(
            'submissions',
            'summary',
            'evaluatorBreakdown',
            'procurementStats'
        ))->setPaper('a4', 'landscape');

        return $pdf->download('evaluation-consolidated.pdf');
    }

    private function applyEvaluationReportProcurementScope($query): void
    {
        $currentUser = request()->user();

        if ($this->userHasAssignedPortfolioScope($currentUser)) {
            $this->applyAssignedPortfolioScopeToProcurements($query, $currentUser);
        }
    }

    private function applyEvaluationReportSubmissionScope($query): void
    {
        $currentUser = request()->user();

        if (! $this->userHasAssignedPortfolioScope($currentUser)) {
            return;
        }

        $query->whereHas('procurement', function ($procurementQuery) use ($currentUser) {
            $this->applyAssignedPortfolioScopeToProcurements($procurementQuery, $currentUser);
        });
    }

    private function assertEvaluationProcurementScope(Procurement $procurement): void
    {
        $currentUser = request()->user();

        if (! $this->userHasAssignedPortfolioScope($currentUser)) {
            return;
        }

        abort_unless(
            $this->procurementIsInAssignedPortfolio($procurement, $currentUser),
            403,
            'This evaluation report is not assigned to your portfolio.'
        );
    }

    private function assertEvaluationSubmissionScope(EvaluationSubmission $submission): void
    {
        abort_unless(
            $submission->submitted_at,
            404,
            'A completed evaluation report was not found.'
        );

        $currentUser = request()->user();

        if (! $this->userHasAssignedPortfolioScope($currentUser)) {
            return;
        }

        $submission->loadMissing('procurement');

        abort_unless(
            $submission->procurement && $this->procurementIsInAssignedPortfolio($submission->procurement, $currentUser),
            403,
            'This evaluation report is not assigned to your portfolio.'
        );
    }

    private function overallMax(EvaluationSubmission $submission): ?float
    {
        $evaluation = $submission->evaluation;

        if (! $evaluation?->usesNumericScoring()) {
            return null;
        }

        return round(
            (float) $evaluation->sections->sum(
                fn ($section) => (float) $section->criteria->sum('max_score')
            ),
            2
        );
    }

    private function buildSummary($submissions): array
    {
        $total = $submissions->count();
        $procurements = $submissions->pluck('procurement_id')->filter()->unique()->count();
        $evaluators = $submissions->pluck('evaluator_id')->filter()->unique()->count();
        $avgOverall = $this->numericOverallScores($submissions)->avg();

        return [
            'total' => $total,
            'procurements' => $procurements,
            'evaluators' => $evaluators,
            'avg_overall' => $avgOverall !== null ? round($avgOverall, 2) : null,
        ];
    }

    private function buildEvaluatorBreakdown($submissions)
    {
        return $submissions
            ->groupBy(fn ($submission) => $submission->evaluator_id ?: 'unassigned')
            ->map(function ($group) {
                $avg = $this->numericOverallScores($group)->avg();
                $evaluator = $group->first()?->evaluator;

                return [
                    'name' => $evaluator?->name ?? 'Unassigned',
                    'email' => $evaluator?->email,
                    'total' => $group->count(),
                    'avg_overall' => $avg !== null ? round($avg, 2) : null,
                ];
            })
            ->values();
    }

    private function buildApplicantRankings($submissions)
    {
        $rank = 0;

        return $submissions
            ->groupBy('form_submission_id')
            ->map(function ($group) {
                $scores = $this->numericOverallScores($group);
                $average = $scores->isNotEmpty() ? round($scores->avg(), 2) : null;
                $highest = $scores->isNotEmpty() ? round($scores->max(), 2) : null;
                $lowest = $scores->isNotEmpty() ? round($scores->min(), 2) : null;

                return [
                    'submission' => $group->first()->applicant,
                    'average' => $average,
                    'highest' => $highest,
                    'lowest' => $lowest,
                    'spread' => $highest !== null && $lowest !== null
                        ? round($highest - $lowest, 2)
                        : null,
                    'evaluators' => $group->pluck('evaluator_id')->filter()->unique()->count(),
                    'evaluations' => $group->count(),
                ];
            })
            ->sortByDesc(fn ($row) => $row['average'] ?? -1)
            ->values()
            ->map(function ($row) use (&$rank) {
                $row['rank'] = $row['average'] !== null ? ++$rank : null;

                return $row;
            });
    }

    private function buildProcurementStats($submissions)
    {
        return $submissions
            ->groupBy('procurement_id')
            ->map(function ($group) {
                $procurement = $group->first()->procurement;
                $avg = $this->numericOverallScores($group)->avg();

                return [
                    'procurement' => $procurement,
                    'total' => $group->count(),
                    'evaluators' => $group->pluck('evaluator_id')->filter()->unique()->count(),
                    'avg_overall' => $avg !== null ? round($avg, 2) : null,
                ];
            })
            ->values();
    }

    private function buildEvaluationStats($submissions)
    {
        return $submissions
            ->filter(fn ($submission) => $submission->evaluation !== null)
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

                        if ($evaluation->usesCategoricalDecisions()) {
                            $decisions = collect($evaluation->decisionOptions())
                                ->map(function (string $label, int $decision) use ($scores) {
                                    return [
                                        'value' => $decision,
                                        'label' => $label,
                                        'count' => $scores->where('decision', $decision)->count(),
                                    ];
                                })
                                ->values();
                            $total = $decisions->sum('count');

                            if ($evaluation->isGoods()) {
                                $yes = (int) data_get($decisions->firstWhere('value', 1), 'count', 0);
                                $no = (int) data_get($decisions->firstWhere('value', 0), 'count', 0);
                                $rate = $total > 0 ? round(($yes / $total) * 100, 1) : 0;

                                return [
                                    'name' => $criterion->name,
                                    'total' => $total,
                                    'decisions' => $decisions->all(),
                                    'yes' => $yes,
                                    'no' => $no,
                                    'rate' => $rate,
                                ];
                            }

                            return [
                                'name' => $criterion->name,
                                'total' => $total,
                                'decisions' => $decisions->all(),
                                'qualified' => (int) data_get($decisions->firstWhere('value', 2), 'count', 0),
                                'average_qualified' => (int) data_get($decisions->firstWhere('value', 1), 'count', 0),
                                'not_qualified' => (int) data_get($decisions->firstWhere('value', 0), 'count', 0),
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

                $avgOverall = $evaluation->usesNumericScoring()
                    ? $this->numericOverallScores($group)->avg()
                    : null;

                return [
                    'evaluation' => $evaluation,
                    'type' => $evaluation->type,
                    'total' => $group->count(),
                    'avg_overall' => $avgOverall !== null ? round($avgOverall, 2) : null,
                    'criteria_stats' => $criteriaStats,
                ];
            })
            ->values();
    }

    private function numericOverallScores($submissions)
    {
        return $submissions
            ->filter(fn ($submission) => $submission->evaluation?->usesNumericScoring())
            ->pluck('overall_score')
            ->filter(fn ($score) => $score !== null)
            ->map(fn ($score) => (float) $score)
            ->values();
    }
}
