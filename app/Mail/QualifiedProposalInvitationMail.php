<?php

namespace App\Mail;

use App\Models\EoiReportCommunicationRecipient;
use App\Services\EoiReportCommunicationService;
use App\Support\PdfBranding;
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
            'communication.technicalProposalRound.rules',
        ]);

        $mail = $this
            ->from((string) config('mail.from.address'), PdfBranding::PLATFORM_NAME)
            ->subject($this->recipient->communication->subject)
            ->view('emails.evaluations.proposal-invitation', [
                'recipient' => $this->recipient,
                'communication' => $this->recipient->communication,
                'procurement' => $this->recipient->communication->procurement,
                'portalUrl' => route('vendor.eoi-communications.show', $this->recipient),
            ]);

        $attachments = $this->recipient->communication->attachments;

        if (! $this->recipient->communication->technical_proposal_round_id
            && (int) $attachments->sum('file_size') <= EoiReportCommunicationService::MAX_EMAIL_TEMPLATE_BYTES) {
            foreach ($attachments as $attachment) {
                $mail->attachFromStorageDisk(
                    'local',
                    $attachment->file_path,
                    $attachment->original_filename,
                    ['mime' => $attachment->mime_type]
                );
            }
        }

        return $mail;
    }
}
