<?php

namespace App\Mail;

use App\Models\GrmEscalationRule;
use App\Models\GrmGrievance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GrmReminderMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public GrmGrievance $grievance,
        public ?GrmEscalationRule $rule = null,
        public string $noticeType = 'reminder'
    ) {
    }

    public function build(): self
    {
        $subject = $this->noticeType === 'escalation'
            ? 'GRM Case Escalation: ' . $this->grievance->case_number
            : ($this->rule?->reminder_subject ?: 'GRM Case Reminder: ' . $this->grievance->case_number);

        return $this->subject($subject)
            ->view('emails.grm.reminder');
    }
}
