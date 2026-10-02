<?php

use App\Models\User;
use App\Notifications\ApplicationAccountSetupNotification;
use App\Services\AccountSetupInvitationService;
use App\Services\ThinkTank\ThinkTankMailSecurityService;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

function bootAccountSetupTestApplication(): void
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return;
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();
    restore_error_handler();
    restore_exception_handler();
}

function accountSetupTestUser(bool $failDelivery = false): User
{
    return new class($failDelivery) extends User
    {
        public ?object $deliveredNotification = null;

        public function __construct(private readonly bool $failDelivery = false)
        {
            parent::__construct();
            $this->setRawAttributes([
                'id' => 'account-setup-test-user',
                'name' => 'Account Setup Test User',
                'email' => 'account-setup@example.test',
                'password' => 'existing-password-hash',
                'user_type' => 'staff',
            ], true);
        }

        public function notify($instance): void
        {
            if ($this->failDelivery) {
                throw new RuntimeException('Synthetic transport failure.');
            }

            $this->deliveredNotification = $instance;
        }
    };
}

it('replaces stale tokens and delivers an encrypted single-use account setup notification', function (): void {
    bootAccountSetupTestApplication();

    $security = Mockery::mock(ThinkTankMailSecurityService::class);
    $security->shouldReceive('assertCredentialDeliveryIsSecure')->once();
    $broker = Mockery::mock(PasswordBroker::class);
    $user = accountSetupTestUser();
    $originalPassword = $user->getRawOriginal('password');

    $broker->shouldReceive('deleteToken')->once()->with($user);
    $broker->shouldReceive('createToken')->once()->with($user)->andReturn('single-use-setup-token');
    Password::shouldReceive('broker')->once()->andReturn($broker);

    $sent = (new AccountSetupInvitationService($security))->send(
        $user,
        AccountSetupInvitationService::PURPOSE_STAFF,
    );

    expect($sent)->toBeTrue()
        ->and($user->deliveredNotification)->toBeInstanceOf(ApplicationAccountSetupNotification::class)
        ->and($user->deliveredNotification)->toBeInstanceOf(ShouldBeEncrypted::class)
        ->and($user->getRawOriginal('password'))->toBe($originalPassword);
});

it('deletes the account setup token when delivery fails and preserves the existing password', function (): void {
    bootAccountSetupTestApplication();

    $security = Mockery::mock(ThinkTankMailSecurityService::class);
    $security->shouldReceive('assertCredentialDeliveryIsSecure')->once();
    $broker = Mockery::mock(PasswordBroker::class);
    $user = accountSetupTestUser(true);
    $originalPassword = $user->getRawOriginal('password');

    $broker->shouldReceive('deleteToken')->twice()->with($user);
    $broker->shouldReceive('createToken')->once()->with($user)->andReturn('failed-delivery-token');
    Password::shouldReceive('broker')->once()->andReturn($broker);

    expect((new AccountSetupInvitationService($security))->send($user))->toBeFalse()
        ->and($user->getRawOriginal('password'))->toBe($originalPassword);
});

it('creates only a one-way unknown password hash for newly provisioned accounts', function (): void {
    bootAccountSetupTestApplication();

    $security = Mockery::mock(ThinkTankMailSecurityService::class);
    $hash = (new AccountSetupInvitationService($security))->unknownPasswordHash();

    expect(Hash::needsRehash($hash))->toBeFalse()
        ->and(Hash::check('Password123!', $hash))->toBeFalse()
        ->and(Hash::check('temporary-password', $hash))->toBeFalse();
});

it('consumes standard account setup tokens while holding the user row lock', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/Auth/NewPasswordController.php');
    $standardBranch = strstr($source, '// Standard setup/reset links use the same in-lock');

    expect(substr_count($source, '->lockForUpdate()'))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($source, '$broker->tokenExists('))->toBeGreaterThanOrEqual(2)
        ->and($standardBranch)->not->toBeFalse()
        ->toContain('$this->completeStandardPasswordReset($lockedUser, $password);')
        ->toContain('$broker->deleteToken($lockedUser);');

    expect(strpos($standardBranch, '$this->completeStandardPasswordReset($lockedUser, $password);'))
        ->toBeLessThan(strpos($standardBranch, '$broker->deleteToken($lockedUser);'));
});

it('forbids plaintext credential fields across every account onboarding delivery path', function (): void {
    $root = dirname(__DIR__, 2);
    $paths = [
        'app/Http/Controllers/UserController.php',
        'app/Http/Controllers/System/UserAccessController.php',
        'app/Http/Controllers/Vendor/VendorManagementController.php',
        'app/Imports/VendorImport.php',
        'app/Http/Controllers/Procurement/PublicProcurementController.php',
        'app/Mail/VendorApplicationReceived.php',
        'app/Http/Controllers/ApplicantController.php',
        'app/Http/Controllers/FunderController.php',
        'app/Http/Controllers/HrController.php',
        'app/Http/Controllers/SectorController.php',
        'app/Services/PortfolioLeaderAssignmentNotificationService.php',
        'app/Jobs/NotifyPortfolioLeaderAssigned.php',
        'app/Mail/PortfolioLeaderAssignedMail.php',
        'app/Http/Controllers/ProgramController.php',
        'app/Jobs/NotifyProgramTtlAssigned.php',
        'app/Mail/ProgramTtlAssignedMail.php',
        'app/Http/Controllers/BiAnnualSiteVisitController.php',
        'app/Http/Controllers/System/ThinkTankUserController.php',
        'app/Services/ThinkTank/ThinkTankUserManagementService.php',
        'resources/views/emails/vendor/application-received.blade.php',
        'resources/views/emails/portfolio/leader-assigned.blade.php',
        'resources/views/emails/programs/ttl-assigned.blade.php',
        'resources/views/think-tank-users/show.blade.php',
    ];
    $forbidden = [
        '$plainPassword',
        '$temporaryPassword',
        '$defaultPassword',
        "'temporary_password'",
        "'plain_password'",
        'Temporary password:',
        'UserAccountCreated',
        'VendorAccountCreated',
        'FundingPartnerWelcome',
        'ApplicantSubmissionReceived',
        'setTemporaryPasswordForSystemOversight',
    ];

    foreach ($paths as $path) {
        $source = file_get_contents($root.'/'.$path);

        expect($source)->not->toBeFalse();
        foreach ($forbidden as $needle) {
            expect($source)->not->toContain($needle);
        }
    }

    foreach ([
        'app/Mail/UserAccountCreated.php',
        'app/Mail/UserPasswordReset.php',
        'app/Mail/VendorAccountCreated.php',
        'app/Mail/FundingPartnerWelcome.php',
        'app/Mail/ApplicantSubmissionReceived.php',
        'app/Mail/ThinkTankTemporaryPasswordMail.php',
    ] as $removedPath) {
        expect(file_exists($root.'/'.$removedPath))->toBeFalse();
    }

    expect(file_get_contents($root.'/app/Http/Controllers/FunderController.php'))
        ->toContain("return ['attempted' => 1, 'failed' => \$invitationSent ? 0 : 1]")
        ->toContain('invitationDeliverySummary(')
        ->and(file_get_contents($root.'/app/Http/Controllers/BiAnnualSiteVisitController.php'))
        ->toContain('$leaderNotificationFailures > 0')
        ->and(file_get_contents($root.'/app/Jobs/NotifyPortfolioLeaderAssigned.php'))
        ->toContain('throw $exception;');
});
