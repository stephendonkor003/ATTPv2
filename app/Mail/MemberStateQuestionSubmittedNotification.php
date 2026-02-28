<?php

namespace App\Mail;

use App\Models\MemberStateQuestion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class MemberStateQuestionSubmittedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public MemberStateQuestion $question;

    public function __construct(MemberStateQuestion $question)
    {
        $this->question = $question;
    }

    public function build()
    {
        $subjectLine = 'New member-state question submitted: ' . Str::limit((string) $this->question->subject, 80);

        return $this->subject($subjectLine)
            ->view('emails.member-state.question-submitted')
            ->with([
                'question' => $this->question,
                'memberStateName' => $this->question->memberState?->name ?? 'Member State',
                'questionsDeskUrl' => route('system.questions.index'),
            ]);
    }
}
