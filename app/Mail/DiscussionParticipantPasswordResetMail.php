<?php

namespace App\Mail;

use App\Models\DiscussionParticipant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DiscussionParticipantPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public DiscussionParticipant $participant,
        public string $resetUrl,
        public int $expiresInMinutes
    ) {}

    public function build(): self
    {
        return $this->subject('Reset your ATTP policy community password')
            ->view('emails.discussion.participant-password-reset');
    }
}
