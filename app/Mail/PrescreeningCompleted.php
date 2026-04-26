<?php

namespace App\Mail;

use App\Models\FormSubmission;
use App\Models\PrescreeningEvaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class PrescreeningCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public FormSubmission $submission
    ) {}

    public function build()
    {
        $submission = $this->submission->load([
            'procurement',
            'submitter',
            'values',
            'prescreeningResult.evaluator',
        ]);

        $template = $submission->prescreeningResult?->prescreening_template_id
            ? \App\Models\PrescreeningTemplate::with('sections.criteria')
                ->find($submission->prescreeningResult->prescreening_template_id)
            : $submission->procurement?->prescreeningTemplate?->load('sections.criteria');

        $sections = $template ? $template->sections : collect();

        $evaluations = PrescreeningEvaluation::with('criterion')
            ->where('submission_id', $submission->id)
            ->get()
            ->keyBy('criterion_id');

        $pdf = Pdf::loadView('reports.prescreening.pdf.submission_watermarked', [
            'submission' => $submission,
            'template' => $template,
            'sections' => $sections,
            'evaluations' => $evaluations,
        ]);

        return $this->subject('Prescreening Completed: ' . $submission->procurement_submission_code)
            ->view('emails.prescreening.completed', compact('submission', 'template'))
            ->attachData(
                $pdf->output(),
                'prescreening-report-' . $submission->procurement_submission_code . '.pdf',
                ['mime' => 'application/pdf']
            );
    }
}
