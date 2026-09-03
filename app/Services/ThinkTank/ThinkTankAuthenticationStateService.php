<?php

namespace App\Services\ThinkTank;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ThinkTankAuthenticationStateService
{
    public const UNAUTHENTICATED = 'UNAUTHENTICATED';

    public const PASSWORD_CHANGE_REQUIRED = 'PASSWORD_CHANGE_REQUIRED';

    public const MFA_REQUIRED = 'MFA_REQUIRED';

    public const READY = 'READY';

    public function state(Request $request, User $user): string
    {
        if ($user->mustChangePassword() || $user->isPasswordExpired()) {
            return self::PASSWORD_CHANGE_REQUIRED;
        }

        if ($this->requiresMfa() && ! $this->hasValidMfaSession($request, $user)) {
            return self::MFA_REQUIRED;
        }

        return self::READY;
    }

    public function nextAction(string $state): string
    {
        return match ($state) {
            self::UNAUTHENTICATED => 'LOGIN',
            self::PASSWORD_CHANGE_REQUIRED => 'CHANGE_PASSWORD',
            self::MFA_REQUIRED => 'VERIFY_MFA',
            default => 'NONE',
        };
    }

    /** @return array{state: string, next_action: string} */
    public function summary(string $state): array
    {
        return ['state' => $state, 'next_action' => $this->nextAction($state)];
    }

    public function clearMfaSession(Request $request): void
    {
        $request->session()->forget([
            'otp_verified',
            'otp_verified_at',
            'otp_verified_user_id',
            'think_tank_mfa_sent_at',
            'think_tank_mfa_user_id',
        ]);
    }

    public function markMfaVerified(Request $request, User $user): void
    {
        $request->session()->put([
            'otp_verified' => true,
            'otp_verified_at' => now()->toIso8601String(),
            'otp_verified_user_id' => (string) $user->getKey(),
        ]);
        $request->session()->forget(['think_tank_mfa_sent_at', 'think_tank_mfa_user_id']);
    }

    private function hasValidMfaSession(Request $request, User $user): bool
    {
        if (! $request->session()->get('otp_verified', false)) {
            return false;
        }

        if ((string) $request->session()->get('otp_verified_user_id') !== (string) $user->getKey()) {
            return false;
        }

        $verifiedAt = $request->session()->get('otp_verified_at');

        if (! is_string($verifiedAt) || $verifiedAt === '') {
            return false;
        }

        try {
            return CarbonImmutable::parse($verifiedAt)->isAfter(
                now()->subHours((int) config('think_tank_portal.mfa_verification_hours', 24))
            );
        } catch (\Throwable) {
            return false;
        }
    }

    private function requiresMfa(): bool
    {
        // Production cannot silently inherit the legacy application's local
        // OTP bypass. Development opt-out remains explicit for isolated work.
        return app()->environment('production')
            || (bool) config('think_tank_portal.require_mfa', true);
    }
}
