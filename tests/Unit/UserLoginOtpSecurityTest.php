<?php

use App\Models\UserLoginOtp;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;

function bootUserLoginOtpSecurityApplication(): array
{
    if (Container::getInstance()->bound(Kernel::class)) {
        return [Container::getInstance(), false];
    }

    $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $application->make(Kernel::class)->bootstrap();

    return [$application, true];
}

it('creates deterministic keyed digests bound to the user session and code without a database', function () {
    [, $bootedHere] = bootUserLoginOtpSecurityApplication();
    $originalKey = config('app.key');
    config(['app.key' => 'base64:'.base64_encode(str_repeat('K', 32))]);

    try {
        $digest = new ReflectionMethod(UserLoginOtp::class, 'digest');

        $first = $digest->invoke(null, 'user-a', 'session-a', '123456');
        $same = $digest->invoke(null, 'user-a', 'session-a', '123456');

        expect($first)
            ->toBe($same)
            ->toMatch('/^[a-f0-9]{64}$/')
            ->not->toContain('123456')
            ->and($digest->invoke(null, 'user-b', 'session-a', '123456'))->not->toBe($first)
            ->and($digest->invoke(null, 'user-a', 'session-b', '123456'))->not->toBe($first)
            ->and($digest->invoke(null, 'user-a', 'session-a', '654321'))->not->toBe($first);
    } finally {
        config(['app.key' => $originalKey]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});

it('releases plaintext once and excludes persisted security material from serialization', function () {
    $otp = new UserLoginOtp;
    $plaintext = new ReflectionProperty($otp, 'plaintextCode');
    $plaintext->setValue($otp, '123456');

    $otp->forceFill([
        'otp_code' => str_repeat('a', 64),
        'session_id' => 'session-a',
    ]);

    expect($otp->releasePlaintextCode())->toBe('123456')
        ->and(fn () => $otp->releasePlaintextCode())->toThrow(LogicException::class)
        ->and($otp->toArray())->not->toHaveKey('otp_code')
        ->and($otp->toArray())->not->toHaveKey('session_id');
});

it('keeps storage hashed and verification atomic without opening a database connection', function () {
    $root = dirname(__DIR__, 2);
    $model = file_get_contents($root.'/app/Models/UserLoginOtp.php');
    $login = file_get_contents($root.'/app/Http/Controllers/Auth/AuthenticatedSessionController.php');
    $security = file_get_contents($root.'/app/Http/Controllers/Auth/SecurityController.php');
    $migration = file_get_contents($root.'/database/migrations/2026_09_01_212423_harden_user_login_otp_storage.php');

    expect($model)
        ->toContain("'otp_code' => self::digest(")
        ->toContain("->where('session_id', \$sessionId)")
        ->toContain("->where('otp_code', \$digest)")
        ->toContain("->update(['verified_at' => \$now])")
        ->toContain("hash_hmac('sha256'")
        ->not->toContain("->where('otp_code', \$code)")
        ->and($login)
        ->toContain('app(ThinkTankMfaService::class)->send(')
        ->not->toContain('$otp->otp_code')
        ->and($security)
        ->toContain('app(ThinkTankMfaService::class)->send(')
        ->not->toContain('$otp->otp_code')
        ->and($migration)
        ->toContain("string('otp_code', self::DIGEST_LENGTH)->change()")
        ->toContain("->whereNull('verified_at')")
        ->toContain("'verified_at' => \$now")
        ->toContain("'expires_at' => \$now")
        ->toContain("hash('sha256', random_bytes(32)")
        ->toContain('dropIndex(self::PLAINTEXT_INDEX)');
});
