<?php

namespace App\Mail;

use App\Models\GrmGrievance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GrmResponsibleOfficerMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public GrmGrievance $grievance,
        public User $officer,
        public string $caseUrl
    ) {}

    public function build(): self
    {
        return $this->subject('New Grievance Assigned: '.$this->grievance->case_number)
            ->view('emails.grm.responsible-officer');
    }
}
