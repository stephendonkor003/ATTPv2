<?php

it('includes Think Tank accounts in the system user directory and keeps tenant administration guarded', function (): void {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/System/UserAccessController.php');
    $view = file_get_contents($root.'/resources/views/system/users/index.blade.php');

    expect($controller)
        ->not->toContain("->orWhere('user_type', '!=', 'think_tank')")
        ->not->toContain("->whereNull('think_tank_member_id')")
        ->toContain("'assignedThinkTankMembership.consortium'")
        ->toContain("'thinkTankMembership.consortium'");

    expect($view)
        ->toContain("$".'isThinkTankUser')
        ->toContain("route('system.think-tank-users.show', $".'user->id)')
        ->toContain('Tenant scoped')
        ->toContain('@if (! $isThinkTankUser)');
});

it('supports secure email and direct temporary-password recovery without auditing secrets', function (): void {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/System/ThinkTankUserController.php');
    $service = file_get_contents($root.'/app/Services/ThinkTank/ThinkTankUserManagementService.php');
    $routes = file_get_contents($root.'/routes/web.php');
    $view = file_get_contents($root.'/resources/views/think-tank-users/show.blade.php');
    $auditMiddleware = file_get_contents($root.'/app/Http/Middleware/SystemAuditLogger.php');

    expect($controller)
        ->toContain('current_password:web')
        ->toContain('Password::min(12)->mixedCase()->letters()->numbers()->symbols()')
        ->toContain('setTemporaryPasswordForSystemOversight');
    expect($service)
        ->toContain("'must_change_password' => true")
        ->toContain('revokeAllSessions($lockedTarget)')
        ->toContain("'password' => \$temporaryPassword");
    expect($routes)->toContain("set-temporary-password");
    expect($view)
        ->toContain('Revoke access and send reset link')
        ->toContain('Set temporary password');
    expect($auditMiddleware)->toContain("'administrator_password'");
});
