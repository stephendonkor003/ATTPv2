<?php

namespace App\Mail;

use App\Models\MemberStateCommunication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MemberStateCommunicationResponseNotification extends Mailable
{
    use Queueable, SerializesModels;

    public MemberStateCommunication $communication;

    public function __construct(MemberStateCommunication $communication)
    {
        $this->communication = $communication;
    }

    public function build()
    {
        $subjectLine = 'AU response to communication: ' . Str::limit((string) $this->communication->subject, 80);

        return $this->subject($subjectLine)
            ->view('emails.member-state.communication-response')
            ->with([
                'communication' => $this->communication,
                'memberStateName' => $this->communication->memberState?->name ?? 'Member State',
                'portalUrl' => route('member-state.communications.index'),
            ]);
    }
}
