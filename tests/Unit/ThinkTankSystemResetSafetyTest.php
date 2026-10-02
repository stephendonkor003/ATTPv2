<?php

use App\Models\User;
use App\Notifications\ThinkTankPortalPasswordResetNotification;
use App\Services\ThinkTank\ThinkTankInvitationService;
use App\Services\ThinkTank\ThinkTankMailSecurityService;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Password;

function bootThinkTankSystemResetTestApplication(): void
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return;
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();
    restore_error_handler();
    restore_exception_handler();
}

function thinkTankResetTestUser(bool $failDelivery = false): User
{
    return new class($failDelivery) extends User
    {
        public ?object $deliveredNotification = null;

        public function __construct(private readonly bool $failDelivery = false)
        {
            parent::__construct();
            $this->setRawAttributes([
                'id' => 'think-tank-reset-test-user',
                'name' => 'Think Tank Reset Test User',
                'email' => 'portal.user@example.test',
                'password' => 'existing-password-hash',
                'must_change_password' => false,
                'otp_verified_at' => '2026-09-30 10:00:00',
                'remember_token' => 'existing-remember-token',
                'user_type' => 'think_tank',
            ], true);
        }

        public function notify($instance): void
        {
            if ($this->failDelivery) {
                throw new RuntimeException('Synthetic Graph transport failure.');
            }

            $this->deliveredNotification = $instance;
        }
    };
}

it('sends the administrator reset directly to the target with accurate revoked-access copy', function (): void {
    bootThinkTankSystemResetTestApplication();

    $security = Mockery::mock(ThinkTankMailSecurityService::class);
    $security->shouldReceive('assertCredentialDeliveryIsSecure')->once();
    $broker = Mockery::mock(PasswordBroker::class);
    $user = thinkTankResetTestUser();

    $broker->shouldReceive('deleteToken')->once()->with($user);
    $broker->shouldReceive('createToken')->once()->with($user)->andReturn('single-use-admin-reset-token');
    Password::shouldReceive('broker')->once()->andReturn($broker);

    $accepted = (new ThinkTankInvitationService($security))->send($user, false, true);

    expect($accepted)->toBeTrue()
        ->and($user->deliveredNotification)->toBeInstanceOf(ThinkTankPortalPasswordResetNotification::class)
        ->and($user->deliveredNotification)->toBeInstanceOf(ShouldBeEncrypted::class);

    $mail = $user->deliveredNotification->toMail($user);
    $copy = implode(' ', [...$mail->introLines, ...$mail->outroLines]);

    expect($mail->subject)->toBe('Action required: Reset your Think Tank Portal password')
        ->and($copy)->toContain('Your previous password no longer works')
        ->not->toContain('safely ignore');
});

it('deletes the reset token and preserves all account security state when delivery fails', function (): void {
    bootThinkTankSystemResetTestApplication();

    $security = Mockery::mock(ThinkTankMailSecurityService::class);
    $security->shouldReceive('assertCredentialDeliveryIsSecure')->once();
    $broker = Mockery::mock(PasswordBroker::class);
    $user = thinkTankResetTestUser(true);
    $securityFields = ['password', 'must_change_password', 'otp_verified_at', 'remember_token'];
    $securityState = collect($securityFields)
        ->mapWithKeys(fn (string $field): array => [$field => $user->getRawOriginal($field)])
        ->all();

    $broker->shouldReceive('deleteToken')->twice()->with($user);
    $broker->shouldReceive('createToken')->once()->with($user)->andReturn('failed-admin-reset-token');
    Password::shouldReceive('broker')->once()->andReturn($broker);

    expect((new ThinkTankInvitationService($security))->send($user, false, true))->toBeFalse()
        ->and(collect($securityFields)
            ->mapWithKeys(fn (string $field): array => [$field => $user->getRawOriginal($field)])
            ->all())->toBe($securityState);
});

it('gates password and session invalidation behind accepted reset delivery', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/ThinkTank/ThinkTankUserManagementService.php');
    $method = str($source)
        ->after('public function resetPasswordForSystemOversight(')
        ->before('/** @return array{user: User, created: bool} */')
        ->toString();

    $delivery = strpos($method, '$this->invitations->send($deliveryTarget, false, true)');
    $failureReturn = strpos($method, 'return false;');
    $passwordMutation = strpos($method, '$lockedTarget->forceFill([');
    $mfaInvalidation = strpos($method, '$this->sessions->invalidateMfa($lockedTarget);');
    $sessionRevocation = strpos($method, '$this->sessions->revokeAllSessions($lockedTarget);');

    expect($delivery)->not->toBeFalse()
        ->and($method)->toContain('$'.'lockedTarget->user_type !== \'think_tank\'')
        ->toContain('(string) $lockedTarget->think_tank_member_id !== (string) $lockedTenant->getKey()')
        ->toContain('$lockedTarget->is_blacklisted')
        ->toContain('hash_equals($deliveryEmail, mb_strtolower(trim((string) $lockedTarget->email)))')
        ->toContain('$this->invitations->invalidateOutstandingToken($deliveryTarget)')
        ->and($failureReturn)->not->toBeFalse()
        ->and($passwordMutation)->not->toBeFalse()
        ->and($mfaInvalidation)->not->toBeFalse()
        ->and($sessionRevocation)->not->toBeFalse()
        ->and($delivery)->toBeLessThan($failureReturn)
        ->and($failureReturn)->toBeLessThan($passwordMutation)
        ->and($passwordMutation)->toBeLessThan($mfaInvalidation)
        ->and($mfaInvalidation)->toBeLessThan($sessionRevocation);
});

it('rate limits the authorized system reset endpoint', function (): void {
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $route = str($routes)
        ->after("Route::middleware('permission:users.manage')")
        ->after("->prefix('think-tank-users')")
        ->before('/*')
        ->toString();

    expect($route)->toContain("Route::post('/{user}/reset-password', 'resetPassword')")
        ->toContain("->middleware('throttle:5,1,think-tank-system-password-reset')")
        ->toContain("->name('reset-password')");
});
