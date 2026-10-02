<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Http\Resources\ThinkTankViewerResource;
use App\Models\User;
use App\Services\PendingLoginService;
use App\Services\ThinkTank\ThinkTankAccountAccessService;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTank\ThinkTankAuthenticationStateService;
use App\Services\ThinkTank\ThinkTankMfaService;
use App\Services\ThinkTank\ThinkTankSessionService;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class MfaController extends ThinkTankApiController
{
    public function __construct(
        private readonly ThinkTankAuthenticationStateService $states,
        private readonly ThinkTankMfaService $mfa,
        private readonly ThinkTankApiAuditService $audit,
        private readonly ThinkTankAccountAccessService $accounts,
        private readonly ThinkTankSessionService $sessions,
        private readonly PendingLoginService $pendingLogins,
    ) {}

    public function resend(Request $request): JsonResponse
    {
        $this->validateOnly($request, []);
        [$user, $pending] = $this->resolveUser($request);
        $this->assertMfaRequired($request, $user, $pending);
        $challenge = $this->mfa->send($request, $user);

        if ($pending) {
            $this->pendingLogins->refreshExpiration(
                $request,
                $challenge['expires_at'],
                PendingLoginService::PURPOSE_THINK_TANK_API,
                $challenge,
            );
        }

        $this->audit->bestEffort($request, 'think_tank.mfa.resent', 'Think tank portal verification code resent.', [
            'target_user_id' => (string) $user->getKey(),
        ], $user);

        return ThinkTankApiResponse::success([
            ...$this->states->summary(ThinkTankAuthenticationStateService::MFA_REQUIRED),
            'user' => null,
            'challenge' => $challenge,
        ], 200, 'A new verification code was sent.');
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $this->validateOnly($request, [
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);
        [$user, $pending] = $this->resolveUser($request);
        $this->assertMfaRequired($request, $user, $pending);
        $account = hash('sha256', (string) $user->getKey());
        $sessionKey = 'think-tank-mfa-session:'.$account.'|'.hash('sha256', $request->session()->getId());
        $accountKey = 'think-tank-mfa-account:'.$account;
        $sessionMaximum = (int) config('think_tank_portal.mfa_verify_max_attempts', 5);
        $accountMaximum = (int) config('think_tank_portal.mfa_verify_account_max_attempts', 10);

        if (RateLimiter::tooManyAttempts($sessionKey, $sessionMaximum)
            || RateLimiter::tooManyAttempts($accountKey, $accountMaximum)) {
            throw new ThinkTankApiException(
                'RATE_LIMITED',
                'Too many verification attempts. Please request a new code later.',
                429,
                [
                    ...$this->states->summary(ThinkTankAuthenticationStateService::MFA_REQUIRED),
                    'retry_after' => max(
                        RateLimiter::availableIn($sessionKey),
                        RateLimiter::availableIn($accountKey),
                    ),
                ],
            );
        }

        if (! $this->mfa->verify($request, $user, $data['code'])) {
            $decay = (int) config('think_tank_portal.mfa_verify_decay_seconds', 600);
            RateLimiter::hit($sessionKey, $decay);
            RateLimiter::hit($accountKey, $decay);
            $this->audit->bestEffort($request, 'think_tank.mfa.failed', 'Think tank portal verification failed.', [
                'target_user_id' => (string) $user->getKey(),
            ], $user);

            throw new ThinkTankApiException(
                'MFA_CODE_INVALID',
                'The verification code is invalid or has expired.',
                422,
                $this->states->summary(ThinkTankAuthenticationStateService::MFA_REQUIRED),
            );
        }

        RateLimiter::clear($sessionKey);
        RateLimiter::clear($accountKey);
        RateLimiter::clear('think-tank-login-account:'.hash('sha256', mb_strtolower((string) $user->email)));

        if ($pending) {
            $freshUser = $this->pendingLogins->user(
                $request,
                PendingLoginService::PURPOSE_THINK_TANK_API,
            );

            if (! $freshUser) {
                throw $this->unauthenticated();
            }

            try {
                $membership = $this->accounts->membership($freshUser);
            } catch (ThinkTankApiException) {
                $this->pendingLogins->clear($request, PendingLoginService::PURPOSE_THINK_TANK_API);

                throw $this->unauthenticated();
            }

            $user = $freshUser;
            $request->attributes->set('think_tank.membership', $membership);
        }

        try {
            $user->markOtpAsVerified();

            if ($pending) {
                $remember = $this->pendingLogins->remember(
                    $request,
                    PendingLoginService::PURPOSE_THINK_TANK_API,
                );
                Auth::guard('web')->login($user, $remember);
                $this->pendingLogins->clear($request, PendingLoginService::PURPOSE_THINK_TANK_API);
                $request->setUserResolver(fn (): ?User => Auth::guard('web')->user());
            }

            $request->session()->regenerate();
            $this->states->markMfaVerified($request, $user);
            $this->sessions->bindCurrentSession($user, $request);
            $state = $this->states->state($request, $user);
        } catch (Throwable $exception) {
            $this->pendingLogins->clear($request, PendingLoginService::PURPOSE_THINK_TANK_API);
            $this->states->clearMfaSession($request);
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw $exception;
        }

        $this->audit->bestEffort($request, 'think_tank.mfa.verified', 'Think tank portal verification completed.', [
            'target_user_id' => (string) $user->getKey(),
            'state' => $state,
        ], $user);

        if ($pending) {
            $this->audit->bestEffort($request, 'think_tank.auth.signed_in', 'Think tank portal sign-in completed.', [
                'target_user_id' => (string) $user->getKey(),
                'state' => $state,
            ], $user);
        }

        return ThinkTankApiResponse::success([
            ...$this->states->summary($state),
            'user' => $state === ThinkTankAuthenticationStateService::READY
                ? (new ThinkTankViewerResource($user))->resolve($request)
                : null,
            'challenge' => null,
        ], 200, 'Verification completed successfully.');
    }

    private function assertMfaRequired(Request $request, User $user, bool $pending): void
    {
        if ($pending || ! $this->states->hasValidMfaSession($request, $user)) {
            return;
        }

        $state = $this->states->state($request, $user);

        throw new ThinkTankApiException(
            'MFA_NOT_REQUIRED',
            'Multi-factor verification is not required for this session.',
            409,
            $this->states->summary($state),
        );
    }

    /** @return array{0: User, 1: bool} */
    private function resolveUser(Request $request): array
    {
        $authenticated = $request->user();
        $pending = ! ($authenticated instanceof User);
        $user = $authenticated instanceof User
            ? $authenticated
            : $this->pendingLogins->user($request, PendingLoginService::PURPOSE_THINK_TANK_API);

        if (! $user) {
            throw $this->unauthenticated();
        }

        try {
            $membership = $this->accounts->membership($user);

            if (! $pending && ! $this->sessions->hasValidCurrentSession($user, $request)) {
                throw $this->unauthenticated();
            }
        } catch (ThinkTankApiException) {
            $this->pendingLogins->clear($request, PendingLoginService::PURPOSE_THINK_TANK_API);

            if (! $pending) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            throw $this->unauthenticated();
        }

        $request->attributes->set('think_tank.membership', $membership);

        return [$user, $pending];
    }

    private function unauthenticated(): ThinkTankApiException
    {
        return new ThinkTankApiException(
            'UNAUTHENTICATED',
            'Authentication is required.',
            401,
            $this->states->summary(ThinkTankAuthenticationStateService::UNAUTHENTICATED),
        );
    }
}
