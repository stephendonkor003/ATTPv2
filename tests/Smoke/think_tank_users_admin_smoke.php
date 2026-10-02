<?php

use App\Models\Consortium;
use App\Models\ConsortiumThinkTank;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemAuditLog;
use App\Models\User;
use App\Notifications\ThinkTankPortalPasswordResetNotification;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTank\ThinkTankInvitationService;
use App\Services\ThinkTank\ThinkTankSessionService;
use App\Services\ThinkTank\ThinkTankUserManagementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
    $runToken = Str::lower(Str::random(10));
    $manageUsers = Permission::query()->firstOrCreate(
        ['name' => 'users.manage'],
        ['module' => 'System', 'description' => 'Manage application users'],
    );
    $adminRole = Role::query()->firstOrCreate(
        ['name' => 'System Admin'],
        ['description' => 'System administrator'],
    );
    $adminRole->permissions()->syncWithoutDetaching([$manageUsers->getKey()]);
    $admin = User::query()->create([
        'name' => 'Think Tank Reset Smoke Administrator',
        'email' => "think-tank-reset-admin-{$runToken}@example.test",
        'password' => Hash::make('Synthetic-Smoke-Password!9'),
        'user_type' => 'admin',
        'role_id' => $adminRole->getKey(),
        'must_change_password' => false,
        'password_changed_at' => now(),
        'otp_verified_at' => now(),
        'is_disabled' => false,
        'is_blacklisted' => false,
    ]);
    $admin->forceFill(['email_verified_at' => now()])->save();
    $consortium = Consortium::query()->create([
        'code' => 'TT-RESET-'.Str::upper($runToken),
        'name' => 'Think Tank reset smoke consortium',
        'country' => 'Ghana',
        'region' => 'West Africa',
        'approved_budget' => 0,
        'currency' => 'USD',
        'status' => 'active',
        'mandate' => 'Transaction-isolated reset workflow verification.',
    ]);
    $member = ConsortiumThinkTank::query()->create([
        'consortium_id' => $consortium->getKey(),
        'name' => 'Think Tank reset smoke member',
        'country' => 'Ghana',
        'email' => "think-tank-reset-member-{$runToken}@example.test",
        'role' => 'lead',
        'budget_allocated' => 0,
        'status' => 'active',
        'joined_at' => now()->toDateString(),
    ]);
    $browser = new ThinkTankUsersAdminBrowser($app);
    $email = 'think-tank-user-admin-smoke-'.Str::lower(Str::random(10)).'@example.test';

    $indexResponse = $browser->getAs($admin, route('system.think-tank-users.index'));
    $ensure(
        $indexResponse->getStatusCode() === 200,
        'Think Tank users index returned '.$indexResponse->getStatusCode().' and redirected to '.($indexResponse->headers->get('Location') ?: 'nowhere').'.',
    );
    $indexResponse
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
        ->assertSee('Email secure reset link');
    $createdUser->forceFill([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'otp_verified_at' => now(),
        'email_verified_at' => now(),
    ])->save();
    $browser->getAs($createdUser, route('system.think-tank-users.index'))->assertForbidden();
    $browser->getAs($createdUser, route('system.think-tank-users.show', $createdUser))->assertForbidden();
    $browser->postAs($createdUser, route('system.think-tank-users.reset-password', $createdUser), [])->assertForbidden();
    $browser->postAs($admin, route('system.think-tank-users.reset-password', $admin), [])->assertNotFound();

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
    ])->assertRedirect()->assertSessionHasErrors('access_level');
    $ensure($soleAdministrator->fresh()->think_tank_access_level === User::THINK_TANK_ACCESS_ADMIN, 'A sole administrator was demoted despite the last-administrator guard.');
    $ensure((string) $isolatedMember->fresh()->portal_user_id === (string) $soleAdministrator->id, 'The last active primary-administrator assignment was unexpectedly cleared.');

    $oldPasswordHash = $createdUser->password;
    $browser->postAs($admin, route('system.think-tank-users.reset-password', $createdUser), [])
        ->assertRedirect()
        ->assertSessionHas('success');
    $createdUser->refresh();
    $ensure($createdUser->password !== $oldPasswordHash, 'The administrator reset did not revoke the previous password.');
    Notification::assertSentToTimes($createdUser, ThinkTankPortalPasswordResetNotification::class, 3);
    Notification::assertSentTo(
        $createdUser,
        ThinkTankPortalPasswordResetNotification::class,
        static function (ThinkTankPortalPasswordResetNotification $notification) use ($createdUser): bool {
            $mail = $notification->toMail($createdUser);
            $copy = implode(' ', [...$mail->introLines, ...$mail->outroLines]);

            return $mail->subject === 'Action required: Reset your Think Tank Portal password'
                && str_contains($copy, 'Your previous password no longer works')
                && ! str_contains($copy, 'safely ignore');
        }
    );

    $createdUser->forceFill([
        'must_change_password' => false,
        'password_changed_at' => now()->subDay(),
        'otp_verified_at' => now(),
        'remember_token' => 'failure-preservation-token',
    ])->save();
    $createdUser->refresh();
    $securityFields = ['password', 'must_change_password', 'password_changed_at', 'otp_verified_at', 'remember_token'];
    $beforeFailedDelivery = collect($securityFields)
        ->mapWithKeys(fn (string $field): array => [$field => $createdUser->getRawOriginal($field)])
        ->all();
    /** @var Dispatcher $notificationDispatcher */
    $notificationDispatcher = app(Dispatcher::class);
    $failingDispatcher = Mockery::mock(Dispatcher::class);
    $failingDispatcher->shouldReceive('send')->once()->andThrow(new RuntimeException('Synthetic mail transport failure.'));
    Notification::swap($failingDispatcher);

    try {
        $browser->postAs($admin, route('system.think-tank-users.reset-password', $createdUser), [])
            ->assertRedirect()
            ->assertSessionHas('error')
            ->assertSessionMissing('success');
    } finally {
        Notification::swap($notificationDispatcher);
    }

    $createdUser->refresh();
    $afterFailedDelivery = collect($securityFields)
        ->mapWithKeys(fn (string $field): array => [$field => $createdUser->getRawOriginal($field)])
        ->all();
    $ensure(
        $afterFailedDelivery === $beforeFailedDelivery,
        'A failed reset-email delivery changed the password, MFA state, or active-session security token.'
    );

    $createdUser->forceFill(['is_blacklisted' => true])->save();
    $createdUser->refresh();
    $blacklistedState = collect($securityFields)
        ->mapWithKeys(fn (string $field): array => [$field => $createdUser->getRawOriginal($field)])
        ->all();
    $browser->postAs($admin, route('system.think-tank-users.reset-password', $createdUser), [])
        ->assertStatus(422);
    $createdUser->refresh();
    $ensure(
        collect($securityFields)
            ->mapWithKeys(fn (string $field): array => [$field => $createdUser->getRawOriginal($field)])
            ->all() === $blacklistedState,
        'A blacklisted reset attempt changed protected account security state.'
    );
    Notification::assertSentToTimes($createdUser, ThinkTankPortalPasswordResetNotification::class, 3);
    $createdUser->forceFill(['is_blacklisted' => false])->save();

    $audit = Mockery::mock(ThinkTankApiAuditService::class);
    $audit->shouldNotReceive('required');
    $invitations = Mockery::mock(ThinkTankInvitationService::class);
    $concurrentlyChangedEmail = 'changed-during-reset-'.Str::lower(Str::random(8)).'@example.test';
    $invitations->shouldReceive('send')
        ->once()
        ->withArgs(fn (User $target, bool $invitation, bool $administratorInitiated): bool => (string) $target->getKey() === (string) $createdUser->getKey()
                && $invitation === false
                && $administratorInitiated === true)
        ->andReturnUsing(function () use ($createdUser, $concurrentlyChangedEmail): bool {
            User::query()->whereKey($createdUser->getKey())->update(['email' => $concurrentlyChangedEmail]);

            return true;
        });
    $invitations->shouldReceive('invalidateOutstandingToken')
        ->once()
        ->withArgs(fn (User $target): bool => (string) $target->email === (string) $createdUser->email);
    $sessions = Mockery::mock(ThinkTankSessionService::class);
    $sessions->shouldNotReceive('invalidateMfa');
    $sessions->shouldNotReceive('revokeAllSessions');
    $management = new ThinkTankUserManagementService($audit, $invitations, $sessions);
    $request = Request::create(
        route('system.think-tank-users.reset-password', $createdUser),
        'POST',
    );
    $request->setUserResolver(fn (): User => $admin);
    $stateBeforeConcurrentChange = collect($securityFields)
        ->mapWithKeys(fn (string $field): array => [$field => $createdUser->getRawOriginal($field)])
        ->all();

    $ensure(
        $management->resetPasswordForSystemOversight($request, $admin, $member, $createdUser) === false,
        'A reset continued after the target email changed during delivery.'
    );
    $createdUser->refresh();
    $ensure(
        collect($securityFields)
            ->mapWithKeys(fn (string $field): array => [$field => $createdUser->getRawOriginal($field)])
            ->all() === $stateBeforeConcurrentChange,
        'A concurrent target-email change still revoked password, MFA, or session state.'
    );
    $createdUser->forceFill(['email' => $updatedEmail])->save();

    $browser->getAs($admin, route('system.think-tank-users.index', [
        'q' => $updatedEmail,
        'access_level' => User::THINK_TANK_ACCESS_ME,
        'account_status' => 'disabled',
    ]))->assertOk()->assertSee($updatedEmail);

    foreach (['think_tank_user_created', 'think_tank_user_updated', 'think_tank_user_password_reset_requested'] as $action) {
        $ensure(
            SystemAuditLog::query()->where('action', $action)->where('payload->staff_user_id', $createdUser->id)->exists(),
            "The {$action} audit event was not recorded."
        );
    }

    echo "THINK_TANK_USERS_ADMIN_OK\n";
} finally {
    DB::rollBack();
}
