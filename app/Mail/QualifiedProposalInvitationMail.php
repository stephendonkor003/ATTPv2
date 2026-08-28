<?php

namespace App\Mail;

use App\Models\EoiReportCommunicationRecipient;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QualifiedProposalInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EoiReportCommunicationRecipient $recipient) {}

    public function build(): self
    {
        $this->recipient->loadMissing([
            'communication.procurement',
            'communication.attachments',
        ]);

        $mail = $this
            ->subject($this->recipient->communication->subject)
            ->view('emails.evaluations.proposal-invitation', [
                'recipient' => $this->recipient,
                'communication' => $this->recipient->communication,
                'procurement' => $this->recipient->communication->procurement,
                'portalUrl' => route('vendor.eoi-communications.show', $this->recipient),
            ]);

        foreach ($this->recipient->communication->attachments as $attachment) {
            $mail->attachFromStorageDisk(
                'local',
                $attachment->file_path,
                $attachment->original_filename,
                ['mime' => $attachment->mime_type]
            );
        }

        return $mail;
    }
}
