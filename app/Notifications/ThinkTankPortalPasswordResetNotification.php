<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;

class ThinkTankPortalPasswordResetNotification extends ResetPassword implements ShouldBeEncrypted, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

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
                ->line("This link expires in {$minutes} minutes. If you were not expecting this invitation, please contact your organization administrator.");
        }

        return (new MailMessage)
            ->subject('Reset your Think Tank Portal password')
            ->line('We received a request to reset your Think Tank Portal password.')
            ->action('Reset password', $url)
            ->line("This single-use link expires in {$minutes} minutes.")
            ->line('If you did not request this reset, you can ignore this message.');
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
