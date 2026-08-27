<?php

namespace App\Support;

use Illuminate\Http\Request;

final class UserImpersonation
{
    public const SESSION_KEY = 'user_impersonation';

    public const AUTH_SWITCH_ATTRIBUTE = 'user_impersonation_auth_switch';

    /**
     * @return array{administrator_id: string, user_id: string, started_at: string}|null
     */
    public static function state(Request $request): ?array
    {
        if (! $request->hasSession()) {
            return null;
        }

        $state = $request->session()->get(self::SESSION_KEY);

        if (! is_array($state)) {
            return null;
        }

        $administratorId = trim((string) ($state['administrator_id'] ?? ''));
        $userId = trim((string) ($state['user_id'] ?? ''));

        if ($administratorId === '' || $userId === '' || $administratorId === $userId) {
            return null;
        }

        return $state;
    }

    public static function isActive(Request $request): bool
    {
        $state = self::state($request);
        $currentUserId = (string) ($request->user()?->id ?? '');

        return $state !== null
            && $currentUserId !== ''
            && hash_equals((string) $state['user_id'], $currentUserId);
    }

    public static function clearIdentityVerificationState(Request $request): void
    {
        $request->session()->forget([
            'auth.password_confirmed_at',
            'otp_verified',
            'otp_verified_at',
            'otp_verified_user_id',
            'url.intended',
            'devOtpCode',
        ]);
    }

    public static function markAuthSwitch(Request $request): void
    {
        $request->attributes->set(self::AUTH_SWITCH_ATTRIBUTE, true);
    }
}
