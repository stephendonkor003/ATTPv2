<?php

use App\Http\Middleware\AuthenticateApiSync;
use App\Http\Middleware\AuthenticateApiSyncV2;
use App\Http\Middleware\AuthenticateDiscussionParticipant;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureAdministrativeAssistant;
use App\Http\Middleware\EnsureEmailIsVerifiedOrImpersonating;
use App\Http\Middleware\EnsureFundingPartner;
use App\Http\Middleware\EnsureMemberState;
use App\Http\Middleware\EnsureNotFundingPartner;
use App\Http\Middleware\EnsureOtpVerified;
use App\Http\Middleware\EnsurePasswordNotExpired;
use App\Http\Middleware\EnsureThinkTankAreaAccess;
use App\Http\Middleware\EnsureThinkTankUser;
use App\Http\Middleware\InjectWebsiteVisitTracker;
use App\Http\Middleware\RedirectAdministrativeAssistantToPortal;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ValidateUserImpersonation;
use App\Jobs\IndicatorReminderJob;
use App\Jobs\ProcessGrmEscalations;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withSchedule(function (Schedule $schedule): void {
        // Send indicator reminder emails every 4 hours via queued job.
        $schedule->job(new IndicatorReminderJob)->everyFourHours();

        // Generate deduplicated M&E deadline, workflow, corrective-action and
        // Means of Verification validation reminders each morning.
        $schedule->command('me:send-reporting-reminders')
            ->dailyAt('07:00')
            ->withoutOverlapping();

        // Process GRM reminders and escalations based on configured response clocks.
        $schedule->job(new ProcessGrmEscalations)->hourly();

        // Clear Laravel cache buildup 6 times per day.
        $schedule->command('optimize:clear')->everyFourHours()->withoutOverlapping();

        // Refresh World Bank catalog + recent values for used indicators each day.
        $schedule->command('worldbank:sync --catalog --used')->dailyAt('02:15')->withoutOverlapping();

        // Keep retrying website visits whose country was not resolved on first capture.
        $schedule->command('website-visits:resolve-locations --limit=200')->everyFifteenMinutes()->withoutOverlapping();

        // Revoke unused pairing codes and short-lived sync credentials promptly.
        $schedule->command('api-sync:expire --limit=500')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

        // Recover missed queue dispatches and promptly remove closed immutable snapshots.
        $schedule->command('api-sync:snapshots:maintain --limit=100')->everyMinute()->withoutOverlapping()->onOneServer();

        // Expire unapproved central invitations and retain replay nonces only
        // for their bounded security window.
        $schedule->command('api-sync:invitations:maintain --limit=500')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verified' => EnsureEmailIsVerifiedOrImpersonating::class,
            'permission' => CheckPermission::class,
            'api.sync' => AuthenticateApiSync::class,
            'api.sync.v2' => AuthenticateApiSyncV2::class,
            'discussion.participant' => AuthenticateDiscussionParticipant::class,
            'funding.partner' => EnsureFundingPartner::class,
            'not.funding.partner' => EnsureNotFundingPartner::class,
            'member.state' => EnsureMemberState::class,
            'think.tank' => EnsureThinkTankUser::class,
            'think.tank.area' => EnsureThinkTankAreaAccess::class,
            'administrative.assistant' => EnsureAdministrativeAssistant::class,
            'password.not.expired' => EnsurePasswordNotExpired::class,
            'otp.verified' => EnsureOtpVerified::class,
        ]);

        // Register SetLocale middleware to web group.
        $middleware->web(append: [
            SecurityHeaders::class,
            SetLocale::class,
            ValidateUserImpersonation::class,
            EnsurePasswordNotExpired::class,
            EnsureOtpVerified::class,
            RedirectAdministrativeAssistantToPortal::class,
            InjectWebsiteVisitTracker::class,
        ]);

        $middleware->api(append: [
            SecurityHeaders::class,
        ]);

        // Recovery must run after the session starts but before route-level
        // authentication, including when the impersonated user was deleted.
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            ValidateUserImpersonation::class
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();
