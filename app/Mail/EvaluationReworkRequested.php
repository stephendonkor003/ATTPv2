<?php

namespace App\Mail;

use App\Models\ReworkRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EvaluationReworkRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ReworkRequest $rework) {}

    public function build(): self
    {
        $reference = trim((string) $this->rework->procurement?->reference_no);
        $subjectContext = $reference !== ''
            ? $reference
            : ($this->rework->applicant?->procurement_submission_code ?: 'Evaluation');

        return $this
            ->subject('Evaluation Rework Required: '.$subjectContext)
            ->view('emails.evaluations.rework-requested');
    }
}
