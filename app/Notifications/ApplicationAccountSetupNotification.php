<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\AccountSetupInvitationService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\Messages\MailMessage;

final class ApplicationAccountSetupNotification extends ResetPassword implements ShouldBeEncrypted
{
    public function __construct(
        #[\SensitiveParameter] string $token,
        private readonly string $purpose = AccountSetupInvitationService::PURPOSE_STAFF,
    ) {
        parent::__construct($token);
    }

    public function toMail($notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $accountLabel = $this->accountLabel();

        return (new MailMessage)
            ->subject("Set up your {$accountLabel}")
            ->greeting('Hello '.trim((string) ($notifiable->name ?: 'there')).',')
            ->line("Your {$accountLabel} has been created for you.")
            ->line('Use the secure, single-use button below to choose your private password. No temporary password is displayed or sent to you.')
            ->action('Set up my account', $this->resetUrl($notifiable))
            ->line("For your protection, this link expires in {$minutes} minutes and can be used only once.")
            ->line('If you were not expecting this invitation, do not use the link and contact ATTP Platform Support.')
            ->salutation('ATTP Platform Support');
    }

    protected function resetUrl($notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->isThinkTankUser()) {
            $base = rtrim((string) config('think_tank_portal.frontend_url'), '/');
            $path = '/'.ltrim((string) config('think_tank_portal.password_reset_path', '/reset-password'), '/');
            $query = http_build_query([
                'email' => $notifiable->getEmailForPasswordReset(),
            ], '', '&', PHP_QUERY_RFC3986);

            return $base.$path.'/'.rawurlencode($this->token).'?'.$query;
        }

        return parent::resetUrl($notifiable);
    }

    private function accountLabel(): string
    {
        return match ($this->purpose) {
            AccountSetupInvitationService::PURPOSE_VENDOR => 'ATTP vendor portal account',
            AccountSetupInvitationService::PURPOSE_APPLICANT => 'ATTP applicant portal account',
            AccountSetupInvitationService::PURPOSE_FUNDING_PARTNER => 'ATTP funding partner portal account',
            AccountSetupInvitationService::PURPOSE_EMPLOYEE => 'ATTP employee account',
            AccountSetupInvitationService::PURPOSE_PORTFOLIO_LEADER => 'ATTP portfolio leadership account',
            AccountSetupInvitationService::PURPOSE_PROGRAM_TTL => 'ATTP program TTL account',
            AccountSetupInvitationService::PURPOSE_SITE_VISIT => 'ATTP site-visit account',
            AccountSetupInvitationService::PURPOSE_THINK_TANK => 'Think Tank Portal account',
            default => 'ATTP account',
        };
    }
}
