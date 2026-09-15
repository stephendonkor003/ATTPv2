<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\Messages\MailMessage;

class ThinkTankPortalPasswordResetNotification extends ResetPassword implements ShouldBeEncrypted
{
    public function __construct(
        #[\SensitiveParameter] string $token,
        private readonly bool $invitation = false,
    ) {
        parent::__construct($token);
    }

    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $url = $this->resetUrl($notifiable);

        if ($this->invitation) {
            return (new MailMessage)
                ->subject('Set up your Think Tank Portal account')
                ->greeting('Hello '.$notifiable->name.',')
                ->line('A Think Tank Portal account has been created for you.')
                ->line('Use the secure, single-use link below to choose your password. No temporary password has been created or sent.')
                ->action('Set my password', $url)
                ->line("This link expires in {$minutes} minutes. If you were not expecting this invitation, please contact your organization administrator.")
                ->salutation('ATTP Platform Support');
        }

        return (new MailMessage)
            ->subject('Reset your Think Tank Portal password')
            ->greeting('Hello '.trim((string) ($notifiable->name ?: 'there')).',')
            ->line('We received a request to reset your Think Tank Portal password.')
            ->line('Use the secure button below to choose a new private password. Your password is never included in this email.')
            ->action('Choose a new password', $url)
            ->line("This single-use link expires in {$minutes} minutes.")
            ->line('If you did not request this reset, you can safely ignore this message.')
            ->salutation('ATTP Platform Support');
    }

    protected function resetUrl($notifiable): string
    {
        $base = rtrim((string) config('think_tank_portal.frontend_url'), '/');
        $path = '/'.ltrim((string) config('think_tank_portal.password_reset_path', '/reset-password'), '/');
        $query = http_build_query([
            'email' => $notifiable->getEmailForPasswordReset(),
        ], '', '&', PHP_QUERY_RFC3986);

        return $base.$path.'/'.rawurlencode($this->token).'?'.$query;
    }
}
