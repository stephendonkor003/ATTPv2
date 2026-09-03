<?php

use App\Exceptions\ThinkTankApiException;
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
use App\Http\Middleware\EnsureThinkTankApiAccount;
use App\Http\Middleware\EnsureThinkTankApiReady;
use App\Http\Middleware\EnsureThinkTankApiStatefulSession;
use App\Http\Middleware\EnsureThinkTankApiUserManager;
use App\Http\Middleware\EnsureThinkTankAreaAccess;
use App\Http\Middleware\EnsureThinkTankUser;
use App\Http\Middleware\InjectWebsiteVisitTracker;
use App\Http\Middleware\NoStoreThinkTankApiResponses;
use App\Http\Middleware\RedirectAdministrativeAssistantToPortal;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ValidateUserImpersonation;
use App\Jobs\IndicatorReminderJob;
use App\Jobs\ProcessGrmEscalations;
use App\Support\ThinkTankApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

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

        // OTP rows retain IP, user-agent and session metadata only briefly.
        $schedule->command('think-tank:otp:purge --hours=24 --limit=5000')
            ->hourly()
            ->withoutOverlapping()
            ->onOneServer();

        // Recipient rows are a durable outbox for proposal invitations. This
        // completes any after-response delivery interrupted by PHP/FPM.
        $schedule->command('eoi:communications:deliver --limit=25')->everyMinute()->withoutOverlapping()->onOneServer();

        // Catch submissions whose initial queue publication was interrupted
        // and recover 3PAP workers that stopped before completing their run.
        $threepapRecoveryLimit = max(1, min(
            (int) config('services.threepap_checker.automatic.recovery_limit', 25),
            500,
        ));
        $schedule->command('threepap:screen-pending --limit='.$threepapRecoveryLimit)
            ->everyMinute()
            ->withoutOverlapping(5)
            ->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // Proxy addresses come from config/trustedproxy.php so config caching
        // remains supported. Only the headers needed for client IP and HTTPS
        // are trusted; forwarded host/prefix values are deliberately ignored.
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PORT,
        );

        $middleware->statefulApi();

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
            'think.tank.api.no-store' => NoStoreThinkTankApiResponses::class,
            'think.tank.api.account' => EnsureThinkTankApiAccount::class,
            'think.tank.api.ready' => EnsureThinkTankApiReady::class,
            'think.tank.api.stateful' => EnsureThinkTankApiStatefulSession::class,
            'think.tank.api.users.manage' => EnsureThinkTankApiUserManager::class,
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
        $isThinkTankApi = static fn (Request $request): bool => $request->is(
            'api/v1/think-tank',
            'api/v1/think-tank/*'
        );

        $exceptions->shouldRenderJsonWhen(
            static fn (Request $request, Throwable $exception): bool => $isThinkTankApi($request)
                || $request->expectsJson()
        );

        $exceptions->render(function (ThinkTankApiException $exception, Request $request) use ($isThinkTankApi) {
            if (! $isThinkTankApi($request)) {
                return null;
            }

            $response = ThinkTankApiResponse::error(
                $exception->errorCode,
                $exception->getMessage(),
                $exception->status,
                $exception->data === [] ? [] : ['data' => $exception->data],
            );

            if ($exception->status === 429 && isset($exception->data['retry_after'])) {
                $response->headers->set('Retry-After', (string) max(0, (int) $exception->data['retry_after']));
            }

            return $response;
        });

        $exceptions->render(function (ValidationException $exception, Request $request) use ($isThinkTankApi) {
            if (! $isThinkTankApi($request)) {
                return null;
            }

            return ThinkTankApiResponse::error(
                'VALIDATION_FAILED',
                'The given data was invalid.',
                422,
                ['errors' => $exception->errors()],
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) use ($isThinkTankApi) {
            if (! $isThinkTankApi($request)) {
                return null;
            }

            return ThinkTankApiResponse::error(
                'UNAUTHENTICATED',
                'Authentication is required.',
                401,
                ['data' => ['state' => 'UNAUTHENTICATED', 'next_action' => 'LOGIN']],
            );
        });

        $exceptions->render(function (TokenMismatchException $exception, Request $request) use ($isThinkTankApi) {
            if (! $isThinkTankApi($request)) {
                return null;
            }

            return ThinkTankApiResponse::error(
                'CSRF_MISMATCH',
                'The security token has expired. Refresh it and try again.',
                419,
            );
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) use ($isThinkTankApi) {
            if (! $isThinkTankApi($request)) {
                return null;
            }

            return ThinkTankApiResponse::error('FORBIDDEN', 'This action is not allowed.', 403);
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request) use ($isThinkTankApi) {
            if (! $isThinkTankApi($request)) {
                return null;
            }

            $headers = $exception->getHeaders();
            $retryAfter = isset($headers['Retry-After']) ? max(0, (int) $headers['Retry-After']) : null;
            $response = ThinkTankApiResponse::error(
                'RATE_LIMITED',
                'Too many requests. Please try again later.',
                429,
                $retryAfter === null ? [] : ['data' => ['retry_after' => $retryAfter]],
            );
            $response->headers->add($headers);

            return $response;
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $exception, Request $request) use ($isThinkTankApi) {
            if (! $isThinkTankApi($request)) {
                return null;
            }

            return ThinkTankApiResponse::error('NOT_FOUND', 'The requested resource was not found.', 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) use ($isThinkTankApi) {
            if (! $isThinkTankApi($request)) {
                return null;
            }

            return ThinkTankApiResponse::error('METHOD_NOT_ALLOWED', 'This HTTP method is not allowed.', 405);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) use ($isThinkTankApi) {
            if (! $isThinkTankApi($request)) {
                return null;
            }

            $status = $exception->getStatusCode();
            $code = match ($status) {
                400 => 'BAD_REQUEST',
                401 => 'UNAUTHENTICATED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED',
                409 => 'CONFLICT',
                419 => 'CSRF_MISMATCH',
                429 => 'RATE_LIMITED',
                default => $status >= 500 ? 'SERVICE_UNAVAILABLE' : 'REQUEST_FAILED',
            };

            return ThinkTankApiResponse::error($code, 'The request could not be completed.', $status);
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($isThinkTankApi) {
            if (! $isThinkTankApi($request)) {
                return null;
            }

            return ThinkTankApiResponse::error(
                'INTERNAL_ERROR',
                'The request could not be completed.',
                500,
            );
        });

        $exceptions->respond(function (Response $response): Response {
            if (ThinkTankApiResponse::isPortalRequest()) {
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->set('Pragma', 'no-cache');
            }

            return $response;
        });
    })
    ->create();
