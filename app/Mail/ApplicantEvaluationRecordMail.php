<?php

namespace App\Mail;

use App\Models\EoiReportCommunicationRecipient;
use App\Support\PdfBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ApplicantEvaluationRecordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public EoiReportCommunicationRecipient $recipient) {}

    public function build(): self
    {
        $this->recipient->loadMissing('communication.procurement');

        $mail = $this
            ->from((string) config('mail.from.address'), PdfBranding::PLATFORM_NAME)
            ->subject($this->recipient->communication->subject)
            ->view('emails.evaluations.applicant-record', [
                'recipient' => $this->recipient,
                'communication' => $this->recipient->communication,
                'procurement' => $this->recipient->communication->procurement,
            ]);

        if ($this->recipient->record_file_path) {
            $mail->attachFromStorageDisk(
                'local',
                $this->recipient->record_file_path,
                $this->recipient->record_file_name ?: 'eoi-evaluation-record.pdf',
                ['mime' => $this->recipient->record_mime_type ?: 'application/pdf']
            );
        }

        return $mail;
    }
}
