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

    public function send(User $user, bool $invitation, bool $administratorInitiated = false): bool
    {
        try {
            $this->mailSecurity->assertCredentialDeliveryIsSecure();
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }

        /** @var PasswordBroker $broker */
        $broker = Password::broker();
        $broker->deleteToken($user);
        $token = $broker->createToken($user);

        try {
            $user->notify(new ThinkTankPortalPasswordResetNotification(
                $token,
                $invitation,
                $administratorInitiated,
            ));

            return true;
        } catch (Throwable $exception) {
            $broker->deleteToken($user);
            report($exception);

            return false;
        } finally {
            unset($token);
        }
    }

    /**
     * Revoke a link that was accepted for delivery but can no longer be used
     * safely because the target account changed before access was invalidated.
     */
    public function invalidateOutstandingToken(User $user): void
    {
        Password::broker()->deleteToken($user);
    }
}
