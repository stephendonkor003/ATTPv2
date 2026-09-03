<?php

use App\Models\ConsortiumThinkTank;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Notifications\ThinkTankPortalPasswordResetNotification;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

class ThinkTankUsersAdminBrowser
{
    use InteractsWithAuthentication;
    use InteractsWithSession;
    use MakesHttpRequests;

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function getAs(User $user, string $uri)
    {
        $this->actingAs($user)->withSession([
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);

        return $this->get($uri);
    }

    public function postAs(User $user, string $uri, array $data)
    {
        $token = Str::random(40);
        $this->actingAs($user)->withSession([
            '_token' => $token,
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);

        return $this->post($uri, ['_token' => $token, ...$data]);
    }

    public function putAs(User $user, string $uri, array $data)
    {
        $token = Str::random(40);
        $this->actingAs($user)->withSession([
            '_token' => $token,
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);

        return $this->put($uri, ['_token' => $token, ...$data]);
    }
}

$ensure = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

Notification::fake();
DB::beginTransaction();

try {
    $admin = User::query()
        ->whereHas('role', fn ($role) => $role->where('name', 'System Admin'))
        ->where('is_disabled', false)
        ->where('is_blacklisted', false)
        ->firstOrFail();
    $member = ConsortiumThinkTank::query()
        ->whereNotNull('consortium_id')
        ->where('status', 'active')
        ->firstOrFail();
    $browser = new ThinkTankUsersAdminBrowser($app);
    $email = 'think-tank-user-admin-smoke-'.Str::lower(Str::random(10)).'@example.test';

    $browser->getAs($admin, route('system.think-tank-users.index'))
        ->assertOk()
        ->assertSee('Think Tank users')
        ->assertSee('Think Tank Users')
        ->assertSee('Create a Think Tank user')
        ->assertSee('Procurement Officer')
        ->assertSee('M&amp;E Officer', false);

    $createResponse = $browser->postAs($admin, route('system.think-tank-users.store'), [
        'name' => 'Think Tank User Smoke Officer',
        'email' => $email,
        'think_tank_member_id' => $member->id,
        'access_level' => User::THINK_TANK_ACCESS_PROCUREMENT,
    ]);
    $createResponse->assertRedirect(route('system.think-tank-users.index'))->assertSessionHasNoErrors();
    $createResponse->assertSessionMissing('temporary_password');

    $createdUser = User::query()->where('email', $email)->firstOrFail();
    $ensure($createdUser->user_type === 'think_tank', 'The created account is not a Think Tank user.');
    $ensure((string) $createdUser->think_tank_member_id === (string) $member->id, 'The created account was assigned to the wrong Think Tank.');
    $ensure($createdUser->think_tank_access_level === User::THINK_TANK_ACCESS_PROCUREMENT, 'The procurement role was not assigned.');
    $ensure((bool) $createdUser->must_change_password, 'The new account was not marked for secure password setup.');
    Notification::assertSentTo($createdUser, ThinkTankPortalPasswordResetNotification::class);
    $browser->getAs($admin, route('system.think-tank-users.show', $createdUser))
        ->assertOk()
        ->assertSee('View and edit user information')
        ->assertSee($email)
        ->assertSee('Revoke access and send reset link');
    $createdUser->forceFill([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'otp_verified_at' => now(),
    ])->save();
    $browser->getAs($createdUser, route('system.think-tank-users.index'))->assertForbidden();
    $browser->getAs($createdUser, route('system.think-tank-users.show', $createdUser))->assertForbidden();

    $updatedEmail = 'think-tank-user-updated-'.Str::lower(Str::random(10)).'@example.test';
    $browser->putAs($admin, route('system.think-tank-users.update', $createdUser), [
        'name' => 'Updated Think Tank M&E Officer',
        'email' => $updatedEmail,
        'think_tank_member_id' => $member->id,
        'access_level' => User::THINK_TANK_ACCESS_ME,
        'account_status' => 'disabled',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $createdUser->refresh();
    $ensure($createdUser->name === 'Updated Think Tank M&E Officer', 'The user name was not updated.');
    $ensure($createdUser->email === $updatedEmail, 'The user email address was not updated.');
    $ensure($createdUser->think_tank_access_level === User::THINK_TANK_ACCESS_ME, 'The user was not changed to M&E Officer.');
    $ensure($createdUser->hasActiveLoginBlock(), 'The Think Tank user login was not disabled.');

    $isolatedMember = ConsortiumThinkTank::query()->create([
        'consortium_id' => $member->consortium_id,
        'name' => 'Sole Administrator Test '.Str::upper(Str::random(6)),
        'country' => $member->country,
        'email' => 'sole-admin-member-'.Str::lower(Str::random(8)).'@example.test',
        'role' => 'member',
        'budget_allocated' => 0,
        'status' => 'active',
        'joined_at' => now()->toDateString(),
    ]);
    $soleAdministrator = User::query()->create([
        'name' => 'Sole Think Tank Administrator',
        'email' => 'sole-think-tank-admin-'.Str::lower(Str::random(8)).'@example.test',
        'password' => 'Password123!',
        'user_type' => 'think_tank',
        'role_id' => $createdUser->role_id,
        'think_tank_member_id' => $isolatedMember->id,
        'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
        'must_change_password' => false,
        'is_disabled' => false,
        'is_blacklisted' => false,
    ]);
    $isolatedMember->update(['portal_user_id' => $soleAdministrator->id]);
    $browser->putAs($admin, route('system.think-tank-users.update', $soleAdministrator), [
        'name' => $soleAdministrator->name,
        'email' => $soleAdministrator->email,
        'think_tank_member_id' => $isolatedMember->id,
        'access_level' => User::THINK_TANK_ACCESS_PROCUREMENT,
        'account_status' => 'disabled',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $ensure($soleAdministrator->fresh()->think_tank_access_level === User::THINK_TANK_ACCESS_PROCUREMENT, 'A sole administrator could not be reassigned to another officer role.');
    $ensure($isolatedMember->fresh()->portal_user_id === null, 'The empty primary-administrator assignment was not handled automatically.');

    $oldPasswordHash = $createdUser->password;
    $browser->postAs($admin, route('system.think-tank-users.reset-password', $createdUser), []);
    $createdUser->refresh();
    $ensure($createdUser->password !== $oldPasswordHash, 'The administrator reset did not revoke the previous password.');
    Notification::assertSentToTimes($createdUser, ThinkTankPortalPasswordResetNotification::class, 2);

    $browser->getAs($admin, route('system.think-tank-users.index', [
        'q' => $updatedEmail,
        'access_level' => User::THINK_TANK_ACCESS_ME,
        'account_status' => 'disabled',
    ]))->assertOk()->assertSee($updatedEmail);

    foreach (['think_tank_user_created', 'think_tank_user_updated', 'think_tank_user_password_reset_initiated'] as $action) {
        $ensure(
            SystemAuditLog::query()->where('action', $action)->where('payload->staff_user_id', $createdUser->id)->exists(),
            "The {$action} audit event was not recorded."
        );
    }

    echo "THINK_TANK_USERS_ADMIN_OK\n";
} finally {
    DB::rollBack();
}
