<?php

namespace App\Mail;

use App\Models\Treaty;
use App\Models\TreatyMemberStateStatus;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TreatyProofServiceCodesMail extends Mailable
{
    use Queueable, SerializesModels;

    public Treaty $treaty;
    public TreatyMemberStateStatus $status;
    public User $actor;
    public bool $isResend;

    public function __construct(Treaty $treaty, TreatyMemberStateStatus $status, User $actor, bool $isResend = false)
    {
        $this->treaty = $treaty;
        $this->status = $status;
        $this->actor = $actor;
        $this->isResend = $isResend;
    }

    public function build()
    {
        $memberStateName = optional($this->actor->memberState)->name
            ?: optional($this->status->memberState)->name
            ?: 'Member State';

        $subjectPrefix = $this->isResend ? '[Resent] ' : '';
        $reference = $this->treaty->reference_code ?: 'No Ref';
        $subject = $subjectPrefix . 'Treaty Proof-of-Service Codes (' . $reference . ') - ' . $memberStateName;

        return $this->subject($subject)
            ->view('emails.treaties.proof-service-codes')
            ->with([
                'treaty' => $this->treaty,
                'status' => $this->status,
                'memberStateName' => $memberStateName,
                'isResend' => $this->isResend,
                'reviewUrl' => route('settings.au.treaties.show', $this->treaty->id),
            ]);
    }
}
