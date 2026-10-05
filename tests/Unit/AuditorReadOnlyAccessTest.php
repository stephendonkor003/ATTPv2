<?php

use App\Http\Controllers\EvaluationSubmissionController;
use App\Http\Controllers\System\PermissionController;
use App\Http\Controllers\System\RoleController;
use App\Http\Controllers\System\UserAccessController;
use App\Http\Middleware\EnforceAuditorReadOnly;
use App\Http\Middleware\InjectWebsiteVisitTracker;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ThinkTank\ThinkTankUserManagementService;
use App\Support\AuditorAccess;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

function bootAuditorReadOnlyApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

function auditorReadOnlyRole(string $name = Role::AUDITOR_NAME, bool $persistedFlag = false): Role
{
    return (new Role)->forceFill([
        'name' => $name,
        'is_read_only_auditor' => $persistedFlag,
    ]);
}

function auditorReadOnlyUser(?Role $role = null, string $userType = 'staff'): User
{
    $user = (new User)->forceFill([
        'name' => 'Read-only Auditor',
        'email' => 'auditor-contract@example.test',
        'user_type' => $userType,
    ]);
    $user->setRelation('role', $role ?? auditorReadOnlyRole());
    $user->setRelation('permissions', collect([
        new Permission(['name' => 'users.manage']),
        new Permission(['name' => 'roles.manage']),
    ]));

    return $user;
}

function auditorReadOnlyRequest(
    string $method,
    string $uri,
    ?string $routeName,
    ?User $user,
    mixed $action = null,
): Request {
    $request = Request::create('/'.ltrim($uri, '/'), $method);
    $route = new Route([$method], ltrim($uri, '/'), $action ?? static fn () => null);

    if ($routeName !== null) {
        $route->name($routeName);
    }

    $request->setRouteResolver(static fn () => $route);
    $request->setUserResolver(static fn () => $user);

    return $request;
}

function withBoundAuditorRequest(Container $application, Request $request, Closure $callback): mixed
{
    $hadRequest = $application->bound('request');
    $previousRequest = $hadRequest ? $application->make('request') : null;
    $application->instance('request', $request);

    try {
        return $callback();
    } finally {
        if ($hadRequest) {
            $application->instance('request', $previousRequest);
        } else {
            $application->forgetInstance('request');
        }
    }
}

function auditorReadOnlyDeniedException(Request $request): ?HttpException
{
    $nextWasCalled = false;

    try {
        (new EnforceAuditorReadOnly)->handle(
            $request,
            function () use (&$nextWasCalled): Response {
                $nextWasCalled = true;

                return new Response('unsafe callback reached');
            },
        );

        return null;
    } catch (HttpException $exception) {
        expect($nextWasCalled)->toBeFalse();

        return $exception;
    }
}

