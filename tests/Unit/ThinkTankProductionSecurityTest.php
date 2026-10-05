<?php

use App\Services\ThinkTank\ThinkTankProductionSecurityService;
use App\Support\SanctumStatefulDomainList;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

function bootThinkTankProductionSecurityApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

it('normalizes the Sanctum stateful domain list used at runtime', function (): void {
    expect(SanctumStatefulDomainList::parse(
        ' Portal.Example.Test, portal.example.test:8443, ,PORTAL.EXAMPLE.TEST '
    ))->toBe([
        'portal.example.test',
        'portal.example.test:8443',
    ]);
});

it('reports every unsafe production portal boundary setting', function (): void {
    [, $bootedHere] = bootThinkTankProductionSecurityApplication();
    $keys = [
        'app.debug',
        'app.url',
        'think_tank_portal.frontend_url',
        'think_tank_portal.allowed_origins',
        'think_tank_portal.trusted_proxies',
        'think_tank_portal.require_mfa',
        'think_tank_portal.dummy_password_hash',
        'sanctum.stateful',
        'session.encrypt',
        'session.secure',
        'session.http_only',
        'session.same_site',
        'session.domain',
    ];
    $original = collect($keys)->mapWithKeys(
        static fn (string $key): array => [$key => config($key)]
    )->all();

    try {
        config([
            'app.debug' => false,
            'app.url' => 'https://api.example.test',
            'think_tank_portal.frontend_url' => 'https://portal.example.test',
            'think_tank_portal.allowed_origins' => ['https://portal.example.test'],
            'think_tank_portal.trusted_proxies' => ['192.0.2.10'],
            'think_tank_portal.require_mfa' => true,
            'think_tank_portal.dummy_password_hash' => Hash::make(bin2hex(random_bytes(24))),
            'sanctum.stateful' => ['portal.example.test', 'portal-dr.example.test:8443'],
            'session.encrypt' => true,
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'strict',
            'session.domain' => null,
        ]);

        $security = new ThinkTankProductionSecurityService;
        expect($security->problems())->toBe([]);

        config([
            'app.debug' => true,
            'app.url' => 'http://localhost',
            'think_tank_portal.frontend_url' => 'http://localhost:3000',
            'think_tank_portal.allowed_origins' => ['https://other.example.test'],
            'think_tank_portal.trusted_proxies' => ['*'],
            'think_tank_portal.require_mfa' => false,
            'think_tank_portal.dummy_password_hash' => 'not-a-password-hash',
            'sanctum.stateful' => ['other.example.test', '*.example.test'],
            'session.encrypt' => false,
            'session.secure' => false,
            'session.http_only' => false,
            'session.same_site' => 'lax',
            'session.domain' => '.example.test',
        ]);

        expect($security->problems())
            ->toContain('APP_DEBUG must be false.')
            ->toContain('APP_URL must be a non-local HTTPS URL.')
            ->toContain('THINK_TANK_PORTAL_URL must be a non-local HTTPS origin.')
            ->toContain('The portal URL must be included in the exact CORS origin list.')
            ->toContain('Every Sanctum stateful domain must be an exact host and optional port; wildcards are forbidden.')
            ->toContain('SANCTUM_STATEFUL_DOMAINS must include the exact portal host and port.')
            ->toContain('Sessions must be encrypted, Secure, HttpOnly, host-only, and SameSite=Strict.')
            ->toContain('Exact trusted proxy addresses or CIDRs are required; wildcards are forbidden.')
            ->toContain('MFA must be enabled.');
    } finally {
        config($original);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('keeps protected portal routes closed and marks transient security failures retryable', function (): void {
    [$application, $bootedHere] = bootThinkTankProductionSecurityApplication();
    $originalEnvironment = app()->environment();
    $originalSessionDriver = config('session.driver');
    $originalStateful = config('sanctum.stateful');

    try {
        app()->instance('env', 'production');
        config([
            'session.driver' => 'array',
            'sanctum.stateful' => ['portal.example.test'],
        ]);

        $request = Request::create('/api/v1/think-tank/auth/session', 'GET', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ORIGIN' => 'https://portal.example.test',
            'HTTP_REFERER' => 'https://portal.example.test/',
        ]);
        /** @var HttpKernel $kernel */
        $kernel = $application->make(HttpKernel::class);
        $response = $kernel->handle($request);
        $payload = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $kernel->terminate($request, $response);

        expect($response->getStatusCode())->toBe(503)
            ->and($payload['code'])->toBe('SECURE_SESSION_STORE_REQUIRED')
            ->and($response->headers->get('Retry-After'))->toBe('5')
            ->and($payload)->not->toHaveKey('exception');
    } finally {
        app()->instance('env', $originalEnvironment);
        config([
            'session.driver' => $originalSessionDriver,
            'sanctum.stateful' => $originalStateful,
        ]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('emits the configured SameSite policy instead of Sanctum forcing Lax', function (): void {
    [$application, $bootedHere] = bootThinkTankProductionSecurityApplication();
    $originalSessionDriver = config('session.driver');
    $originalSameSite = config('session.same_site');
    $originalSecure = config('session.secure');
    $originalStateful = config('sanctum.stateful');

    try {
        config([
            'session.driver' => 'array',
            'session.secure' => true,
            'sanctum.stateful' => ['portal.example.test'],
        ]);

        /** @var HttpKernel $kernel */
        $kernel = $application->make(HttpKernel::class);

        foreach (['strict', 'lax'] as $sameSite) {
            config(['session.same_site' => $sameSite]);
            $request = Request::create('/api/v1/think-tank/auth/session', 'GET', [], [], [], [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ORIGIN' => 'https://portal.example.test',
                'HTTP_REFERER' => 'https://portal.example.test/',
                'HTTP_HOST' => 'api.example.test',
                'HTTPS' => 'on',
            ]);
            $response = $kernel->handle($request);
            $sessionCookie = collect($response->headers->getCookies())
                ->first(fn ($cookie): bool => $cookie->getName() === config('session.cookie'));
            $kernel->terminate($request, $response);

            expect($response->getStatusCode())->toBe(200)
                ->and($sessionCookie)->not->toBeNull()
                ->and($sessionCookie->isHttpOnly())->toBeTrue()
                ->and($sessionCookie->getSameSite())->toBe($sameSite)
                ->and(config('session.same_site'))->toBe($sameSite);
        }
    } finally {
        config([
            'session.driver' => $originalSessionDriver,
            'session.same_site' => $originalSameSite,
            'session.secure' => $originalSecure,
            'sanctum.stateful' => $originalStateful,
        ]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});
