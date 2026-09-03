<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Http\Resources\ThinkTankViewerResource;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTank\ThinkTankAuthenticationStateService;
use App\Services\ThinkTank\ThinkTankMfaService;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class MfaController extends ThinkTankApiController
{
    public function __construct(
        private readonly ThinkTankAuthenticationStateService $states,
        private readonly ThinkTankMfaService $mfa,
        private readonly ThinkTankApiAuditService $audit,
    ) {}

    public function resend(Request $request): JsonResponse
    {
        $this->validateOnly($request, []);
        $user = $request->user();
        $this->assertMfaRequired($request);
        $challenge = $this->mfa->send($request, $user);

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
        $user = $request->user();
        $this->assertMfaRequired($request);
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
        $user->forceFill(['otp_verified_at' => now()])->save();
        $request->session()->regenerate();
        $this->states->markMfaVerified($request, $user);
        $this->audit->bestEffort($request, 'think_tank.mfa.verified', 'Think tank portal verification completed.', [
            'target_user_id' => (string) $user->getKey(),
        ], $user);

        return ThinkTankApiResponse::success([
            ...$this->states->summary(ThinkTankAuthenticationStateService::READY),
            'user' => (new ThinkTankViewerResource($user))->resolve($request),
            'challenge' => null,
        ], 200, 'Verification completed successfully.');
    }

    private function assertMfaRequired(Request $request): void
    {
        $state = $this->states->state($request, $request->user());

        if ($state === ThinkTankAuthenticationStateService::MFA_REQUIRED) {
            return;
        }

        if ($state === ThinkTankAuthenticationStateService::PASSWORD_CHANGE_REQUIRED) {
            throw new ThinkTankApiException(
                'PASSWORD_CHANGE_REQUIRED',
                'Change your password before completing multi-factor verification.',
                409,
                $this->states->summary($state),
            );
        }

        throw new ThinkTankApiException(
            'MFA_NOT_REQUIRED',
            'Multi-factor verification is not required for this session.',
            409,
            $this->states->summary($state),
        );
    }
}
