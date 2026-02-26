<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLoginOtp extends BaseModel
{
    protected $fillable = [
        'user_id',
        'otp_code',
        'session_id',
        'expires_at',
        'verified_at',
        'ip_address',
        'user_agent',
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

    public function verify(): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        $this->update(['verified_at' => now()]);
        return true;
    }

    /**
     * Generate a new OTP for a user
     */
    public static function generateFor(User $user, ?string $sessionId = null): self
    {
        // Invalidate any existing OTPs for this user
        self::where('user_id', $user->id)
            ->whereNull('verified_at')
            ->delete();

        // Generate 6-digit OTP
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return self::create([
            'user_id' => $user->id,
            'otp_code' => $otpCode,
            'session_id' => $sessionId,
            'expires_at' => now()->addMinutes(10), // OTP valid for 10 minutes
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Verify OTP code for a user
     */
    public static function verifyCode(User $user, string $code, ?string $sessionId = null): bool
    {
        $query = self::where('user_id', $user->id)
            ->where('otp_code', $code)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now());

        if ($sessionId) {
            $query->where('session_id', $sessionId);
        }

        $otp = $query->first();

        if (!$otp) {
            return false;
        }

        $otp->verify();
        return true;
    }
}
