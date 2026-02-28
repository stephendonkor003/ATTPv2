<?php

namespace App\Mail;

use App\Models\Treaty;
use App\Models\TreatyMemberStateStatus;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class TreatyCodeVerificationUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public Treaty $treaty;
    public TreatyMemberStateStatus $status;
    public string $codeType;
    public string $memberStateName;
    public ?User $actor;

    public function __construct(
        Treaty $treaty,
        TreatyMemberStateStatus $status,
        string $codeType,
        string $memberStateName,
        ?User $actor = null
    ) {
        $this->treaty = $treaty;
        $this->status = $status;
        $this->codeType = $codeType;
        $this->memberStateName = $memberStateName;
        $this->actor = $actor;
    }

    public function build()
    {
        $codeLabel = $this->codeType === 'ratified' ? 'Ratified' : 'Signed';
        $subject = $codeLabel . ' Treaty Code Verified - ' . Str::limit($this->memberStateName, 80);

        return $this->subject($subject)
            ->view('emails.treaties.code-verification-update')
            ->with([
                'treaty' => $this->treaty,
                'status' => $this->status,
                'codeType' => $this->codeType,
                'codeLabel' => $codeLabel,
                'memberStateName' => $this->memberStateName,
                'actorName' => $this->actor?->name ?? 'AU Legal Officer',
                'reviewUrl' => route('settings.au.treaties.show', $this->treaty->id),
                'memberPortalUrl' => route('member-state.treaties.index'),
            ]);
    }
}
