<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

it('provides a complete email verification recovery screen', function (): void {
    $root = dirname(__DIR__, 2);
    $viewPath = $root.'/resources/views/auth/verify-email.blade.php';

    expect(file_exists($viewPath))->toBeTrue();

    $view = file_get_contents($viewPath);

    expect($view)
        ->toContain("route('verification.send')")
        ->toContain("route('logout')")
        ->toContain("session('status') === 'verification-link-sent'")
        ->toContain('$errors->any()')
        ->toContain('Verify your email address')
        ->toContain('contact your ATTP administrator or support team');
});

it('prevents browsers and intermediaries from caching authentication responses', function (string $routeName): void {
    $request = Request::create('/authentication-check', 'GET');
    $route = (new Route(['GET'], '/authentication-check', static fn (): null => null))
        ->name($routeName);
    $request->setRouteResolver(static fn (): Route => $route);

    $response = (new SecurityHeaders)->handle(
        $request,
        static fn (): Response => new Response('OK'),
    );

    expect($response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('must-revalidate')
        ->and($response->headers->get('Pragma'))->toBe('no-cache')
        ->and($response->headers->get('Expires'))->toBe('0');
})->with([
    'login',
    'register',
    'password.request',
    'password.email',
    'password.reset',
    'password.store',
    'password.confirm',
    'password.update',
    'verification.notice',
    'verification.verify',
    'verification.send',
    'security.otp.show',
]);

it('handles verification delivery failures without exposing an exception page', function (): void {
    $controller = file_get_contents(
        dirname(__DIR__, 2).'/app/Http/Controllers/Auth/EmailVerificationNotificationController.php'
    );

    expect($controller)
        ->toContain('catch (Throwable $exception)')
        ->toContain('report($exception)')
        ->toContain("'verification' => 'We could not send a verification email right now.");
});
