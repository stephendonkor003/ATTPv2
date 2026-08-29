<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\EvaluationSubmission;
use App\Models\Procurement;
use Barryvdh\DomPDF\Facade\Pdf;

class EvaluationPanelPdfController extends Controller
{
    use ScopesAssignedPortfolios;

    /* ===============================
     | SINGLE EVALUATOR PDF
     =============================== */
    public function single(EvaluationSubmission $submission)
    {
        $this->assertPanelSubmissionAccessible($submission);

        $submission->load([
            'evaluation.sections.criteria',
            'criteriaScores.criteria',
            'sectionScores.section',
            'evaluator',
            'procurement',
            'applicant.submitter',
        ]);

        $pdf = Pdf::loadView(
            'evaluations.panel.pdf.single',
            compact('submission')
        );

        return $pdf->download(
            'evaluation-'.$submission->id.'.pdf'
        );
    }

    /* ===============================
     | BULK PDF PER PROCUREMENT
     =============================== */
    public function bulk(Procurement $procurement)
    {
        $this->assertPanelProcurementAccessible($procurement);

        $submissions = EvaluationSubmission::with([
            'evaluation.sections.criteria',
            'criteriaScores.criteria',
            'sectionScores.section',
            'evaluator',
            'applicant.submitter',
        ])
            ->where('procurement_id', $procurement->id)
            ->whereNotNull('submitted_at')
            ->where('workflow_status', EvaluationSubmission::WORKFLOW_SUBMITTED)
            ->when($this->userHasAssignedPortfolioScope(), function ($query) {
                $this->applyAssignedPortfolioScopeToEvaluationSubmissions($query);
            })
            ->get();

        abort_if($submissions->isEmpty(), 404, 'No evaluations found');

        return Pdf::loadView(
            'evaluations.panel.pdf.bulk',
            compact('procurement', 'submissions')
        )
            ->setPaper('a4', 'portrait')
            ->download(
                'panel-evaluations-'.$procurement->id.'.pdf'
            );
    }

    private function assertPanelSubmissionAccessible(EvaluationSubmission $submission): void
    {
        abort_unless(
            $submission->isSubmitted()
                && $submission->workflow_status === EvaluationSubmission::WORKFLOW_SUBMITTED,
            404,
            'A completed evaluation PDF was not found.'
        );

        $user = request()->user();

        if ($this->userHasAssignedPortfolioScope($user)) {
            abort_unless(
                $this->evaluationSubmissionIsInAssignedPortfolio($submission, $user),
                403,
                'This evaluation PDF is not assigned to your portfolio.'
            );
        }
    }

    private function assertPanelProcurementAccessible(Procurement $procurement): void
    {
        $user = request()->user();

        if ($this->userHasAssignedPortfolioScope($user)) {
            abort_unless(
                $this->procurementIsInAssignedPortfolio($procurement, $user),
                403,
                'This procurement is not assigned to your portfolio.'
            );
        }
    }
}
