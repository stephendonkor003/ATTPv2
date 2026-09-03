<?php

namespace App\Services\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Mail\Security\LoginOtpMail;
use App\Models\User;
use App\Models\UserLoginOtp;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class ThinkTankMfaService
{
    public function __construct(private readonly ThinkTankMailSecurityService $mailSecurity) {}

    /** @return array{sent: true, expires_at: string, resend_available_at: string, masked_destination: string} */
    public function send(Request $request, User $user, bool $force = false): array
    {
        $this->mailSecurity->assertCredentialDeliveryIsSecure();

        $store = (string) (config('cache.limiter') ?: config('cache.default'));
        $lock = Cache::store($store)->lock(
            'think-tank-mfa-issue-lock:'.hash('sha256', (string) $user->getKey()),
            15,
        );

        try {
            return $lock->block(5, fn (): array => $this->issue($request, $user, $force));
        } catch (LockTimeoutException) {
            throw new ThinkTankApiException(
                'MFA_ISSUE_IN_PROGRESS',
                'A verification code is already being issued. Please wait and try again.',
                409,
                ['state' => 'MFA_REQUIRED', 'next_action' => 'VERIFY_MFA'],
            );
        }
    }

    /** @return array{sent: true, expires_at: string, resend_available_at: string, masked_destination: string} */
    private function issue(Request $request, User $user, bool $force): array
    {
        $resendSeconds = (int) config('think_tank_portal.mfa_resend_seconds', 60);
        $sentAt = $request->session()->get('think_tank_mfa_sent_at');
        $sentFor = (string) $request->session()->get('think_tank_mfa_user_id');

        if (! $force && $sentFor === (string) $user->getKey() && is_string($sentAt)) {
            try {
                $allowedAt = CarbonImmutable::parse($sentAt)->addSeconds($resendSeconds);

                if ($allowedAt->isFuture()) {
                    throw new ThinkTankApiException(
                        'MFA_RESEND_TOO_SOON',
                        'Please wait before requesting another verification code.',
                        429,
                        [
                            'state' => 'MFA_REQUIRED',
                            'next_action' => 'VERIFY_MFA',
                            'resend_available_at' => $allowedAt->toIso8601String(),
                            'retry_after' => max(0, $allowedAt->getTimestamp() - now()->getTimestamp()),
                        ],
                    );
                }
            } catch (ThinkTankApiException $exception) {
                throw $exception;
            } catch (Throwable) {
                // Malformed session state is replaced by the new challenge.
            }
        }

        $account = hash('sha256', (string) $user->getKey());
        $cooldownKey = 'think-tank-mfa-issue-cooldown:'.$account;
        $hourlyKey = 'think-tank-mfa-issue-hourly:'.$account;
        $hourlyMaximum = (int) config('think_tank_portal.mfa_issue_max_per_hour', 5);

        if (RateLimiter::tooManyAttempts($cooldownKey, 1)
            || RateLimiter::tooManyAttempts($hourlyKey, $hourlyMaximum)) {
            $retryAfter = max(
                RateLimiter::availableIn($cooldownKey),
                RateLimiter::availableIn($hourlyKey),
            );

            throw new ThinkTankApiException(
                'MFA_DELIVERY_RATE_LIMITED',
                'Too many verification codes have been requested. Please try again later.',
                429,
                [
                    'state' => 'MFA_REQUIRED',
                    'next_action' => 'VERIFY_MFA',
                    'retry_after' => $retryAfter,
                ],
            );
        }

        $challenge = UserLoginOtp::generateFor($user, $request->session()->getId());

        try {
            Mail::to($user->email)->send(new LoginOtpMail($user, $challenge->releasePlaintextCode()));
        } catch (Throwable $exception) {
            $challenge->delete();
            report($exception);

            throw new ThinkTankApiException(
                'MFA_DELIVERY_FAILED',
                'The verification code could not be delivered. Please try again.',
                503,
                ['state' => 'MFA_REQUIRED', 'next_action' => 'VERIFY_MFA'],
            );
        }

        RateLimiter::hit($cooldownKey, $resendSeconds);
        RateLimiter::hit($hourlyKey, 3600);
        $now = now();
        $request->session()->put([
            'think_tank_mfa_sent_at' => $now->toIso8601String(),
            'think_tank_mfa_user_id' => (string) $user->getKey(),
        ]);

        return [
            'sent' => true,
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'resend_available_at' => $now->copy()->addSeconds($resendSeconds)->toIso8601String(),
            'masked_destination' => $this->maskedEmail((string) $user->email),
        ];
    }

    public function verify(Request $request, User $user, string $code): bool
    {
        return UserLoginOtp::verifyCode($user, $code, $request->session()->getId());
    }

    private function maskedEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return '***';
        }

        return mb_substr($local, 0, 1).'***@'.$domain;
    }
}
