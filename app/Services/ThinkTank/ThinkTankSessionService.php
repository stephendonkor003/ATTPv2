<?php

namespace App\Services\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Models\User;
use App\Models\UserLoginOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThinkTankSessionService
{
    private const SECURITY_STAMP_KEY = 'think_tank_security_stamp';

    public function bindCurrentSession(User $user, Request $request): void
    {
        $request->session()->put(self::SECURITY_STAMP_KEY, $this->securityStamp($user));
    }

    public function assertProductionSecurityStores(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if (config('session.driver') !== 'database') {
            throw new ThinkTankApiException(
                'SECURE_SESSION_STORE_REQUIRED',
                'The Think Tank portal requires database-backed sessions in production.',
                503,
            );
        }

        $limiterStore = (string) (config('cache.limiter') ?: config('cache.default'));
        $limiterDriver = (string) config("cache.stores.{$limiterStore}.driver");

        if (! in_array($limiterDriver, ['database', 'redis', 'memcached', 'dynamodb'], true)) {
            throw new ThinkTankApiException(
                'SHARED_RATE_LIMIT_STORE_REQUIRED',
                'The Think Tank portal requires a shared production rate-limit store.',
                503,
            );
        }
    }

    public function hasValidCurrentSession(User $user, Request $request): bool
    {
        $boundStamp = $request->session()->get(self::SECURITY_STAMP_KEY);

        return is_string($boundStamp)
            && $boundStamp !== ''
            && hash_equals($this->securityStamp($user), $boundStamp);
    }

    public function revokeOtherSessions(User $user, Request $request): void
    {
        $this->assertProductionSecurityStores();
        $this->rotateRememberToken($user);

        if (config('session.driver') !== 'database') {
            return;
        }

        $connection = DB::connection(config('session.connection'));
        $table = (string) config('session.table', 'sessions');

        if (! $connection->getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $connection
            ->table($table)
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    public function revokeAllSessions(User $user): void
    {
        $this->assertProductionSecurityStores();
        $this->rotateRememberToken($user);

        if (config('session.driver') !== 'database') {
            return;
        }

        $connection = DB::connection(config('session.connection'));
        $table = (string) config('session.table', 'sessions');

        if (! $connection->getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $connection
            ->table($table)
            ->where('user_id', $user->getKey())
            ->delete();
    }

    public function invalidateMfa(User $user): void
    {
        UserLoginOtp::query()->where('user_id', $user->getKey())->delete();
        $user->forceFill(['otp_verified_at' => null])->save();
    }

    private function rotateRememberToken(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }

    private function securityStamp(User $user): string
    {
        $securityState = implode("\0", [
            'think-tank-session:v1',
            (string) $user->getKey(),
            (string) $user->getAuthPassword(),
            (string) $user->remember_token,
            mb_strtolower((string) $user->email),
            (string) $user->user_type,
            (string) $user->think_tank_member_id,
            (string) $user->think_tank_access_level,
            $user->is_disabled ? '1' : '0',
            $user->is_blacklisted ? '1' : '0',
        ]);

        return hash_hmac('sha256', $securityState, (string) config('app.key'));
    }
}
