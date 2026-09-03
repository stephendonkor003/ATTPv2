<?php

namespace App\Mail;

use App\Models\Consortium;
use App\Models\ConsortiumThinkTank;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ThinkTankPortalWelcome extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 20;

    public function __construct(
        public ConsortiumThinkTank $member,
        public Consortium $consortium,
        public User $user,
    ) {
        $this->afterCommit();
    }

    public function build(): self
    {
        return $this->subject('Your ATTP Think Tank Portal Access')
            ->markdown('emails.think-tank.portal-welcome')
            ->with([
                'member' => $this->member,
                'consortium' => $this->consortium,
                'user' => $this->user,
                'loginUrl' => rtrim((string) config('think_tank_portal.frontend_url'), '/').'/login',
            ]);
    }
}
