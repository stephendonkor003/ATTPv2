<?php

use App\Http\Middleware\EnsureOtpVerified;
use App\Http\Middleware\EnsurePasswordNotExpired;
use App\Models\User;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;

it('refreshes the CSRF cookie while preserving pending password and MFA gates', function (
    bool $mustChange,
    bool $expired,
    string $expectedRedirect,
) {
    $bootedHere = ! Container::getInstance()->bound(Kernel::class);

    if ($bootedHere) {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $application->make(Kernel::class)->bootstrap();
    }

    $originalSessionDriver = config('session.driver');
    $originalSessionStore = app('session.store');
    $session = new Store('csrf-bootstrap-test', new ArraySessionHandler(120));
    $session->regenerateToken();
    config(['session.driver' => 'array']);
    app('session')->forgetDrivers();
    app()->instance('session.store', $session);
    app('redirect')->setSession($session);

    try {
        $user = (new User)->forceFill([
            'id' => '10000000-0000-4000-8000-000000000001',
            'user_type' => 'think_tank',
            'must_change_password' => $mustChange,
            'password_changed_at' => $expired ? now()->subDays(90) : now(),
        ]);
        $user->setRelation('role', null);
        $runSecurityGates = fn (Request $request, Closure $next) => (new EnsurePasswordNotExpired)->handle(
            $request,
            fn (Request $request) => (new EnsureOtpVerified)->handle($request, $next),
        );
        $makeRequest = function (string $path, Route $route) use ($session, $user): Request {
            $request = Request::create($path, 'GET');
            $request->setLaravelSession($session);
            $request->setUserResolver(fn () => $user);
            $request->setRouteResolver(fn () => $route);

            return $request;
        };
        $csrfRoute = app('router')->getRoutes()->getByName('sanctum.csrf-cookie');
        expect($csrfRoute)->not->toBeNull();
        $request = $makeRequest('/sanctum/csrf-cookie', $csrfRoute);
        $response = app(ValidateCsrfToken::class)->handle(
            $request,
            fn (Request $request) => $runSecurityGates(
                $request,
                fn (Request $request) => (new CsrfCookieController)->show($request),
            ),
        );

        expect($response->getStatusCode())->toBe(204)
            ->and($response->headers->has('Location'))->toBeFalse()
            ->and(collect($response->headers->getCookies())->map(fn ($cookie) => $cookie->getName())->all())->toContain('XSRF-TOKEN')
            ->and($session->get('otp_verified', false))->toBeFalse()
            ->and($user->must_change_password)->toBe($mustChange);

        // Only Sanctum's named bootstrap route is exempt. Protected screens and
        // unrelated routes under the same prefix still enforce the original gate.
        foreach (['think-tank.portal.dashboard', 'sanctum.other'] as $routeName) {
            $route = (new Route('GET', 'protected', fn () => null))->name($routeName);
            $protectedRequest = $makeRequest('/protected', $route);
            $blocked = $runSecurityGates($protectedRequest, fn () => new Response('must not be reached'));

            expect($blocked->getStatusCode())->toBe(302)
                ->and($blocked->headers->get('Location'))->toBe(route($expectedRedirect));
        }
    } finally {
        config(['session.driver' => $originalSessionDriver]);
        app('session')->forgetDrivers();
        app()->instance('session.store', $originalSessionStore);
        app('redirect')->setSession($originalSessionStore);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
})->with([
    'mandatory password change' => [true, false, 'security.password.change'],
    'expired password' => [false, true, 'security.password.change'],
    'pending MFA' => [false, false, 'security.otp.show'],
]);
