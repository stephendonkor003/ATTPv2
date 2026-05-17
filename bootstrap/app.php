<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureFundingPartner;
use App\Http\Middleware\EnsureMemberState;
use App\Http\Middleware\EnsureNotFundingPartner;
use App\Http\Middleware\EnsureOtpVerified;
use App\Http\Middleware\EnsurePasswordNotExpired;
use App\Http\Middleware\EnsureThinkTankUser;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Jobs\IndicatorReminderJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Send indicator reminder emails every 4 hours via queued job.
        $schedule->job(new IndicatorReminderJob())->everyFourHours();

        // Refresh World Bank catalog + recent values for used indicators each day.
        $schedule->command('worldbank:sync --catalog --used')->dailyAt('02:15')->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => CheckPermission::class,
            'funding.partner' => EnsureFundingPartner::class,
            'not.funding.partner' => EnsureNotFundingPartner::class,
            'member.state' => EnsureMemberState::class,
            'think.tank' => EnsureThinkTankUser::class,
            'password.not.expired' => EnsurePasswordNotExpired::class,
            'otp.verified' => EnsureOtpVerified::class,
        ]);

        // Register SetLocale middleware to web group.
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
