<?php

namespace App\Mail;

use App\Models\ConsortiumThinkTank;
use App\Models\User;
use Illuminate\Mail\Mailable;

class ThinkTankTemporaryPasswordMail extends Mailable
{
    public function __construct(
        public readonly User $user,
        public readonly ConsortiumThinkTank $tenant,
        public readonly string $temporaryPassword,
    ) {}

    public function build(): self
    {
        return $this->subject('Your ATTP Think Tank Portal account')
            ->markdown('emails.think-tank.temporary-password')
            ->with([
                'loginUrl' => rtrim((string) config('think_tank_portal.frontend_url'), '/').'/login',
            ]);
    }
}
