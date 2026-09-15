<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

final class ApplicationVerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your ATTP email address')
            ->greeting('Hello '.trim((string) ($notifiable->name ?: 'there')).',')
            ->line('Welcome to the Africa Think Tank Platform.')
            ->line('Please confirm that this email address belongs to you by using the secure button below.')
            ->action('Verify my email address', $this->verificationUrl($notifiable))
            ->line('For your security, this verification link is signed and will expire automatically.')
            ->line('If you did not create or request this account, you can safely ignore this message.')
            ->salutation('ATTP Platform Support');
    }
}
