<?php

use App\Data\ThinkTank\CreateThinkTankUserData;
use App\Http\Middleware\NoStoreThinkTankApiResponses;
use App\Http\Resources\ThinkTankViewerResource;
use App\Models\Consortium;
use App\Models\ConsortiumThinkTank;
use App\Models\User;
use App\Notifications\ThinkTankPortalPasswordResetNotification;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTank\ThinkTankMailSecurityService;
use App\Services\ThinkTank\ThinkTankSessionService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Validation\ValidationException;

function bootThinkTankApiContractApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

it('registers the exact versioned authentication and user management contract', function () {
    [$application, $bootedHere] = bootThinkTankApiContractApplication();

    try {
        /** @var Router $router */
        $router = $application->make(Router::class);
        $routes = collect($router->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->uri(), 'api/v1/think-tank'))
            ->flatMap(fn ($route) => collect($route->methods())
                ->reject(fn (string $method): bool => $method === 'HEAD')
                ->map(fn (string $method): string => $method.' '.$route->uri()))
            ->sort()
            ->values()
            ->all();

        expect($routes)->toBe(collect([
            'GET api/v1/think-tank/auth/session',
            'POST api/v1/think-tank/auth/login',
            'POST api/v1/think-tank/auth/logout',
            'PUT api/v1/think-tank/auth/password',
            'POST api/v1/think-tank/auth/password/forgot',
            'POST api/v1/think-tank/auth/password/reset',
            'POST api/v1/think-tank/auth/mfa/resend',
            'POST api/v1/think-tank/auth/mfa/verify',
            'GET api/v1/think-tank/me',
            'GET api/v1/think-tank/access-levels',
            'GET api/v1/think-tank/users',
            'POST api/v1/think-tank/users',
            'GET api/v1/think-tank/users/{user}',
            'PATCH api/v1/think-tank/users/{user}',
            'POST api/v1/think-tank/users/{user}/invitation',
        ])->sort()->values()->all());
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('sets private no-store headers on successful portal responses', function () {
    $request = Request::create('/api/v1/think-tank/auth/session', 'GET');
    $response = (new NoStoreThinkTankApiResponses)->handle(
        $request,
        fn (): Response => new Response('ok')
    );

    expect($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
        ->and($response->headers->get('Pragma'))->toBe('no-cache');
});

it('returns a stable unauthenticated error and no-store headers without a database', function () {
    [$application, $bootedHere] = bootThinkTankApiContractApplication();
    $originalSession = config('session.driver');
    $originalCache = config('cache.default');
    config(['session.driver' => 'array', 'cache.default' => 'array']);

    try {
        $request = Request::create('/api/v1/think-tank/users', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_REFERER' => 'http://localhost:3000/',
        ]);
        /** @var HttpKernel $kernel */
        $kernel = $application->make(HttpKernel::class);
        $response = $kernel->handle($request);
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $kernel->terminate($request, $response);

        $sessionRequest = Request::create('/api/v1/think-tank/auth/session', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_REFERER' => 'http://localhost:3000/',
        ]);
        $sessionResponse = $kernel->handle($sessionRequest);
        $sessionPayload = json_decode((string) $sessionResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $kernel->terminate($sessionRequest, $sessionResponse);

        $untrustedRequest = Request::create('/api/v1/think-tank/auth/session', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $untrustedResponse = $kernel->handle($untrustedRequest);
        $untrustedPayload = json_decode((string) $untrustedResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $kernel->terminate($untrustedRequest, $untrustedResponse);

        $bearerRequest = Request::create('/api/v1/think-tank/auth/session', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer must-not-be-accepted',
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_REFERER' => 'http://localhost:3000/',
        ]);
        $bearerResponse = $kernel->handle($bearerRequest);
        $bearerPayload = json_decode((string) $bearerResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $kernel->terminate($bearerRequest, $bearerResponse);

        expect($response->getStatusCode())->toBe(401)
            ->and($payload['code'])->toBe('UNAUTHENTICATED')
            ->and($payload['data']['state'])->toBe('UNAUTHENTICATED')
            ->and($response->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
            ->and($sessionResponse->getStatusCode())->toBe(200)
            ->and($sessionPayload['data'])->toMatchArray([
                'state' => 'UNAUTHENTICATED',
                'next_action' => 'LOGIN',
                'user' => null,
                'challenge' => null,
            ])
            ->and($sessionResponse->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
            ->and($untrustedResponse->getStatusCode())->toBe(400)
            ->and($untrustedPayload['code'])->toBe('STATEFUL_SESSION_REQUIRED')
            ->and($untrustedResponse->headers->get('Cache-Control'))->toContain('private')->toContain('no-store')
            ->and($bearerResponse->getStatusCode())->toBe(400)
            ->and($bearerPayload['code'])->toBe('AUTHORIZATION_HEADER_NOT_ALLOWED')
            ->and($bearerResponse->headers->get('Cache-Control'))->toContain('private')->toContain('no-store');
    } finally {
        config(['session.driver' => $originalSession, 'cache.default' => $originalCache]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('builds the Next reset route with the token in the path and only email in the query', function () {
    [, $bootedHere] = bootThinkTankApiContractApplication();
    $originalUrl = config('think_tank_portal.frontend_url');
    config(['think_tank_portal.frontend_url' => 'https://portal.example.test']);

    try {
        $user = new User;
        $user->forceFill(['email' => 'person+portal@example.test']);
        $notification = new ThinkTankPortalPasswordResetNotification('token/with spaces', true);
        $method = new ReflectionMethod($notification, 'resetUrl');
        $url = $method->invoke($notification, $user);

        expect($url)->toBe(
            'https://portal.example.test/reset-password/token%2Fwith%20spaces?email=person%2Bportal%40example.test'
        );
    } finally {
        config(['think_tank_portal.frontend_url' => $originalUrl]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('normalizes user DTO email and viewer resources whitelist capabilities without secrets', function () {
    $data = CreateThinkTankUserData::from([
        'name' => '  Portal Admin  ',
        'email' => '  ADMIN@Example.COM ',
        'access_level' => User::THINK_TANK_ACCESS_ADMIN,
    ]);

    $consortium = (new Consortium)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000001',
        'name' => 'Consortium',
    ]);
    $membership = (new ConsortiumThinkTank)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000002',
        'name' => 'Think Tank',
        'status' => 'active',
    ]);
    $membership->setRelation('consortium', $consortium);
    $user = (new User)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000003',
        'name' => 'Portal Admin',
        'email' => 'admin@example.com',
        'password' => 'must-never-serialize',
        'payment_account_number' => 'must-never-serialize',
        'user_type' => 'think_tank',
        'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
    ]);
    $request = Request::create('/api/v1/think-tank/me');
    $request->attributes->set('think_tank.membership', $membership);
    $resource = (new ThinkTankViewerResource($user))->resolve($request);

    expect($data->name)->toBe('Portal Admin')
        ->and($data->email)->toBe('admin@example.com')
        ->and(User::THINK_TANK_ACCESS_LEVELS)->toHaveKey(User::THINK_TANK_ACCESS_FINANCE, 'Finance Officer')
        ->and($resource)->not->toHaveKeys(['password', 'remember_token', 'payment_account_number'])
        ->and($resource['access']['permissions'])->toContain('think_tank.portal.access')
        ->toContain('think_tank.team.manage')
        ->toContain('think_tank.users.manage');
});

it('keeps reset consumption and normalized email mutations serialized in source', function () {
    $root = dirname(__DIR__, 2);
    $passwordController = file_get_contents($root.'/app/Http/Controllers/Api/V1/ThinkTank/PasswordController.php');
    $users = file_get_contents($root.'/app/Services/ThinkTank/ThinkTankUserManagementService.php');
    $statefulMiddleware = file_get_contents($root.'/app/Http/Middleware/EnsureThinkTankApiStatefulSession.php');

    expect($passwordController)
        ->toContain('->lockForUpdate()->firstOrFail()')
        ->toContain("if (! \$broker->tokenExists(\$lockedUser, \$data['token']))")
        ->toContain('$broker->deleteToken($lockedUser)')
        ->and($users)
        ->toContain('Cache::store($store)->lock(')
        ->toContain('$this->assertEmailAvailable($data->email)')
        ->toContain("whereRaw('LOWER(email) = ?'")
        ->and($statefulMiddleware)
        ->toContain("request->header('Authorization'")
        ->toContain('AUTHORIZATION_HEADER_NOT_ALLOWED')
        ->and(class_uses_recursive(User::class))->not->toContain(Laravel\Sanctum\HasApiTokens::class);
});

it('binds sessions to mutable account security state without a database', function () {
    [, $bootedHere] = bootThinkTankApiContractApplication();
    $request = Request::create('/api/v1/think-tank/auth/session');
    $request->setLaravelSession(new Store('think-tank-test', new ArraySessionHandler(120)));
    $user = (new User)->forceFill([
        'id' => '10000000-0000-4000-8000-000000000004',
        'email' => 'security@example.test',
        'password' => 'password-hash-a',
        'remember_token' => 'remember-a',
        'user_type' => 'think_tank',
        'think_tank_member_id' => '10000000-0000-4000-8000-000000000002',
        'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
        'is_disabled' => false,
        'is_blacklisted' => false,
    ]);
    $sessions = new ThinkTankSessionService;

    try {
        expect($sessions->hasValidCurrentSession($user, $request))->toBeFalse();
        $sessions->bindCurrentSession($user, $request);
        expect($sessions->hasValidCurrentSession($user, $request))->toBeTrue();

        $user->forceFill(['think_tank_access_level' => User::THINK_TANK_ACCESS_FINANCE]);
        expect($sessions->hasValidCurrentSession($user, $request))->toBeFalse();
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('keeps mutation secrets out of query contracts and audit URLs', function () {
    $controller = new class extends \App\Http\Controllers\Api\V1\ThinkTank\ThinkTankApiController
    {
        public function validateRequest(Request $request, array $rules = []): array
        {
            return $this->validateOnly($request, $rules);
        }
    };
    $queryMutation = Request::create(
        '/api/v1/think-tank/auth/password/reset?token=must-not-log',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        '{}',
    );
    $jsonMutation = Request::create(
        '/api/v1/think-tank/auth/logout',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        '{}',
    );
    $auditRequest = Request::create(
        '/api/v1/think-tank/auth/password/reset?token=secret&password=secret',
        'POST',
    );
    $audit = new ThinkTankApiAuditService;
    $attributes = new ReflectionMethod($audit, 'attributes');

    expect(fn () => $controller->validateRequest($queryMutation))
        ->toThrow(ValidationException::class)
        ->and($controller->validateRequest($jsonMutation))->toBe([])
        ->and($attributes->invoke($audit, $auditRequest, 'test', 'test', [], null)['url'])
        ->toBe('/api/v1/think-tank/auth/password/reset');
});

it('fails closed on debug credential mail transports in production', function () {
    [, $bootedHere] = bootThinkTankApiContractApplication();
    $originalEnvironment = app()->environment();
    $originalMailer = config('mail.default');
    $originalQueue = config('queue.default');
    $mailSecurity = new ThinkTankMailSecurityService;

    try {
        app()->instance('env', 'production');
        config(['mail.default' => 'log']);

        expect(fn () => $mailSecurity->assertCredentialDeliveryIsSecure())
            ->toThrow(\App\Exceptions\ThinkTankApiException::class);

        config(['mail.default' => 'smtp']);
        expect(fn () => $mailSecurity->assertCredentialDeliveryIsSecure())->not->toThrow(Throwable::class);

        config(['queue.default' => 'sync']);
        expect(fn () => $mailSecurity->assertEncryptedResetQueueIsDurable())
            ->toThrow(\App\Exceptions\ThinkTankApiException::class);

        config(['queue.default' => 'database']);
        expect(fn () => $mailSecurity->assertEncryptedResetQueueIsDurable())->not->toThrow(Throwable::class);
    } finally {
        app()->instance('env', $originalEnvironment);
        config(['mail.default' => $originalMailer, 'queue.default' => $originalQueue]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('requires shared production stores for revocation and rate limits', function () {
    [, $bootedHere] = bootThinkTankApiContractApplication();
    $originalEnvironment = app()->environment();
    $originalDriver = config('session.driver');
    $originalCache = config('cache.default');
    $sessions = new ThinkTankSessionService;

    try {
        app()->instance('env', 'production');
        config(['session.driver' => 'redis']);
        expect(fn () => $sessions->assertProductionSecurityStores())
            ->toThrow(\App\Exceptions\ThinkTankApiException::class);

        config(['session.driver' => 'database', 'cache.default' => 'file']);
        expect(fn () => $sessions->assertProductionSecurityStores())
            ->toThrow(\App\Exceptions\ThinkTankApiException::class);

        config(['cache.default' => 'database']);
        expect(fn () => $sessions->assertProductionSecurityStores())->not->toThrow(Throwable::class);
    } finally {
        app()->instance('env', $originalEnvironment);
        config(['session.driver' => $originalDriver, 'cache.default' => $originalCache]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});