it('registers the Auditor boundary in both HTTP middleware stacks', function (): void {
    [$application, $bootedHere] = bootAuditorReadOnlyApplication();

    try {
        $groups = $application->make(HttpKernel::class)->getMiddlewareGroups();

        expect($groups['web'])
            ->toContain(EnforceAuditorReadOnly::class)
            ->and($groups['api'])
            ->toContain(EnforceAuditorReadOnly::class);
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('does not inject the mutating website visit tracker into Auditor pages', function (): void {
    bootAuditorReadOnlyApplication();
    $auditor = auditorReadOnlyUser();
    $request = auditorReadOnlyRequest('GET', 'system/audit', 'system.audit.index', $auditor);
    $html = '<html><body>Audit workspace</body></html>';

    $response = (new InjectWebsiteVisitTracker)->handle(
        $request,
        static fn (): Response => new Response($html, 200, ['Content-Type' => 'text/html']),
    );

    expect($response->getContent())
        ->toBe($html)
        ->not->toContain('window.__attpWebsiteVisitTracker');

    $staff = auditorReadOnlyUser(auditorReadOnlyRole('Audit Support Officer'));
    $staffRequest = auditorReadOnlyRequest('GET', 'system/audit', 'system.audit.index', $staff);
    $staffResponse = (new InjectWebsiteVisitTracker)->handle(
        $staffRequest,
        static fn (): Response => new Response($html, 200, ['Content-Type' => 'text/html']),
    );

    expect($staffResponse->getContent())
        ->toContain('window.__attpWebsiteVisitTracker')
        ->toContain('const heartbeatUrl');

    $heartbeat = auditorReadOnlyRequest(
        'POST',
        'website-visit-tracker/heartbeat',
        'website-visit-tracker.heartbeat',
        $auditor,
    );
    $exception = auditorReadOnlyDeniedException($heartbeat);

    expect($exception)
        ->toBeInstanceOf(HttpException::class)
        ->and($exception?->getStatusCode())->toBe(403);
});

it('allows ordinary routed reads while denying every unsafe business method', function (string $method): void {
    bootAuditorReadOnlyApplication();
    $auditor = auditorReadOnlyUser();
    $request = auditorReadOnlyRequest($method, 'system/audit', 'system.audit.index', $auditor);
    $nextWasCalled = false;

    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        $response = (new EnforceAuditorReadOnly)->handle(
            $request,
            function () use (&$nextWasCalled): Response {
                $nextWasCalled = true;

                return new Response('', 204);
            },
        );

        expect($nextWasCalled)->toBeTrue()
            ->and($response->getStatusCode())->toBe(204)
            ->and(AuditorAccess::requestIsSafeReadAllowed($request))->toBeTrue();

        return;
    }

    $exception = auditorReadOnlyDeniedException($request);

    expect($exception)
        ->toBeInstanceOf(HttpException::class)
        ->and($exception?->getStatusCode())->toBe(403)
        ->and($exception?->getMessage())->toContain('read-only')
        ->and(AuditorAccess::requestIsSafeReadAllowed($request))->toBeFalse();
})->with([
    'GET is readable' => ['GET'],
    'HEAD is readable' => ['HEAD'],
    'OPTIONS remains available' => ['OPTIONS'],
    'POST is blocked' => ['POST'],
    'PUT is blocked' => ['PUT'],
    'PATCH is blocked' => ['PATCH'],
    'DELETE is blocked' => ['DELETE'],
]);

it('blocks legacy evaluation start GET routes because rendering them creates a draft', function (
    ?string $routeName,
    mixed $action,
): void {
    bootAuditorReadOnlyApplication();
    $request = auditorReadOnlyRequest(
        'GET',
        'my-evaluations/assignment/start/applicant',
        $routeName,
        auditorReadOnlyUser(),
        $action,
    );
    $exception = auditorReadOnlyDeniedException($request);

    expect($exception)
        ->toBeInstanceOf(HttpException::class)
        ->and($exception?->getStatusCode())->toBe(403)
        ->and(AuditorAccess::requestIsSafeReadAllowed($request))->toBeFalse();

    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/EvaluationSubmissionController.php'
    );
    expect($controller)
        ->toContain('public function start(')
        ->toContain('$this->evaluationSubmissionForUpdate(')
        ->toContain('EvaluationSubmission::create([');
})->with([
    'named personal evaluator route' => [
        'my.eval.start',
        static fn () => null,
    ],
    'named management evaluator route' => [
        'eval.assign.start',
        static fn () => null,
    ],
    'controller action fallback for a future alias' => [
        'future.evaluation.start.alias',
        [EvaluationSubmissionController::class, 'start'],
    ],
]);

it('keeps only exact authentication and session mutations available', function (
    string $method,
    string $uri,
    string $routeName,
): void {
    bootAuditorReadOnlyApplication();
    $request = auditorReadOnlyRequest($method, $uri, $routeName, auditorReadOnlyUser());
    $nextWasCalled = false;
    $response = (new EnforceAuditorReadOnly)->handle(
        $request,
        function () use (&$nextWasCalled): Response {
            $nextWasCalled = true;

            return new Response('', 204);
        },
    );

    expect($nextWasCalled)->toBeTrue()
        ->and($response->getStatusCode())->toBe(204)
        ->and(AuditorAccess::requestIsAllowed($request))->toBeTrue()
        ->and(AuditorAccess::requestIsSafeReadAllowed($request))->toBeFalse();
})->with([
    'logout' => ['POST', 'logout', 'logout'],
    'stop impersonation' => ['POST', 'impersonation/stop', 'impersonation.stop'],
    'send verification message' => ['POST', 'email/verification-notification', 'verification.send'],
    'update profile password' => ['PUT', 'password', 'password.update'],
    'legacy password update' => ['POST', 'change-password', 'password.change.update'],
    'required first-login password update' => ['POST', 'security/password/change', 'security.password.submit'],
    'verify login otp' => ['POST', 'security/otp/verify', 'security.otp.verify'],
    'resend login otp' => ['POST', 'security/otp/resend', 'security.otp.resend'],
    'think tank api logout' => ['POST', 'api/v1/think-tank/auth/logout', 'api.v1.think-tank.auth.logout'],
    'think tank api password update' => ['PUT', 'api/v1/think-tank/auth/password', 'api.v1.think-tank.auth.password.update'],
    'think tank api mfa verify' => ['POST', 'api/v1/think-tank/auth/mfa/verify', 'api.v1.think-tank.auth.mfa.verify'],
    'think tank api mfa resend' => ['POST', 'api/v1/think-tank/auth/mfa/resend', 'api.v1.think-tank.auth.mfa.resend'],
]);

it('does not allow unnamed or similarly named unsafe routes through the session allowlist', function (
    ?string $routeName,
): void {
    bootAuditorReadOnlyApplication();
    $request = auditorReadOnlyRequest('POST', 'system/mutate', $routeName, auditorReadOnlyUser());
    $exception = auditorReadOnlyDeniedException($request);

    expect($exception)
        ->toBeInstanceOf(HttpException::class)
        ->and($exception?->getStatusCode())->toBe(403);
})->with([
    'unnamed mutation' => [null],
    'logout prefix is not enough' => ['logout.everywhere'],
    'password prefix is not enough' => ['password.update.another-user'],
]);

it('cannot be upgraded by direct permissions, an admin user type, or a renamed role', function (): void {
    [$application, $bootedHere] = bootAuditorReadOnlyApplication();
    $role = auditorReadOnlyRole('Renamed Audit Observer', true);
    $role->setRelation('permissions', collect([
        new Permission(['name' => 'users.manage']),
    ]));
    $auditor = auditorReadOnlyUser($role, 'admin');
    $safeRequest = auditorReadOnlyRequest('GET', 'system/users', 'system.users.index', $auditor);
    $unsafeRequest = auditorReadOnlyRequest('POST', 'system/users', 'system.users.store', $auditor);

    try {
        expect($role->isReadOnlyAuditor())->toBeTrue()
            ->and($auditor->isAuditor())->toBeTrue()
            ->and($auditor->isAdmin())->toBeFalse()
            ->and($auditor->isSuperAdmin())->toBeFalse();

        withBoundAuditorRequest($application, $safeRequest, function () use ($auditor): void {
            expect($auditor->hasPermission('users.manage'))->toBeTrue()
                ->and($auditor->hasPermission('permission.not.explicitly.assigned'))->toBeTrue()
                ->and($auditor->hasSystemWideReadAccess())->toBeTrue()
                ->and(Gate::forUser($auditor)->allows('users.manage'))->toBeTrue();
        });

        withBoundAuditorRequest($application, $unsafeRequest, function () use ($auditor): void {
            expect($auditor->hasPermission('users.manage'))->toBeFalse()
                ->and($auditor->hasSystemWideReadAccess())->toBeFalse()
                ->and(Gate::forUser($auditor)->allows('users.manage'))->toBeFalse();
        });

        $unroutedConsoleStyleRequest = Request::create('/', 'GET');
        $unroutedConsoleStyleRequest->setUserResolver(static fn () => $auditor);
        withBoundAuditorRequest($application, $unroutedConsoleStyleRequest, function () use ($auditor): void {
            expect($auditor->hasPermission('users.manage'))->toBeFalse()
                ->and($auditor->hasSystemWideReadAccess())->toBeFalse();
        });
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('detects an Auditor sanctum identity before route-level authentication runs', function (): void {
    bootAuditorReadOnlyApplication();
    $auditor = auditorReadOnlyUser();
    $guard = Auth::guard('sanctum');
    $guard->setUser($auditor);
    $request = auditorReadOnlyRequest(
        'POST',
        'api/v1/think-tank/procurement/plans',
        'api.v1.think-tank.procurement.plans.store',
        null,
    );

    try {
        $exception = auditorReadOnlyDeniedException($request);

        expect($exception)
            ->toBeInstanceOf(HttpException::class)
            ->and($exception?->getStatusCode())->toBe(403);
    } finally {
        $guard->forgetUser();
    }
});

it('blocks an Auditor using a Sanctum bearer token outside the cookie-only Think Tank API', function (): void {
    bootAuditorReadOnlyApplication();
    $auditor = auditorReadOnlyUser();
    $guard = Auth::guard('sanctum');
    $guard->setUser($auditor);
    $request = auditorReadOnlyRequest(
        'POST',
        'api/v1/general/records',
        'api.v1.general.records.store',
        null,
    );
    $request->headers->set('Authorization', 'Bearer auditor-personal-access-token');

    try {
        $exception = auditorReadOnlyDeniedException($request);

        expect($exception)
            ->toBeInstanceOf(HttpException::class)
            ->and($exception?->getStatusCode())->toBe(403);
    } finally {
        $guard->forgetUser();
    }
});

it('leaves Think Tank bearer input for the cookie-only API boundary to reject canonically', function (): void {
    bootAuditorReadOnlyApplication();
    $auditor = auditorReadOnlyUser();
    $guard = Auth::guard('sanctum');
    $guard->setUser($auditor);
    $request = auditorReadOnlyRequest(
        'POST',
        'api/v1/think-tank/procurement/plans',
        'api.v1.think-tank.procurement.plans.store',
        null,
    );
    $request->headers->set('Authorization', 'Bearer must-not-be-accepted');
    $nextWasCalled = false;

    try {
        $response = (new EnforceAuditorReadOnly)->handle(
            $request,
            function () use (&$nextWasCalled): Response {
                $nextWasCalled = true;

                return new Response('', 204);
            },
        );

        expect($nextWasCalled)->toBeTrue()
            ->and($response->getStatusCode())->toBe(204);
    } finally {
        $guard->forgetUser();
    }
});

it('normalizes an admin-typed account and removes direct grants when assigning Auditor inline', function (): void {
    [$application, $bootedHere] = bootAuditorReadOnlyApplication();
    $startingTransactionLevel = DB::transactionLevel();
    $previousRequest = $application->make('request');
    DB::beginTransaction();

    try {
        $auditorRole = Role::query()->where('name', User::AUDITOR_ROLE)->first()
            ?? Role::query()->create([
                'name' => User::AUDITOR_ROLE,
                'description' => 'Transactional Auditor contract fixture.',
            ]);
        $permission = Permission::query()->first()
            ?? Permission::query()->create([
                'name' => 'auditor.contract.write',
                'module' => 'tests',
                'description' => 'Rolled-back Auditor contract fixture.',
            ]);
        $target = User::withoutEvents(fn () => User::query()->create([
            'name' => 'Transactional Auditor Target',
            'email' => 'auditor-contract-'.Str::uuid().'@example.test',
            'password' => Str::password(40),
            'user_type' => 'admin',
            'vendor_category' => 'stale-admin-category',
            'must_change_password' => false,
        ]));
        $target->permissions()->attach($permission->getKey());

        $management = Mockery::mock(ThinkTankUserManagementService::class);
        $management->shouldReceive('assertNotManagedPortalIdentity')
            ->once()
            ->with($target);
        $application->instance(ThinkTankUserManagementService::class, $management);

        $request = Request::create(
            '/system/users/'.$target->getKey().'/role',
            'POST',
            ['role_id' => $auditorRole->getKey()],
            server: ['HTTP_REFERER' => 'http://localhost/system/users'],
        );
        $application->instance('request', $request);

        $response = User::withoutEvents(
            fn () => (new UserAccessController)->updateRole($request, $target)
        );
        $target->refresh();

        expect($response->getStatusCode())->toBe(302)
            ->and($target->role?->isReadOnlyAuditor())->toBeTrue()
            ->and($target->user_type)->toBe('staff')
            ->and($target->member_state_id)->toBeNull()
            ->and($target->vendor_category)->toBeNull()
            ->and($target->permissions()->count())->toBe(0);
    } finally {
        $application->instance('request', $previousRequest);
        $application->forgetInstance(ThinkTankUserManagementService::class);
        Mockery::close();

        while (DB::transactionLevel() > $startingTransactionLevel) {
            DB::rollBack();
        }

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('protects the canonical role and rejects manual permission reassignment', function (): void {
    [$application, $bootedHere] = bootAuditorReadOnlyApplication();
    $previousRequest = $application->make('request');
    $role = auditorReadOnlyRole();

    try {
        $renameRequest = Mockery::mock(Request::class);
        $renameRequest->shouldReceive('validate')->once()->andReturn([
            'name' => 'Writable Auditor',
            'description' => null,
        ]);
        expect(fn () => (new RoleController)->update($renameRequest, $role))
            ->toThrow(ValidationException::class, 'cannot be renamed');

        $createRequest = Mockery::mock(Request::class);
        $createRequest->shouldReceive('validate')->once()->andReturn([
            'name' => 'aUdItOr',
            'description' => null,
        ]);
        expect(fn () => (new RoleController)->store($createRequest))
            ->toThrow(ValidationException::class, 'cannot be recreated');

        expect(fn () => (new PermissionController)->storeAssign(
            Request::create('/system/permissions/auditor/assign', 'POST'),
            $role,
        ))->toThrow(ValidationException::class, 'system-managed');

        $target = Mockery::mock(User::class)->makePartial();
        $target->forceFill(['user_type' => 'staff']);
        $target->setRelation('role', $role);
        $target->shouldReceive('loadMissing')->once()->with('role')->andReturnSelf();
        $directPermissions = Mockery::mock();
        $directPermissions->shouldReceive('detach')->once()->andReturn(1);
        $target->shouldReceive('permissions')->once()->andReturn($directPermissions);
        $management = Mockery::mock(ThinkTankUserManagementService::class);
        $management->shouldReceive('assertNotManagedPortalIdentity')->once()->with($target);
        $application->instance(ThinkTankUserManagementService::class, $management);

        $permissionRequest = Request::create(
            '/system/users/auditor/permissions',
            'POST',
            ['permissions' => ['users.manage']],
            server: ['HTTP_REFERER' => 'http://localhost/system/users/auditor/permissions'],
        );
        $application->instance('request', $permissionRequest);
        $response = (new UserAccessController)->syncPermissions($permissionRequest, $target);

        expect($response->getStatusCode())->toBe(302)
            ->and($response->getSession()?->get('error'))
            ->toContain('cannot be assigned');
    } finally {
        $application->instance('request', $previousRequest);
        $application->forgetInstance(ThinkTankUserManagementService::class);
        Mockery::close();

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('renders a persistent read-only notice and disables mutation controls in the back office shell', function (): void {
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/app.blade.php');

    expect($layout)
        ->toContain('auth()->user()?->isAuditor()')
        ->toContain("'auditor-read-only' => \$isReadOnlyAuditor")
        ->toContain('Auditor read-only session')
        ->toContain('Creating,')
        ->toContain('state-changing exports is disabled.')
        ->toContain('data-auditor-disabled')
        ->toContain("form.addEventListener('submit'")
        ->toContain('control.disabled = true')
        ->toContain("method === 'GET'")
        ->toContain("'/logout'")
        ->toContain("'/security/password/change'")
        ->toContain("link.setAttribute('aria-disabled', 'true')");
});
