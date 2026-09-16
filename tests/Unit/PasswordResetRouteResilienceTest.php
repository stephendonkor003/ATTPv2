<?php

use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Middleware\EnsureEmailIsVerifiedOrImpersonating;
use App\Models\User;
use App\Notifications\ThinkTankPortalPasswordResetNotification;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Router;

function bootPasswordResetRouteApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

it('keeps canonical and previously issued password reset URLs routable', function (): void {
    [$application, $bootedHere] = bootPasswordResetRouteApplication();

    try {
        /** @var Router $router */
        $router = $application->make(Router::class);

        $canonical = $router->getRoutes()->match(Request::create('/reset-password/test-token', 'GET'));
        $legacy = $router->getRoutes()->match(Request::create('/password/reset/test-token?email=person%40example.test', 'GET'));
        $legacyRequest = $router->getRoutes()->match(Request::create('/password/reset', 'GET'));
        $legacyPost = $router->getRoutes()->match(Request::create('/password/reset', 'POST'));

        expect($canonical->getName())->toBe('password.reset')
            ->and($legacy->getName())->toBe('password.reset.legacy')
            ->and($legacyRequest->getName())->toBe('password.request.legacy')
            ->and($legacyPost->getName())->toBe('password.store.legacy');
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('keeps the APP URL fallback for Think Tank reset mail on a shipped route', function (): void {
    [$application, $bootedHere] = bootPasswordResetRouteApplication();
    $originalFrontend = config('think_tank_portal.frontend_url');
    config(['think_tank_portal.frontend_url' => rtrim((string) config('app.url'), '/')]);

    try {
        $user = (new User)->forceFill(['email' => 'portal-owner@example.test']);
        $notification = new ThinkTankPortalPasswordResetNotification('safe-reset-token');
        $method = new ReflectionMethod($notification, 'resetUrl');
        $url = $method->invoke($notification, $user);
        $path = (string) parse_url($url, PHP_URL_PATH);
        $query = (string) parse_url($url, PHP_URL_QUERY);

        /** @var Router $router */
        $router = $application->make(Router::class);
        $route = $router->getRoutes()->match(Request::create($path.'?'.$query, 'GET'));

        expect($route->getName())->toBe('password.reset')
            ->and($path)->toBe('/reset-password/safe-reset-token');
    } finally {
        config(['think_tank_portal.frontend_url' => $originalFrontend]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('renders the canonical reset page with private recovery controls', function (): void {
    [$application, $bootedHere] = bootPasswordResetRouteApplication();
    $originalSession = config('session.driver');
    $originalCache = config('cache.default');
    config(['session.driver' => 'array', 'cache.default' => 'array']);

    try {
        $request = Request::create(
            '/reset-password/safe-reset-token?email=person%40example.test',
            'GET',
        );
        /** @var HttpKernel $kernel */
        $kernel = $application->make(HttpKernel::class);
        $response = $kernel->handle($request);
        $content = (string) $response->getContent();
        $kernel->terminate($request, $response);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($content)->toContain('Set new password')
            ->toContain('name="token" value="safe-reset-token"')
            ->toContain('value="person@example.test"')
            ->toContain('<meta name="referrer" content="no-referrer">');
    } finally {
        config(['session.driver' => $originalSession, 'cache.default' => $originalCache]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('redirects old reset links to the canonical page without retaining unrelated query data', function (): void {
    [$application, $bootedHere] = bootPasswordResetRouteApplication();
    $originalSession = config('session.driver');
    $originalCache = config('cache.default');
    config(['session.driver' => 'array', 'cache.default' => 'array']);

    try {
        $request = Request::create(
            '/password/reset/old-reset-token?email=person%40example.test&utm_source=mail',
            'GET',
        );
        /** @var HttpKernel $kernel */
        $kernel = $application->make(HttpKernel::class);
        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);

        $location = (string) $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        expect($response->getStatusCode())->toBe(302)
            ->and(parse_url($location, PHP_URL_PATH))->toBe('/reset-password/old-reset-token')
            ->and($query)->toBe(['email' => 'person@example.test']);
    } finally {
        config(['session.driver' => $originalSession, 'cache.default' => $originalCache]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('treats successful standard password reset token use as email ownership proof', function (): void {
    [, $bootedHere] = bootPasswordResetRouteApplication();

    try {
        $user = new class extends User
        {
            public function save(array $options = []): bool
            {
                $this->syncChanges();

                return true;
            }
        };
        $user->forceFill([
            'email' => 'reset-owner@example.test',
            'email_verified_at' => null,
            'password' => 'Old password 123!',
        ]);

        $method = new ReflectionMethod(NewPasswordController::class, 'completeStandardPasswordReset');
        $method->invoke(new NewPasswordController, $user, 'Replacement password 456!');

        expect($user->hasVerifiedEmail())->toBeTrue();

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(static fn (): User => $user);
        $response = (new EnsureEmailIsVerifiedOrImpersonating)->handle(
            $request,
            static fn (): Response => new Response('verified-user-continued'),
            'verification.notice',
        );

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getContent())->toBe('verified-user-continued');
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('preserves an existing email verification timestamp during password reset', function (): void {
    [, $bootedHere] = bootPasswordResetRouteApplication();

    try {
        $verifiedAt = now()->subYear()->startOfSecond();
        $user = new class extends User
        {
            public function save(array $options = []): bool
            {
                $this->syncChanges();

                return true;
            }
        };
        $user->forceFill([
            'email' => 'already-verified@example.test',
            'email_verified_at' => $verifiedAt,
            'password' => 'Old password 123!',
        ]);

        $method = new ReflectionMethod(NewPasswordController::class, 'completeStandardPasswordReset');
        $method->invoke(new NewPasswordController, $user, 'Replacement password 456!');

        expect($user->email_verified_at?->equalTo($verifiedAt))->toBeTrue();
    } finally {
        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});
