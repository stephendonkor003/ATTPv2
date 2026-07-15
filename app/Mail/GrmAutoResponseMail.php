<?php

namespace App\Mail;

use App\Models\GrmEscalationRule;
use App\Models\GrmGrievance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GrmAutoResponseMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public GrmGrievance $grievance,
        public ?GrmEscalationRule $rule = null
    ) {
    }

    public function build(): self
    {
        $subject = $this->rule?->auto_response_subject
            ?: 'GRM Case Received: ' . $this->grievance->case_number;

        return $this->subject($subject)
            ->view('emails.grm.auto-response');
    }
}
