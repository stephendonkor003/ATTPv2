<?php

namespace App\Services\ThinkTank;

use App\Models\User;
use App\Notifications\ThinkTankPortalPasswordResetNotification;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Throwable;

class ThinkTankInvitationService
{
    public function __construct(private readonly ThinkTankMailSecurityService $mailSecurity) {}

    public function send(User $user, bool $invitation): bool
    {
        try {
            $this->mailSecurity->assertCredentialDeliveryIsSecure();
            $this->mailSecurity->assertEncryptedResetQueueIsDurable();
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        /** @var PasswordBroker $broker */
        $broker = Password::broker();
        $broker->deleteToken($user);
        $token = $broker->createToken($user);

        try {
            $user->notify(new ThinkTankPortalPasswordResetNotification($token, $invitation));

            return true;
        } catch (Throwable $exception) {
            $broker->deleteToken($user);
            report($exception);

            return false;
        } finally {
            unset($token);
        }
    }
}
