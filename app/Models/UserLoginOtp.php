<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use SensitiveParameter;

class UserLoginOtp extends BaseModel
{
    private const CODE_LENGTH = 6;

    private const DIGEST_CONTEXT = 'attp-login-otp:v1';

    private const EXPIRES_AFTER_MINUTES = 10;

    /**
     * The deliverable code exists only on the newly generated in-memory model.
     * It is deliberately neither an Eloquent attribute nor serializable.
     */
    private ?string $plaintextCode = null;

    protected $fillable = [
        'user_id',
        'session_id',
        'expires_at',
        'verified_at',
        'ip_address',
        'user_agent',
    ];

    protected $hidden = [
        'otp_code',
        'session_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Generate a session-bound OTP while persisting only its keyed digest.
     */
    public static function generateFor(User $user, ?string $sessionId = null): self
    {
        $sessionId = self::requireSessionId($sessionId);
        $otpCode = str_pad(
            (string) random_int(0, (10 ** self::CODE_LENGTH) - 1),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT
        );
        $now = now();

        $otp = DB::transaction(function () use ($user, $sessionId, $otpCode, $now): self {
            // Serialize challenge replacement per user so concurrent sends cannot
            // leave two active codes behind.
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            // Replacing a challenge is scoped to this browser session. A
            // login or resend in one session must not invalidate another
            // legitimate session's challenge.
            self::query()
                ->where('user_id', $user->getKey())
                ->where('session_id', $sessionId)
                ->whereNull('verified_at')
                ->delete();

            $challenge = new self;
            $challenge->forceFill([
                'user_id' => $user->getKey(),
                'otp_code' => self::digest($user->getKey(), $sessionId, $otpCode),
                'session_id' => $sessionId,
                'expires_at' => $now->copy()->addMinutes(self::EXPIRES_AFTER_MINUTES),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])->save();

            return $challenge;
        });

        $otp->plaintextCode = $otpCode;

        return $otp;
    }

    /**
     * Return the generated code exactly once and remove it from model memory.
     */
    public function releasePlaintextCode(): string
    {
        if ($this->plaintextCode === null) {
            throw new LogicException('The plaintext OTP is no longer available.');
        }

        $code = $this->plaintextCode;
        $this->plaintextCode = null;

        return $code;
    }

    /**
     * Atomically consume a valid code. Exactly one request can change the
     * challenge from unverified to verified.
     */
    public static function verifyCode(
        User $user,
        #[SensitiveParameter] string $code,
        ?string $sessionId = null
    ): bool {
        if (! preg_match('/^\d{'.self::CODE_LENGTH.'}$/D', $code)) {
            return false;
        }

        try {
            $sessionId = self::requireSessionId($sessionId);
        } catch (InvalidArgumentException) {
            return false;
        }

        $now = now();
        $digest = self::digest($user->getKey(), $sessionId, $code);

        $consumed = self::query()
            ->where('user_id', $user->getKey())
            ->where('session_id', $sessionId)
            ->where('otp_code', $digest)
            ->whereNull('verified_at')
            ->where('expires_at', '>', $now)
            ->update(['verified_at' => $now]);

        return $consumed === 1;
    }

    private static function requireSessionId(?string $sessionId): string
    {
        $sessionId = trim((string) $sessionId);

        if ($sessionId === '') {
            throw new InvalidArgumentException('A browser session is required to issue or verify a login OTP.');
        }

        return $sessionId;
    }

    private static function digest(
        string $userId,
        string $sessionId,
        #[SensitiveParameter] string $code
    ): string {
        $message = implode("\0", [
            self::DIGEST_CONTEXT,
            $userId,
            $sessionId,
            $code,
        ]);

        return hash_hmac('sha256', $message, self::hashKey());
    }

    private static function hashKey(): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY is required to protect login OTP digests.');
        }

        if (! str_starts_with($key, 'base64:')) {
            return $key;
        }

        $decoded = base64_decode(substr($key, 7), true);

        if ($decoded === false || $decoded === '') {
            throw new RuntimeException('APP_KEY contains invalid base64 key material.');
        }

        return $decoded;
    }
}
