<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Http\Resources\ThinkTankViewerResource;
use App\Models\User;
use App\Models\UserLoginOtp;
use App\Services\ThinkTank\ThinkTankAccountAccessService;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTank\ThinkTankAuthenticationStateService;
use App\Services\ThinkTank\ThinkTankMfaService;
use App\Services\ThinkTank\ThinkTankSessionService;
use App\Support\ThinkTankApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class AuthenticationController extends ThinkTankApiController
{
    public function __construct(
        private readonly ThinkTankAccountAccessService $accounts,
        private readonly ThinkTankAuthenticationStateService $states,
        private readonly ThinkTankMfaService $mfa,
        private readonly ThinkTankSessionService $sessions,
        private readonly ThinkTankApiAuditService $audit,
    ) {}

    public function session(Request $request): JsonResponse
    {
        $this->validateOnly($request, []);
        $user = $request->user();

        if (! $user) {
            return ThinkTankApiResponse::success($this->authPayload(
                $request,
                ThinkTankAuthenticationStateService::UNAUTHENTICATED,
            ));
        }

        try {
            $membership = $this->accounts->membership($user);

            if (! $this->sessions->hasValidCurrentSession($user, $request)) {
                throw $this->accounts->unavailable();
            }
        } catch (ThinkTankApiException) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return ThinkTankApiResponse::success($this->authPayload(
                $request,
                ThinkTankAuthenticationStateService::UNAUTHENTICATED,
            ));
        }

        $request->attributes->set('think_tank.membership', $membership);
        $state = $this->states->state($request, $user);

        return ThinkTankApiResponse::success($this->authPayload($request, $state, $user));
    }

    public function login(Request $request): JsonResponse
    {
        $data = $this->validateOnly($request, [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['bail', 'required', 'string', 'max:4096', $this->passwordByteRule()],
        ]);
        $email = mb_strtolower(trim($data['email']));
        $emailHash = hash('sha256', $email);
        $rateKey = 'think-tank-login:'.$emailHash.'|'.$request->ip();
        $accountRateKey = 'think-tank-login-account:'.$emailHash;
        $accountMaximum = (int) config('think_tank_portal.login_email_max_attempts', 20);

        if (RateLimiter::tooManyAttempts($rateKey, 5)
            || RateLimiter::tooManyAttempts($accountRateKey, $accountMaximum)) {
            throw new ThinkTankApiException(
                'RATE_LIMITED',
                'Too many sign-in attempts. Please try again later.',
                429,
                ['retry_after' => max(
                    RateLimiter::availableIn($rateKey),
                    RateLimiter::availableIn($accountRateKey),
                )],
            );
        }

        $matches = User::query()
            ->where('user_type', 'think_tank')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->limit(2)
            ->get();
        $user = $matches->count() === 1 ? $matches->first() : null;
        $candidateHash = $user?->getAuthPassword()
            ?: (string) config('think_tank_portal.dummy_password_hash');

        if (! Hash::check($data['password'], $candidateHash) || ! $user) {
            RateLimiter::hit($rateKey, 60);
            RateLimiter::hit(
                $accountRateKey,
                (int) config('think_tank_portal.login_email_decay_seconds', 900),
            );
            $this->audit->bestEffort($request, 'think_tank.auth.failed', 'Think tank portal authentication failed.', [
                'reason' => 'invalid_credentials',
            ]);

            throw new ThinkTankApiException(
                'AUTHENTICATION_FAILED',
                'The provided credentials are invalid.',
                401,
                $this->states->summary(ThinkTankAuthenticationStateService::UNAUTHENTICATED),
            );
        }

        try {
            $membership = $this->accounts->membership($user);
        } catch (ThinkTankApiException $exception) {
            RateLimiter::hit($rateKey, 60);
            $this->audit->bestEffort($request, 'think_tank.auth.denied', 'Think tank portal authentication denied.', [
                'target_user_id' => (string) $user->getKey(),
                'reason' => 'account_unavailable',
            ], $user);
            throw $exception;
        }

        if ((bool) config('hashing.rehash_on_login', true)
            && Hash::needsRehash($user->getAuthPassword())) {
            $user->forceFill(['password' => $data['password']])->save();
        }

        RateLimiter::clear($rateKey);
        Auth::guard('web')->login($user, false);
        $request->session()->regenerate();
        $this->states->clearMfaSession($request);
        $this->sessions->bindCurrentSession($user, $request);
        $request->attributes->set('think_tank.membership', $membership);

        $state = $this->states->state($request, $user);
        $challenge = $state === ThinkTankAuthenticationStateService::MFA_REQUIRED
            ? $this->mfa->send($request, $user, true)
            : null;

        if ($state === ThinkTankAuthenticationStateService::READY) {
            RateLimiter::clear($accountRateKey);
        }

        $this->audit->bestEffort($request, 'think_tank.auth.signed_in', 'Think tank portal password accepted.', [
            'target_user_id' => (string) $user->getKey(),
            'state' => $state,
        ], $user);

        return ThinkTankApiResponse::success(
            $this->authPayload($request, $state, $user, $challenge),
            200,
            $state === ThinkTankAuthenticationStateService::READY
                ? 'Signed in successfully.'
                : 'Additional security action is required.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->validateOnly($request, []);
        $user = $request->user();
        $sessionId = $request->session()->getId();

        $this->audit->bestEffort($request, 'think_tank.auth.signed_out', 'Think tank portal user signed out.', [
            'target_user_id' => (string) $user->getKey(),
        ], $user);
        try {
            UserLoginOtp::query()
                ->where('user_id', $user->getKey())
                ->where('session_id', $sessionId)
                ->delete();
        } catch (Throwable $exception) {
            // Challenge cleanup must never keep an authenticated session alive.
            report($exception);
        }

        try {
            $this->states->clearMfaSession($request);
        } catch (Throwable $exception) {
            report($exception);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ThinkTankApiResponse::success($this->authPayload(
            $request,
            ThinkTankAuthenticationStateService::UNAUTHENTICATED,
        ), 200, 'Signed out successfully.');
    }

    /**
     * @param  array<string, mixed>|null  $challenge
     * @return array<string, mixed>
     */
    private function authPayload(Request $request, string $state, ?User $user = null, ?array $challenge = null): array
    {
        return [
            ...$this->states->summary($state),
            // Tenant identity and capabilities are released only after the
            // complete password + MFA ceremony reaches READY.
            'user' => $state === ThinkTankAuthenticationStateService::READY && $user
                ? (new ThinkTankViewerResource($user))->resolve($request)
                : null,
            'challenge' => $challenge,
        ];
    }

    private function passwordByteRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value)
                && strlen($value) > (int) config('think_tank_portal.password_max_bytes', 72)) {
                $fail('The :attribute must not exceed 72 bytes.');
            }
        };
    }
}
