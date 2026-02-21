<?php

namespace App\Mail;

use App\Models\Indicator;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IndicatorReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public Indicator $indicator;
    public User $user;

    public function __construct(Indicator $indicator, User $user)
    {
        $this->indicator = $indicator;
        $this->user = $user;
    }

    public function build()
    {
        return $this->subject('Reminder: Indicator responsibility and data schedule')
            ->view('emails.indicator_reminder')
            ->with([
                'indicator' => $this->indicator,
                'user' => $this->user,
                'frequency' => $this->indicator->frequency?->name,
            ]);
    }
}
