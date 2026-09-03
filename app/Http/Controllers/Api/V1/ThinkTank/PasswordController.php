<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Http\Resources\ThinkTankViewerResource;
use App\Models\User;
use App\Services\ThinkTank\ThinkTankAccountAccessService;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTank\ThinkTankAuthenticationStateService;
use App\Services\ThinkTank\ThinkTankInvitationService;
use App\Services\ThinkTank\ThinkTankMfaService;
use App\Services\ThinkTank\ThinkTankSessionService;
use App\Support\ThinkTankApiResponse;
use Closure;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordController extends ThinkTankApiController
{
    public function __construct(
        private readonly ThinkTankAccountAccessService $accounts,
        private readonly ThinkTankAuthenticationStateService $states,
        private readonly ThinkTankInvitationService $invitations,
        private readonly ThinkTankSessionService $sessions,
        private readonly ThinkTankMfaService $mfa,
        private readonly ThinkTankApiAuditService $audit,
    ) {}

    public function forgot(Request $request): JsonResponse
    {
        $data = $this->validateOnly($request, [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
        ]);
        $email = mb_strtolower(trim($data['email']));
        $key = 'think-tank-forgot:'.hash('sha256', $email).'|'.$request->ip();
        $deliveryKey = 'think-tank-forgot-delivery:'.hash('sha256', $email);

        (new Timebox)->call(function () use ($request, $email, $key, $deliveryKey): void {
            if (! RateLimiter::tooManyAttempts($key, 1)
                && ! RateLimiter::tooManyAttempts($deliveryKey, 3)) {
                RateLimiter::hit($key, 60);
                RateLimiter::hit($deliveryKey, 3600);
                $matches = User::query()
                    ->where('user_type', 'think_tank')
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->limit(2)
                    ->get();
                $user = $matches->count() === 1 ? $matches->first() : null;

                if ($user) {
                    try {
                        $this->accounts->membership($user);
                        $sent = $this->invitations->send($user, false);
                        $this->audit->bestEffort($request, 'think_tank.password.reset_requested', 'Think tank password reset requested.', [
                            'target_user_id' => (string) $user->getKey(),
                            'delivered' => $sent,
                        ], $user);
                    } catch (ThinkTankApiException) {
                        // Keep the response indistinguishable for unavailable accounts.
                    }
                }
            }
        }, 200000);

        return ThinkTankApiResponse::success(
            ['accepted' => true],
            202,
            'If an eligible account matches that email, a password reset link will be sent.',
        );
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $this->validateOnly($request, [
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['bail', 'required', 'string', 'max:4096', $this->passwordByteRule(), 'confirmed', $this->passwordRule()],
            'password_confirmation' => ['bail', 'required', 'string', 'max:4096', $this->passwordByteRule()],
        ]);
        $email = mb_strtolower(trim($data['email']));
        $attemptKey = 'think-tank-password-reset:'.hash('sha256', $email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
            throw new ThinkTankApiException(
                'RATE_LIMITED',
                'Too many password reset attempts. Please try again later.',
                429,
                ['retry_after' => RateLimiter::availableIn($attemptKey)],
            );
        }

        RateLimiter::hit($attemptKey, 300);
        $matches = User::query()
            ->where('user_type', 'think_tank')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->limit(2)
            ->get();
        $user = $matches->count() === 1 ? $matches->first() : null;

        if (! $user) {
            $this->invalidResetToken();
        }

        try {
            $this->accounts->membership($user);
        } catch (ThinkTankApiException) {
            $this->invalidResetToken();
        }

        /** @var PasswordBroker $broker */
        $broker = Password::broker();

        if (! $broker->tokenExists($user, $data['token'])) {
            $this->invalidResetToken();
        }

        DB::transaction(function () use ($request, $broker, $user, $data): void {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            // Re-check while holding the user row lock. Concurrent submissions
            // serialize here; the first request deletes the one-use token before
            // a second request can pass this check.
            if (! $broker->tokenExists($lockedUser, $data['token'])) {
                $this->invalidResetToken();
            }

            if (Hash::check($data['password'], $lockedUser->getAuthPassword())) {
                throw ValidationException::withMessages([
                    'password' => ['The new password must be different from the current password.'],
                ]);
            }

            $lockedUser->forceFill([
                'password' => $data['password'],
                'must_change_password' => false,
                'password_changed_at' => now(),
                'email_verified_at' => $lockedUser->email_verified_at ?: now(),
                'remember_token' => Str::random(60),
                'otp_verified_at' => null,
            ])->save();
            $this->sessions->invalidateMfa($lockedUser);
            $this->sessions->revokeAllSessions($lockedUser);
            $broker->deleteToken($lockedUser);
            $this->audit->required($request, 'think_tank.password.reset', 'Think tank portal password reset completed.', [
                'target_user_id' => (string) $lockedUser->getKey(),
            ], $lockedUser);
        });

        RateLimiter::clear($attemptKey);
        RateLimiter::clear('think-tank-login-account:'.hash('sha256', $email));
        Event::dispatch(new PasswordReset($user));

        return ThinkTankApiResponse::success([
            ...$this->states->summary(ThinkTankAuthenticationStateService::UNAUTHENTICATED),
            'user' => null,
            'challenge' => null,
        ], 200, 'Password reset successfully. Sign in with your new password.');
    }

    public function update(Request $request): JsonResponse
    {
        $data = $this->validateOnly($request, [
            'current_password' => ['bail', 'required', 'string', 'max:4096', $this->passwordByteRule()],
            'password' => ['bail', 'required', 'string', 'max:4096', $this->passwordByteRule(), 'different:current_password', 'confirmed', $this->passwordRule()],
            'password_confirmation' => ['bail', 'required', 'string', 'max:4096', $this->passwordByteRule()],
        ]);
        $user = DB::transaction(function () use ($request, $data): User {
            $lockedUser = User::query()
                ->whereKey($request->user()->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $this->accounts->membership($lockedUser);

            if (! Hash::check($data['current_password'], $lockedUser->getAuthPassword())) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }

            $lockedUser->forceFill([
                'password' => $data['password'],
                'must_change_password' => false,
                'password_changed_at' => now(),
                'otp_verified_at' => null,
            ])->save();
            $this->sessions->invalidateMfa($lockedUser);
            $this->sessions->revokeOtherSessions($lockedUser, $request);
            $this->audit->required($request, 'think_tank.password.changed', 'Think tank portal password changed.', [
                'target_user_id' => (string) $lockedUser->getKey(),
            ], $lockedUser);

            return $lockedUser;
        });

        $this->sessions->bindCurrentSession($user, $request);
        $this->states->clearMfaSession($request);
        $state = $this->states->state($request, $user);
        $challenge = $state === ThinkTankAuthenticationStateService::MFA_REQUIRED
            ? $this->mfa->send($request, $user, true)
            : null;

        return ThinkTankApiResponse::success([
            ...$this->states->summary($state),
            'user' => $state === ThinkTankAuthenticationStateService::READY
                ? (new ThinkTankViewerResource($user))->resolve($request)
                : null,
            'challenge' => $challenge,
        ], 200, 'Password changed successfully.');
    }

    private function passwordRule(): PasswordRule
    {
        return PasswordRule::min((int) config('think_tank_portal.password_min_length', 12))
            ->mixedCase()
            ->numbers()
            ->symbols();
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

    private function invalidResetToken(): never
    {
        throw ValidationException::withMessages([
            'token' => ['This password reset link is invalid or has expired.'],
        ]);
    }
}
