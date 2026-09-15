<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

final class ApplicationPasswordResetNotification extends ResetPassword
{
    public function __construct(
        #[\SensitiveParameter] string $token,
        private readonly bool $administratorInitiated = false,
    ) {
        parent::__construct($token);
    }

    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $message = (new MailMessage)
            ->subject($this->administratorInitiated
                ? 'Action required: Reset your ATTP password'
                : 'Reset your ATTP password')
            ->greeting('Hello '.trim((string) ($notifiable->name ?: 'there')).',');

        if ($this->administratorInitiated) {
            $message
                ->line('An authorized ATTP administrator requested a secure password reset for your account.')
                ->line('Your password has not been emailed or displayed. Use the button below to choose a new password securely.');
        } else {
            $message
                ->line('We received a request to reset the password for your ATTP account.')
                ->line('Use the secure button below to choose a new password.');
        }

        return $message
            ->action('Choose a new password', $this->resetUrl($notifiable))
            ->line("For your protection, this single-use link expires in {$minutes} minutes.")
            ->line('If you did not expect this message, you can safely ignore it. Your current password will remain unchanged.')
            ->salutation('ATTP Platform Support');
    }
}
