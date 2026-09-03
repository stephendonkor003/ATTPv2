<?php

use App\Console\Commands\AuditThinkTankPortalUserLinks;

function projectSource(string $path): string
{
    $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);

    if ($contents === false) {
        throw new RuntimeException("Could not read {$path}.");
    }

    return $contents;
}

it('removes plaintext credentials from every active legacy Think Tank account writer', function () {
    $paths = [
        'app/Http/Controllers/ThinkTankPortalController.php',
        'app/Http/Controllers/System/ThinkTankUserController.php',
        'app/Http/Controllers/ConsortiumOperationsController.php',
        'app/Http/Controllers/AdminThinkTankController.php',
        'app/Mail/ThinkTankPortalWelcome.php',
        'resources/views/emails/think-tank/portal-welcome.blade.php',
        'resources/views/think-tank/team-access.blade.php',
        'resources/views/think-tank-users/index.blade.php',
        'resources/views/think-tank-users/show.blade.php',
    ];
    $sources = collect($paths)->mapWithKeys(fn (string $path): array => [$path => projectSource($path)]);

    foreach ($sources as $source) {
        expect($source)
            ->not->toContain('temporary_password')
            ->not->toContain('$temporaryPassword')
            ->not->toContain('Temporary password:');
    }

    expect($sources['app/Http/Controllers/ThinkTankPortalController.php'])
        ->toContain('ThinkTankUserManagementService')
        ->toContain('CreateThinkTankUserData::from($data)')
        ->and($sources['app/Http/Controllers/System/ThinkTankUserController.php'])
        ->toContain('$this->userManagement->resetPasswordForSystemOversight(')
        ->not->toContain('Hash::make(')
        ->not->toContain('Str::password(')
        ->and($sources['app/Http/Controllers/ConsortiumOperationsController.php'])
        ->toContain('resolveOrCreateUnassignedAdministrator(')
        ->toContain('$this->invitations->send($portalUser, true)')
        ->and($sources['app/Http/Controllers/AdminThinkTankController.php'])
        ->toContain('resolveOrCreateUnassignedAdministrator(')
        ->toContain('$this->invitations->send($portalUser, true)')
        ->and($sources['app/Mail/ThinkTankPortalWelcome.php'])
        ->not->toContain("route('login')")
        ->toContain("config('think_tank_portal.frontend_url')");
});

it('uses only explicit user tenant assignments for the new Think Tank API', function () {
    $access = projectSource('app/Services/ThinkTank/ThinkTankAccountAccessService.php');
    $management = projectSource('app/Services/ThinkTank/ThinkTankUserManagementService.php');
    $viewer = projectSource('app/Http/Resources/ThinkTankViewerResource.php');
    $userResource = projectSource('app/Http/Resources/ThinkTankUserResource.php');
    $userManagerMiddleware = projectSource('app/Http/Middleware/EnsureThinkTankApiUserManager.php');

    expect($access)
        ->toContain('assignedThinkTankMembership()->first()')
        ->toContain('trim((string) $user->think_tank_access_level)')
        ->not->toContain('resolvedThinkTankMembership()')
        ->not->toContain('resolvedThinkTankAccessLevel()')
        ->and($management)
        ->toContain("->where('think_tank_member_id', \$tenant->getKey())")
        ->not->toContain("->orWhere('id', \$tenant->portal_user_id)")
        ->and($viewer)
        ->not->toContain('resolvedThinkTankMembership()')
        ->not->toContain('resolvedThinkTankAccessLevel()')
        ->and($userResource)
        ->not->toContain('resolvedThinkTankAccessLevel()')
        ->and($userManagerMiddleware)
        ->toContain('$user->think_tank_access_level === User::THINK_TANK_ACCESS_ADMIN')
        ->not->toContain('resolvedThinkTankAccessLevel()');
});

it('rechecks locked mutation authority and protects administrator identity changes', function () {
    $management = projectSource('app/Services/ThinkTank/ThinkTankUserManagementService.php');
    $systemController = projectSource('app/Http/Controllers/System/ThinkTankUserController.php');
    $apiController = projectSource('app/Http/Controllers/Api/V1/ThinkTank/UserController.php');

    expect($management)
        ->toContain('$this->lockMutationAuthority(')
        ->toContain("->whereKey(\$tenant->getKey())\n            ->lockForUpdate()")
        ->toContain("->whereKey(\$actor->getKey())\n            ->lockForUpdate()")
        ->toContain("\$lockedTenant->status === 'active'")
        ->toContain('! $lockedActor->is_blacklisted && ! $lockedActor->is_disabled')
        ->toContain("\$lockedActor->user_type === 'think_tank'")
        ->toContain('$lockedActor->think_tank_access_level === User::THINK_TANK_ACCESS_ADMIN')
        ->toContain("\$lockedActor->hasPermission('think_tank.users.manage')")
        ->toContain('You cannot change your own administrator email here.')
        ->toContain('$this->sessions->invalidateMfa($lockedTarget)')
        ->toContain('$this->sessions->revokeAllSessions($lockedTarget)')
        ->and($systemController)
        ->toContain('$this->userManagement->createForSystemOversight(')
        ->toContain('$this->userManagement->resetPasswordForSystemOversight(')
        ->and($apiController)
        ->toContain('false => \'User updated, but the invitation could not be delivered.');
});

it('fails closed on cross-tenant reuse and provides only guarded read-only diagnostics', function () {
    $management = projectSource('app/Services/ThinkTank/ThinkTankUserManagementService.php');
    $consortium = projectSource('app/Http/Controllers/ConsortiumOperationsController.php');
    $admin = projectSource('app/Http/Controllers/AdminThinkTankController.php');
    $migration = projectSource('database/migrations/2026_09_01_220000_enforce_unique_think_tank_portal_user_assignment.php');
    $audit = projectSource('app/Console/Commands/AuditThinkTankPortalUserLinks.php');
    $defaults = (new ReflectionClass(AuditThinkTankPortalUserLinks::class))->getDefaultProperties();

    expect($management)
        ->toContain('assertCanBeAssignedToMembership(')
        ->toContain("->where('portal_user_id', \$user->getKey())")
        ->toContain('filled($assignedId)')
        ->and($consortium)
        ->toContain('$this->userManagement->assertCanBeAssignedToMembership($portalUser)')
        ->toContain('$this->userManagement->assignAdministrator($portalUser, $member)')
        ->and($admin)
        ->toContain('$this->userManagement->assertCanBeAssignedToMembership($user, $expectedMembership)')
        ->toContain('$this->userManagement->assignAdministrator($portalUser, $thinkTank)')
        ->and($migration)
        ->toContain("havingRaw('COUNT(*) > 1')")
        ->toContain("->unique('portal_user_id', self::INDEX)")
        ->toContain('throw new \\RuntimeException(')
        ->not->toContain('->update(')
        ->not->toContain('->delete(')
        ->and($audit)
        ->toContain('Read-only audit: no account or membership data was changed.')
        ->toContain("orWhereColumn('users.think_tank_member_id', '!=', 'memberships.id')")
        ->not->toContain('->update(')
        ->not->toContain('->delete(')
        ->not->toContain('->insert(')
        ->and($defaults['signature'])->toBe('think-tank:portal-user-links:audit');
});
