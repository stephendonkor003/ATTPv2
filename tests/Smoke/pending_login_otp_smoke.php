<?php

use App\Mail\Security\LoginOtpMail;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

final class PendingLoginOtpSmoke
{
    use InteractsWithSession;
    use MakesHttpRequests;

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function run(): void
    {
        $originalOtpSetting = config('security.require_login_otp_locally');
        config(['security.require_login_otp_locally' => true]);
        Mail::fake();
        DB::beginTransaction();

        try {
            $password = 'PendingLogin!'.Str::random(16).'8';
            $user = User::query()->create([
                'name' => 'Pending Login OTP Smoke',
                'email' => 'pending-login-'.Str::lower(Str::random(12)).'@example.test',
                'password' => Hash::make($password),
                'user_type' => 'staff',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
                'must_change_password' => false,
                'is_disabled' => false,
                'is_blacklisted' => false,
            ]);

            $login = $this->postWithCsrf('/login', [
                'email' => $user->email,
                'password' => $password,
            ]);
            $this->require($login->isRedirect(route('security.otp.show')), 'Password validation did not enter the OTP challenge.');
            $this->require(! auth('web')->check(), 'Password validation created an authenticated session before OTP.');
            $this->require($login->getSession()->has('security.pending_login.user_id'), 'Pending login state was not stored.');

            $otpCode = null;
            Mail::assertSent(LoginOtpMail::class, function (LoginOtpMail $mail) use (&$otpCode, $user): bool {
                if ((string) $mail->user->getKey() !== (string) $user->getKey()) {
                    return false;
                }

                $otpCode = $mail->otpCode;

                return true;
            });
            $this->require(is_string($otpCode) && preg_match('/^\d{6}$/D', $otpCode) === 1, 'A bounded OTP was not delivered.');

            $sessionCookieName = (string) config('session.cookie');
            $sessionCookie = $login->getCookie($sessionCookieName, false);
            $this->require($sessionCookie !== null, 'The OTP challenge did not return its rotated session cookie.');
            $this->withUnencryptedCookie($sessionCookieName, $sessionCookie->getValue());

            $verified = $this->postWithCsrf(route('security.otp.verify'), ['otp_code' => $otpCode]);
            $this->require($verified->isRedirect(route('dashboard')), 'A valid OTP did not complete the intended staff login.');
            $this->require(auth('web')->check() && (string) auth('web')->id() === (string) $user->getKey(), 'OTP completion did not authenticate the expected user.');
            $this->require(! $verified->getSession()->has('security.pending_login.user_id'), 'Pending login state survived completed authentication.');
        } finally {
            DB::rollBack();
            config(['security.require_login_otp_locally' => $originalOtpSetting]);
        }

        echo "PENDING_LOGIN_OTP_SMOKE_OK password validation stayed unauthenticated until session-bound OTP verification\n";
    }

    private function postWithCsrf(string $uri, array $data = [])
    {
        $token = Str::random(40);

        return $this->withSession(['_token' => $token])
            ->post($uri, ['_token' => $token, ...$data]);
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}

(new PendingLoginOtpSmoke($app))->run();
