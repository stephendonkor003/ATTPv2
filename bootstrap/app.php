<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\EnsureFundingPartner;
use App\Http\Middleware\EnsureNotFundingPartner;
use App\Http\Middleware\EnsureMemberState;
use App\Http\Middleware\EnsurePasswordNotExpired;
use App\Http\Middleware\EnsureOtpVerified;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )

    // ✅ REGISTER MIDDLEWARE ALIASES HERE (LARAVEL 11)
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'permission' => CheckPermission::class,
            'funding.partner' => EnsureFundingPartner::class,
            'not.funding.partner' => EnsureNotFundingPartner::class,
            'member.state' => EnsureMemberState::class,
            'password.not.expired' => EnsurePasswordNotExpired::class,
            'otp.verified' => EnsureOtpVerified::class,
        ]);

        // Register SetLocale middleware to web group
        $middleware->web(append: [
            SecurityHeaders::class,
            SetLocale::class,
            EnsurePasswordNotExpired::class,
            EnsureOtpVerified::class,
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
        ]);

    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
