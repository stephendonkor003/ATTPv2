<?php

namespace App\Mail;

use App\Models\EvaluationSubmission;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EvaluationCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public EvaluationSubmission $submission
    ) {}

    public function build()
    {
        $submission = $this->submission->load([
            'procurement',
            'applicant.submitter',
            'evaluation.sections.criteria',
            'criteriaScores.criteria',
            'sectionScores.section',
            'evaluator',
        ]);

        $overallMax = $submission->evaluation?->usesNumericScoring()
            ? $submission->evaluation->sections
                ->flatMap(fn ($section) => $section->criteria)
                ->sum('max_score')
            : null;

        $pdf = Pdf::loadView('reports.evaluations.pdf.submission', [
            'submission' => $submission,
            'overallMax' => $overallMax,
        ]);

        return $this->subject('Evaluation Submitted: '.($submission->applicant?->procurement_submission_code ?? 'Submission'))
            ->view('emails.evaluations.completed', compact('submission', 'overallMax'))
            ->attachData(
                $pdf->output(),
                'evaluation-report-'.($submission->applicant?->procurement_submission_code ?? $submission->id).'.pdf',
                ['mime' => 'application/pdf']
            );
    }
}
