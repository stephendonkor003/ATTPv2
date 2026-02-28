<?php

namespace App\Mail;

use App\Models\MemberStateCommunication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MemberStateCommunicationSubmittedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public MemberStateCommunication $communication;

    public function __construct(MemberStateCommunication $communication)
    {
        $this->communication = $communication;
    }

    public function build()
    {
        $subjectLine = 'New member-state communication: ' . Str::limit((string) $this->communication->subject, 80);

        return $this->subject($subjectLine)
            ->view('emails.member-state.communication-submitted')
            ->with([
                'communication' => $this->communication,
                'memberStateName' => $this->communication->memberState?->name ?? 'Member State',
                'reviewUrl' => route('system.communications.index', ['q' => $this->communication->subject]),
            ]);
    }
}
