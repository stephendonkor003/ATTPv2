<?php

use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route as IlluminateRoute;

function bootUserImpersonationContractApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

function userImpersonationContractSource(string $relativePath): string
{
    $path = dirname(__DIR__, 2).'/'.ltrim($relativePath, '/');

    expect(is_file($path))->toBeTrue("Expected impersonation file [{$relativePath}] to exist.");

    return is_file($path) ? (string) file_get_contents($path) : '';
}

function userImpersonationContractRoute($application, string $name): IlluminateRoute
{
    $route = $application['router']->getRoutes()->getByName($name);

    expect($route)->not->toBeNull("Expected route [{$name}] to be registered.");

    return $route;
}

it('registers post-only start and stop routes with boundaries that remain usable after the identity switch', function () {
    [$application, $bootedHere] = bootUserImpersonationContractApplication();

    try {
        $start = userImpersonationContractRoute($application, 'system.users.login-as');
        $stop = userImpersonationContractRoute($application, 'impersonation.stop');

        expect($start->uri())
            ->toBe('system/users/{user}/login-as')
            ->and($start->methods())
            ->toBe(['POST'])
            ->and($start->getActionName())
            ->toBe('App\\Http\\Controllers\\System\\UserImpersonationController@store');

        $startMiddleware = $start->gatherMiddleware();

        expect($startMiddleware)
            ->toContain('web')
            ->toContain('auth')
            ->toContain('verified')
            ->toContain('not.funding.partner')
            ->toContain('permission:users.manage');

        expect($stop->uri())
            ->toBe('impersonation/stop')
            ->and($stop->methods())
            ->toBe(['POST'])
            ->and($stop->getActionName())
            ->toBe('App\\Http\\Controllers\\System\\UserImpersonationController@destroy');

        $stopMiddleware = $stop->gatherMiddleware();

        expect($stopMiddleware)
            ->toContain('web')
            ->toContain('auth')
            ->not->toContain('verified')
            ->not->toContain('not.funding.partner')
            ->not->toContain('permission:users.manage');
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('keeps the impersonation state bounded, rotates sessions, and records both security events', function () {
    $controller = userImpersonationContractSource(
        'app/Http/Controllers/System/UserImpersonationController.php'
    );

    expect($controller)
        ->toContain('class UserImpersonationController')
        ->toContain('function store(')
        ->toContain('function destroy(')
        ->toContain('UserImpersonation::SESSION_KEY')
        ->toContain("'administrator_id'")
        ->toContain("'user_id'")
        ->toContain('isAdmin()')
        ->toContain('isSuperAdmin()')
        ->toContain('user_impersonation_started')
        ->toContain('user_impersonation_stopped')
        ->toContain('SystemAuditLog')
        ->toContain('if (! $audited)')
        ->toContain('security audit was unavailable');

    expect(
        str_contains($controller, 'hasActiveLoginBlock()')
        || str_contains($controller, 'is_disabled')
    )->toBeTrue('The controller must reject disabled target accounts.');

    expect(str_contains($controller, 'is_blacklisted'))
        ->toBeTrue('The controller must reject blacklisted target accounts.');

    preg_match_all(
        '/(?:->regenerate\(\)|->migrate\(\s*true\s*\))/',
        $controller,
        $sessionRotations
    );
    expect(count($sessionRotations[0]))
        ->toBeGreaterThanOrEqual(2, 'Both entering and leaving impersonation must rotate the session ID.');

    preg_match_all('/(?:Auth::login|->login)\s*\(/', $controller, $guardLogins);
    expect(count($guardLogins[0]))
        ->toBeGreaterThanOrEqual(2, 'The controller must switch to the target and restore the administrator.');
});

it('shows an admin-only login-as action with csrf protection on the users table', function () {
    $index = userImpersonationContractSource('resources/views/system/users/index.blade.php');
    $routePosition = strpos($index, "route('system.users.login-as'");

    expect($routePosition)->not->toBeFalse('The users table must link to the login-as route.');

    $actionMarkup = substr($index, max(0, (int) $routePosition - 700), 1800);

    expect($actionMarkup)
        ->toContain('method="POST"')
        ->toContain('@csrf')
        ->toContain('Login as');

    expect($index)
        ->toContain('auth()->id()')
        ->toContain('isAdmin()')
        ->toContain('isSuperAdmin()');
});

it('provides a shared csrf-protected return control in every authenticated root layout', function () {
    $banner = userImpersonationContractSource(
        'resources/views/layouts/partials/impersonation-banner.blade.php'
    );

    expect($banner)
        ->toContain('UserImpersonation::state')
        ->toContain("route('impersonation.stop')")
        ->toContain('method="POST"')
        ->toContain('@csrf');

    expect(
        str_contains($banner, 'Return to')
        || str_contains($banner, 'Stop impersonating')
    )->toBeTrue('The banner must clearly identify the action that restores the administrator.');

    foreach ([
        'resources/views/layouts/app.blade.php',
        'resources/views/layouts/partner.blade.php',
        'resources/views/layouts/vendor.blade.php',
        'resources/views/layouts/think-tank.blade.php',
        'resources/views/layouts/ttl.blade.php',
        'resources/views/layouts/administrative-assistant.blade.php',
    ] as $layoutPath) {
        expect(userImpersonationContractSource($layoutPath))
            ->toContain('layouts.partials.impersonation-banner');
    }
});

it('revalidates bounded impersonation sessions before authentication bypass middleware', function () {
    $bootstrap = userImpersonationContractSource('bootstrap/app.php');
    $validator = userImpersonationContractSource('app/Http/Middleware/ValidateUserImpersonation.php');
    $security = userImpersonationContractSource('config/security.php');

    expect($bootstrap)
        ->toContain('ValidateUserImpersonation::class')
        ->toContain('EnsurePasswordNotExpired::class')
        ->toContain('EnsureOtpVerified::class')
        ->toContain('prependToPriorityList(')
        ->toContain('AuthenticatesRequests::class');
    $webStack = str($bootstrap)
        ->after('$middleware->web(append: [')
        ->before(']);')
        ->toString();
    expect(strpos($webStack, 'ValidateUserImpersonation::class'))
        ->toBeLessThan(strpos($webStack, 'EnsurePasswordNotExpired::class'));

    expect($validator)
        ->toContain('administrator_no_longer_authorized')
        ->toContain('impersonated_user_missing')
        ->toContain('effective_user_mismatch')
        ->toContain('target_access_revoked')
        ->toContain('target_became_administrator')
        ->toContain('session_expired')
        ->toContain('user_impersonation_terminated')
        ->toContain('Auth::guard(');
    expect($security)->toContain('impersonation_ttl_minutes');
});

it('keeps the real administrator visible in audits and on error recovery pages', function () {
    $auditModel = userImpersonationContractSource('app/Models/SystemAuditLog.php');
    $auditController = userImpersonationContractSource('app/Http/Controllers/SystemAuditController.php');
    $auditView = userImpersonationContractSource('resources/views/system/audit/index.blade.php');

    expect($auditModel)
        ->toContain("'_impersonation'")
        ->toContain("'administrator_id'")
        ->toContain("'impersonated_user_id'");
    expect($auditController)->toContain('$impersonators');
    expect($auditView)
        ->toContain('ACTING AS USER')
        ->toContain('Administrator:');

    expect(userImpersonationContractSource('resources/views/errors/minimal.blade.php'))
        ->toContain('impersonation-banner');
    foreach ([403, 500] as $status) {
        expect(userImpersonationContractSource("resources/views/errors/{$status}.blade.php"))
            ->toContain("@extends('errors.minimal')");
    }
    foreach ([404, 419] as $status) {
        expect(userImpersonationContractSource("resources/views/errors/{$status}.blade.php"))
            ->toContain('impersonation-banner');
    }
});
