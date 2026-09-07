<?php

namespace App\Mail;

use App\Models\AssistantSubmission;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AssistantSubmissionReviewMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AssistantSubmission $submission,
        public User $recipient
    ) {
        $this->mailer(config('assistant_submissions.mailer') ?: config('mail.default'));
    }

    public function build(): self
    {
        $this->submission->loadMissing(['creator', 'reviewer']);
        $creatorName = $this->submission->creator?->name ?: 'The administrative assistant';
        $requestType = $this->submission->kind === 'purchase_request' ? 'Purchase request' : 'Disbursement';
        $pdf = Pdf::loadView('administrative-assistant.submissions.pdf', [
            'submission' => $this->submission,
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $this->submission->reference_no);
        $coordinatorEmail = (string) config('assistant_submissions.coordinator_email', 'chirwat@africanunion.org');

        return $this->subject('Urgent review: '.$creatorName.' submitted '.$this->submission->reference_no)
            ->view('emails.assistant-submission-review', [
                'submission' => $this->submission,
                'recipient' => $this->recipient,
                'creatorName' => $creatorName,
                'requestType' => $requestType,
                'coordinatorEmail' => $coordinatorEmail,
                'isMainCoordinator' => strcasecmp(trim((string) $this->recipient->email), trim($coordinatorEmail)) === 0,
                'reviewUrl' => route('assistant-approvals.show', $this->submission),
            ])
            ->attachData($pdf->output(), 'approval-request-'.$filename.'.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
