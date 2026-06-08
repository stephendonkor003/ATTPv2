<?php

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$request = LoginRequest::create('/login', 'POST', [
    'email' => 'legacy-user@example.test',
    'password' => 'Password123!',
]);
$request->setContainer($app);
$request->setRedirector($app['redirect']);

Auth::shouldReceive('attempt')
    ->once()
    ->with([
        'email' => 'legacy-user@example.test',
        'password' => 'Password123!',
    ], false)
    ->andThrow(new RuntimeException('This password does not use the Bcrypt algorithm.'));

RateLimiter::shouldReceive('tooManyAttempts')
    ->once()
    ->with($request->throttleKey(), 5)
    ->andReturn(false);

RateLimiter::shouldReceive('hit')
    ->once()
    ->with($request->throttleKey());

Log::shouldReceive('warning')
    ->once()
    ->with(
        'Login rejected because the stored password hash is incompatible with bcrypt.',
        Mockery::on(fn (array $context): bool => ($context['email'] ?? null) === 'legacy-user@example.test')
    );

$passed = false;

try {
    $request->authenticate();
} catch (ValidationException $exception) {
    $passed = isset($exception->errors()['email']);
} finally {
    Mockery::close();
}

if (! $passed) {
    fwrite(STDERR, "Expected bcrypt mismatch to become a login validation failure.\n");
    exit(1);
}

echo "LOGIN_INCOMPATIBLE_PASSWORD_HASH_OK\n";
