<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

final class PendingLoginService
{
    public const PURPOSE_WEB = 'web';

    public const PURPOSE_THINK_TANK_API = 'think_tank_api';

    private const USER_ID = 'security.pending_login.user_id';

    private const REMEMBER = 'security.pending_login.remember';

    private const EXPIRES_AT = 'security.pending_login.expires_at';

    private const SECURITY_FINGERPRINT = 'security.pending_login.security_fingerprint';

    private const PURPOSE = 'security.pending_login.purpose';

    private const CHALLENGE = 'security.pending_login.challenge';

    /** @param array<string, mixed>|null $challenge */
    public function begin(
        Request $request,
        User $user,
        bool $remember,
        string $expiresAt,
        string $purpose,
        ?array $challenge = null,
    ): void {
        $this->clear($request);
        $request->session()->put([
            self::USER_ID => (string) $user->getKey(),
            self::REMEMBER => $remember,
            self::EXPIRES_AT => $expiresAt,
            self::SECURITY_FINGERPRINT => $this->securityFingerprint($user),
            self::PURPOSE => $purpose,
            self::CHALLENGE => $this->sanitizeChallenge($challenge),
        ]);
    }

    public function user(Request $request, string $purpose): ?User
    {
        if (! $this->hasPurpose($request, $purpose)) {
            return null;
        }

        $userId = $request->session()->get(self::USER_ID);
        $expiresAt = $request->session()->get(self::EXPIRES_AT);
        $fingerprint = $request->session()->get(self::SECURITY_FINGERPRINT);

        if (! is_string($userId) || $userId === '' || ! is_string($expiresAt) || ! is_string($fingerprint)) {
            $this->clear($request);

            return null;
        }

        try {
            if (! CarbonImmutable::parse($expiresAt)->isFuture()) {
                $this->clear($request);

                return null;
            }
        } catch (Throwable) {
            $this->clear($request);

            return null;
        }

        $user = User::query()->find($userId);
        if (! $user || ! hash_equals($fingerprint, $this->securityFingerprint($user))) {
            $this->clear($request);

            return null;
        }

        return $user;
    }

    public function remember(Request $request, string $purpose): bool
    {
        if (! $this->hasPurpose($request, $purpose)) {
            return false;
        }

        return (bool) $request->session()->get(self::REMEMBER, false);
    }

    /** @param array<string, mixed>|null $challenge */
    public function refreshExpiration(
        Request $request,
        string $expiresAt,
        string $purpose,
        ?array $challenge = null,
    ): void {
        if ($this->hasPurpose($request, $purpose) && $request->session()->has(self::USER_ID)) {
            $request->session()->put(self::EXPIRES_AT, $expiresAt);

            if ($challenge !== null) {
                $request->session()->put(self::CHALLENGE, $this->sanitizeChallenge($challenge));
            }
        }
    }

    /** @return array<string, mixed>|null */
    public function challenge(Request $request, string $purpose): ?array
    {
        if (! $this->hasPurpose($request, $purpose)) {
            return null;
        }

        $challenge = $request->session()->get(self::CHALLENGE);

        return is_array($challenge) ? $this->sanitizeChallenge($challenge) : null;
    }

    public function clear(Request $request, ?string $purpose = null): void
    {
        if ($purpose !== null && ! $this->hasPurpose($request, $purpose)) {
            return;
        }

        $request->session()->forget([
            self::USER_ID,
            self::REMEMBER,
            self::EXPIRES_AT,
            self::SECURITY_FINGERPRINT,
            self::PURPOSE,
            self::CHALLENGE,
        ]);
    }

    private function hasPurpose(Request $request, string $purpose): bool
    {
        return hash_equals($purpose, (string) $request->session()->get(self::PURPOSE, ''));
    }

    /**
     * Persist only the public challenge metadata already returned to the same
     * browser. Never retain arbitrary mail-service response fields.
     *
     * @param  array<string, mixed>|null  $challenge
     * @return array<string, mixed>|null
     */
    private function sanitizeChallenge(?array $challenge): ?array
    {
        if ($challenge === null) {
            return null;
        }

        $sanitized = array_intersect_key($challenge, array_flip([
            'sent',
            'expires_at',
            'resend_available_at',
            'masked_destination',
            'local_code',
        ]));

        if (! app()->environment('local') || ! (bool) config('think_tank_portal.show_local_otp', false)) {
            unset($sanitized['local_code']);
        }

        return $sanitized;
    }

    private function securityFingerprint(User $user): string
    {
        return hash_hmac('sha256', implode("\0", [
            'pending-login:v1',
            (string) $user->getKey(),
            (string) $user->getAuthPassword(),
            (string) $user->remember_token,
            mb_strtolower((string) $user->email),
            (string) $user->user_type,
            $user->is_disabled ? '1' : '0',
            $user->is_blacklisted ? '1' : '0',
            (string) $user->disabled_until,
            (string) $user->think_tank_member_id,
            (string) $user->think_tank_access_level,
        ]), (string) config('app.key'));
    }
}
