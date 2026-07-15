<?php

namespace App\Mail;

use App\Models\Sector;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PortfolioLeaderAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Sector $portfolio,
        public string $roleName,
        public ?string $plainPassword,
        public string $loginUrl,
        public string $portfolioUrl
    ) {
    }

    public function build()
    {
        return $this->subject('ATTP Portfolio Assignment: ' . ($this->portfolio->name ?? 'New Portfolio'))
            ->view('emails.portfolio.leader-assigned')
            ->with([
                'user' => $this->user,
                'portfolio' => $this->portfolio,
                'roleName' => $this->roleName,
                'plainPassword' => $this->plainPassword,
                'loginUrl' => $this->loginUrl,
                'portfolioUrl' => $this->portfolioUrl,
            ]);
    }
}
