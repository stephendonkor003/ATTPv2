<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

class UserImpersonationSmokeBrowser
{
    use InteractsWithAuthentication;
    use InteractsWithSession;
    use MakesHttpRequests;

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function asVerified(User $user): self
    {
        $this->actingAs($user)->withSession([
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);

        return $this;
    }

    public function postWithCsrf(string $uri, array $data = [])
    {
        $token = Str::random(40);
        $this->withSession(['_token' => $token]);

        return $this->post($uri, ['_token' => $token, ...$data]);
    }
}

$ensure = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$assertRedirectTo = static function ($response, string $expected, string $context) use ($ensure): void {
    $actual = (string) $response->headers->get('Location', '');
    $ensure(
        $response->isRedirect($expected),
        "{$context} Expected redirect [{$expected}], received status {$response->getStatusCode()} with location [{$actual}]."
    );
};

$assertRejected = static function ($response, string $message) use ($ensure, $app): void {
    $ensure(
        in_array($response->getStatusCode(), [302, 303, 403, 422], true),
        $message.' Unexpected status: '.$response->getStatusCode().'.'
    );
    $ensure(
        ! $app['session.store']->has('user_impersonation'),
        $message.' A rejected request created impersonation state.'
    );
};

$payloadContains = static function (?SystemAuditLog $log, string $value): bool {
    return $log !== null
        && str_contains((string) json_encode($log->payload), $value);
};

$originalLocalOtpRequirement = config('security.require_login_otp_locally');
config(['security.require_login_otp_locally' => true]);

DB::beginTransaction();

try {
    $suffix = Str::lower(Str::random(10));
    $systemAdminRole = Role::firstOrCreate(
        ['name' => 'System Admin'],
        ['description' => 'Full system administrator']
    );
    $usersManage = Permission::firstOrCreate(
        ['name' => 'users.manage'],
        ['module' => 'system', 'description' => 'Manage system users']
    );

    $createUser = static function (string $label, array $attributes = []) use ($suffix): User {
        $user = User::query()->create([
            'name' => $label,
            'email' => Str::slug($label).'-'.$suffix.'-'.Str::lower(Str::random(5)).'@example.test',
            'password' => 'Password123!',
            'user_type' => 'staff',
            'role_id' => null,
            'must_change_password' => false,
            'password_changed_at' => now(),
            'is_disabled' => false,
            'is_blacklisted' => false,
            ...$attributes,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user->fresh();
    };

    $administrator = $createUser('Impersonation Smoke Administrator', [
        'user_type' => 'admin',
        'role_id' => $systemAdminRole->id,
    ]);
    $otherAdministrator = $createUser('Impersonation Smoke Other Administrator', [
        'user_type' => 'admin',
        'role_id' => $systemAdminRole->id,
    ]);
    $target = $createUser('Impersonation Smoke Target', [
        'must_change_password' => true,
        'password_changed_at' => null,
    ]);
    $deletableTarget = $createUser('Impersonation Smoke Deletable Target');
    $vendorTarget = $createUser('Impersonation Smoke Vendor', [
        'user_type' => 'vendor',
        'must_change_password' => true,
        'password_changed_at' => null,
    ]);
    $expiredBlockedVendor = $createUser('Impersonation Smoke Expired Vendor Block', [
        'user_type' => 'vendor',
        'is_disabled' => true,
        'disabled_at' => now()->subDays(2),
        'disabled_until' => now()->subDay(),
        'disabled_reason' => 'Expired temporary block.',
    ]);
    $disabledTarget = $createUser('Impersonation Smoke Disabled Target', [
        'is_disabled' => true,
        'disabled_at' => now(),
        'disabled_reason' => 'Disabled for impersonation smoke coverage.',
    ]);
    $blacklistedTarget = $createUser('Impersonation Smoke Blacklisted Target', [
        'is_blacklisted' => true,
        'blacklisted_at' => now(),
        'blacklisted_reason' => 'Blacklisted for impersonation smoke coverage.',
    ]);
    $unlinkedMemberState = $createUser('Impersonation Smoke Unlinked Member State', [
        'user_type' => 'member_state',
        'member_state_id' => null,
    ]);
    $delegatedManager = $createUser('Impersonation Smoke Delegated Manager');
    $delegatedManager->permissions()->syncWithoutDetaching([$usersManage->id]);
    $delegatedManager->unsetRelation('permissions');

    $browser = new UserImpersonationSmokeBrowser($app);

    $browser->asVerified($administrator);
    $usersPage = $browser->get(route('system.users.index'));
    $usersPage
        ->assertOk()
        ->assertSee('Login as')
        ->assertSee(route('system.users.login-as', $target), false)
        ->assertDontSee(route('system.users.login-as', $administrator), false)
        ->assertDontSee(route('system.users.login-as', $otherAdministrator), false)
        ->assertDontSee(route('system.users.login-as', $disabledTarget), false)
        ->assertDontSee(route('system.users.login-as', $blacklistedTarget), false);

    $browser->asVerified($delegatedManager);
    $browser->get(route('system.users.index'))
        ->assertOk()
        ->assertDontSee('Login as');
    $managerAttempt = $browser->postWithCsrf(
        route('system.users.login-as', $target)
    );
    $assertRejected($managerAttempt, 'A non-admin with users.manage was not rejected.');
    $ensure(
        (string) Auth::id() === (string) $delegatedManager->id,
        'The rejected delegated-manager request changed the authenticated user.'
    );

    foreach ([
        [$administrator, 'Self impersonation was not rejected.'],
        [$otherAdministrator, 'Impersonating another administrator was not rejected.'],
        [$disabledTarget, 'Impersonating a disabled account was not rejected.'],
        [$blacklistedTarget, 'Impersonating a blacklisted account was not rejected.'],
        [$unlinkedMemberState, 'Impersonating an unlinked member-state account was not rejected.'],
    ] as [$protectedTarget, $message]) {
        $browser->asVerified($administrator);
        $response = $browser->postWithCsrf(
            route('system.users.login-as', $protectedTarget)
        );
        $assertRejected($response, $message);
        $ensure(
            (string) Auth::id() === (string) $administrator->id,
            $message.' The authenticated administrator was replaced.'
        );
    }

    $targetPassword = $target->password;
    $targetOtpVerifiedAt = $target->otp_verified_at;

    $browser->asVerified($administrator);
    $startSessionId = $app['session.store']->getId();
    $startResponse = $browser->postWithCsrf(
        route('system.users.login-as', $target)
    );
    $startResponse->assertRedirect(route('dashboard'));

    $impersonation = $app['session.store']->get('user_impersonation');
    $ensure(is_array($impersonation), 'The impersonation context was not stored as an array.');
    $ensure(
        (string) ($impersonation['administrator_id'] ?? '') === (string) $administrator->id,
        'The impersonation context did not preserve the administrator ID.'
    );
    $ensure(
        (string) ($impersonation['user_id'] ?? '') === (string) $target->id,
        'The impersonation context did not preserve the target user ID.'
    );
    $ensure(
        (string) Auth::id() === (string) $target->id,
        'The start endpoint did not authenticate as the target user.'
    );
    $ensure(
        $startSessionId !== $app['session.store']->getId(),
        'The session ID was not rotated when impersonation started.'
    );

    $targetDashboard = $browser->get(route('dashboard'));
    $targetDashboard->assertOk()->assertSee($target->name);
    $dashboardContent = (string) $targetDashboard->getContent();
    $ensure(
        str_contains($dashboardContent, 'attp-impersonation-banner')
        && str_contains($dashboardContent, route('impersonation.stop')),
        'The impersonated workspace did not render a return-to-admin control.'
    );

    $contextAudit = SystemAuditLog::query()->create([
        'user_id' => $target->id,
        'module' => 'user_management',
        'action' => 'user_impersonation_context_smoke',
        'description' => 'Verifies audit attribution during impersonation.',
        'status_code' => 200,
        'payload' => ['probe' => true],
    ]);
    $context = (array) data_get($contextAudit->payload, '_impersonation', []);
    $ensure(
        (string) ($context['administrator_id'] ?? '') === (string) $administrator->id,
        'An audit written during impersonation did not retain the administrator ID.'
    );
    $ensure(
        (string) ($context['impersonated_user_id'] ?? '') === (string) $target->id,
        'An audit written during impersonation did not retain the effective user ID.'
    );

    $stopSessionId = $app['session.store']->getId();
    $stopResponse = $browser->postWithCsrf(route('impersonation.stop'));
    $stopResponse->assertRedirect(route('system.users.index'));
    $ensure(
        (string) Auth::id() === (string) $administrator->id,
        'Stopping impersonation did not restore the administrator.'
    );
    $ensure(
        ! $app['session.store']->has('user_impersonation'),
        'Stopping impersonation did not remove its session context.'
    );
    $ensure(
        (string) $app['session.store']->get('otp_verified_user_id', '') !== (string) $target->id,
        'Stopping impersonation retained OTP state belonging to the target user.'
    );
    $ensure(
        $stopSessionId !== $app['session.store']->getId(),
        'The session ID was not rotated when impersonation stopped.'
    );
    $browser->get(route('system.users.index'))->assertOk();

    $target->refresh();
    $ensure($target->password === $targetPassword, 'Impersonation changed the target password.');
    $ensure($target->must_change_password, 'Impersonation cleared the target password-change requirement.');
    $ensure(
        $target->otp_verified_at?->equalTo($targetOtpVerifiedAt) ?? $targetOtpVerifiedAt === null,
        'Impersonation changed the target account OTP verification timestamp.'
    );

    $startedAudit = SystemAuditLog::query()
        ->where('action', 'user_impersonation_started')
        ->where('user_id', $administrator->id)
        ->latest()
        ->get()
        ->first(fn (SystemAuditLog $log) => $payloadContains($log, (string) $target->id));
    $stoppedAudit = SystemAuditLog::query()
        ->where('action', 'user_impersonation_stopped')
        ->where('user_id', $administrator->id)
        ->latest()
        ->get()
        ->first(fn (SystemAuditLog $log) => $payloadContains($log, (string) $target->id));
    $ensure($startedAudit !== null, 'The administrator-attributed impersonation-start audit was not recorded.');
    $ensure($stoppedAudit !== null, 'The administrator-attributed impersonation-stop audit was not recorded.');

    // A custom vendor layout and the not.funding.partner redirect must never
    // trap an administrator inside the impersonated identity.
    $browser->asVerified($administrator);
    $browser->postWithCsrf(route('system.users.login-as', $vendorTarget))
        ->assertRedirect(route('vendor.dashboard'));
    $ensure(
        (string) Auth::id() === (string) $vendorTarget->id,
        'The vendor target was not authenticated.'
    );
    $browser->postWithCsrf(route('impersonation.stop'))
        ->assertRedirect(route('system.users.index'));
    $ensure(
        (string) Auth::id() === (string) $administrator->id,
        'The administrator could not return from a vendor impersonation.'
    );

    // Login As follows normal-login semantics for an expired temporary block,
    // preventing a vendor portal 403 after the identity has already switched.
    $browser->asVerified($administrator);
    $assertRedirectTo(
        $browser->postWithCsrf(route('system.users.login-as', $expiredBlockedVendor)),
        route('vendor.dashboard'),
        'Expired-block vendor impersonation failed.'
    );
    $expiredBlockedVendor->refresh();
    $ensure(! $expiredBlockedVendor->is_disabled, 'An expired temporary login block was not released.');
    $assertRedirectTo(
        $browser->postWithCsrf(route('impersonation.stop')),
        route('system.users.index'),
        'Returning from the expired-block vendor failed.'
    );

    // An absolute time limit returns the original administrator even while
    // requests continue to arrive from the impersonated user.
    $browser->asVerified($administrator);
    $assertRedirectTo(
        $browser->postWithCsrf(route('system.users.login-as', $target)),
        route('dashboard'),
        'Starting the expiring impersonation failed.'
    );
    $expiredContext = (array) $app['session.store']->get('user_impersonation');
    $expiredContext['started_at'] = now()->subHours(5)->toIso8601String();
    $app['session.store']->put('user_impersonation', $expiredContext);
    $assertRedirectTo(
        $browser->get(route('dashboard')),
        route('system.users.index'),
        'Expiring impersonation did not return to user management.'
    );
    $ensure(
        (string) Auth::id() === (string) $administrator->id,
        'An expired impersonation session did not restore the administrator.'
    );
    $ensure(
        ! $app['session.store']->has('user_impersonation'),
        'An expired impersonation session retained its recovery context.'
    );

    // The recovery middleware runs before route authentication, so deleting
    // the effective user cannot strand the valid administrator session.
    $browser->asVerified($administrator);
    $assertRedirectTo(
        $browser->postWithCsrf(route('system.users.login-as', $deletableTarget)),
        route('dashboard'),
        'Starting deletable-target impersonation failed.'
    );
    $deletableTarget->delete();
    Auth::forgetUser();
    $assertRedirectTo(
        $browser->postWithCsrf(route('impersonation.stop')),
        route('system.users.index'),
        'Deleting the target did not return to user management.'
    );
    $ensure(
        (string) Auth::id() === (string) $administrator->id,
        'Deleting the impersonated user did not restore the administrator.'
    );
    $ensure(
        ! $app['session.store']->has('user_impersonation'),
        'Deleting the impersonated user retained its recovery context.'
    );

    // Revoking the original administrator invalidates the recovery privilege
    // immediately instead of allowing the target session to keep bypassing
    // authentication gates.
    $browser->asVerified($administrator);
    $assertRedirectTo(
        $browser->postWithCsrf(route('system.users.login-as', $target)),
        route('dashboard'),
        'Starting demotion coverage impersonation failed.'
    );
    $administrator->forceFill([
        'user_type' => 'staff',
        'role_id' => null,
    ])->save();
    Auth::forgetUser();
    $assertRedirectTo(
        $browser->get(route('dashboard')),
        route('login'),
        'Demoting the administrator did not terminate impersonation.'
    );
    $ensure(! Auth::check(), 'A demoted administrator left the impersonated user authenticated.');
    $ensure(
        ! $app['session.store']->has('user_impersonation'),
        'A demoted administrator left an impersonation recovery context in session.'
    );
    $administrator->forceFill([
        'user_type' => 'admin',
        'role_id' => $systemAdminRole->id,
    ])->save();
    $administrator->unsetRelation('role');

    // A normal logout must destroy the recovery context instead of leaving a
    // route that can resurrect an administrator session later.
    $browser->asVerified($administrator);
    $browser->postWithCsrf(route('system.users.login-as', $target))
        ->assertRedirect(route('dashboard'));
    $browser->postWithCsrf(route('logout'))->assertRedirect('/');
    $ensure(! Auth::check(), 'Logout during impersonation left a user authenticated.');
    $ensure(
        ! $app['session.store']->has('user_impersonation'),
        'Logout during impersonation retained the administrator recovery context.'
    );
    $browser->postWithCsrf(route('impersonation.stop'))
        ->assertRedirect(route('login'));
    $ensure(! Auth::check(), 'The stop route restored an administrator after logout.');

    echo "USER_IMPERSONATION_OK\n";
} finally {
    Auth::guard('web')->logout();
    config(['security.require_login_otp_locally' => $originalLocalOtpRequirement]);
    DB::rollBack();
}
