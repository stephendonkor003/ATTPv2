<?php

namespace App\Mail;

use App\Models\Program;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProgramTtlAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Program $program,
        public ?string $plainPassword,
        public string $loginUrl,
        public string $programUrl
    ) {
    }

    public function build()
    {
        return $this->subject('ATTP TTL Assignment: ' . ($this->program->name ?? 'Program'))
            ->view('emails.programs.ttl-assigned')
            ->with([
                'user' => $this->user,
                'program' => $this->program,
                'plainPassword' => $this->plainPassword,
                'loginUrl' => $this->loginUrl,
                'programUrl' => $this->programUrl,
            ]);
    }
}
