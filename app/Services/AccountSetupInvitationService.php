<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ApplicationAccountSetupNotification;
use App\Services\ThinkTank\ThinkTankMailSecurityService;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

final class AccountSetupInvitationService
{
    public const PURPOSE_STAFF = 'staff';

    public const PURPOSE_VENDOR = 'vendor';

    public const PURPOSE_APPLICANT = 'applicant';

    public const PURPOSE_FUNDING_PARTNER = 'funding_partner';

    public const PURPOSE_EMPLOYEE = 'employee';

    public const PURPOSE_PORTFOLIO_LEADER = 'portfolio_leader';

    public const PURPOSE_PROGRAM_TTL = 'program_ttl';

    public const PURPOSE_SITE_VISIT = 'site_visit';

    public const PURPOSE_THINK_TANK = 'think_tank';

    public function __construct(
        private readonly ThinkTankMailSecurityService $mailSecurity,
    ) {}

    /**
     * Produce a one-way hash for a random value that is never shown, logged,
     * queued, mailed, or returned to a caller.
     */
    public function unknownPasswordHash(): string
    {
        return Hash::make(Str::password(64));
    }

    /**
     * Issue a fresh setup token and deliver it synchronously so a transport
     * failure can invalidate the token before this method returns.
     */
    public function send(User $user, string $purpose = self::PURPOSE_STAFF): bool
    {
        try {
            $this->mailSecurity->assertCredentialDeliveryIsSecure();

            /** @var PasswordBroker $broker */
            $broker = Password::broker();
            $broker->deleteToken($user);
            $token = $broker->createToken($user);

            try {
                $user->notify(new ApplicationAccountSetupNotification($token, $purpose));

                return true;
            } catch (Throwable $exception) {
                try {
                    $broker->deleteToken($user);
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
                report($exception);

                return false;
            } finally {
                unset($token);
            }
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }
}
