<?php

use App\Mail\Security\LoginOtpMail;
use App\Models\Consortium;
use App\Models\ConsortiumThinkTank;
use App\Models\User;
use App\Models\UserLoginOtp;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

$expectedDatabase = 'attpv1_codex_test';

if ((string) getenv('DB_CONNECTION') !== 'pgsql'
    || (string) getenv('DB_DATABASE') !== $expectedDatabase) {
    throw new RuntimeException(
        "Refusing to run: set DB_CONNECTION=pgsql and DB_DATABASE={$expectedDatabase} explicitly."
    );
}

// Local mode keeps the real CSRF middleware active while avoiding production-only
// preflight requirements. The database target still comes only from the caller.
foreach ([
    'APP_ENV' => 'local',
    'APP_DEBUG' => 'false',
    'APP_URL' => 'http://127.0.0.1:8001',
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'database',
    'SESSION_COOKIE' => 'attpv1_api_login_smoke_session',
    'CACHE_STORE' => 'array',
    'SANCTUM_STATEFUL_DOMAINS' => 'localhost:3100',
    'THINK_TANK_PORTAL_URL' => 'http://localhost:3100',
    'THINK_TANK_PORTAL_ALLOWED_ORIGINS' => 'http://localhost:3100',
] as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

/**
 * A tiny same-origin browser that goes through Laravel's real HTTP kernel.
 * It preserves encrypted response cookies and sends the encrypted XSRF cookie
 * back through the X-XSRF-TOKEN header exactly as the portal does.
 */
final class ThinkTankApiSmokeBrowser
{
    /** @var array<string, string> */
    private array $cookies = [];

    public function __construct(
        private readonly HttpKernel $kernel,
        private readonly string $backendOrigin = 'http://127.0.0.1:8001',
        private readonly string $frontendOrigin = 'http://localhost:3100',
    ) {}

    /** @return array{status: int, json: array<string, mixed>|null, response: Response, authenticated: bool, auth_id: ?string} */
    public function request(
        string $method,
        string $path,
        array $payload = [],
        bool $includeCsrf = true,
    ): array {
        // A PHP web request gets a fresh guard. Reset it here so a prior synthetic
        // request cannot make session persistence appear healthier than it is.
        Auth::forgetGuards();

        $method = strtoupper($method);
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ORIGIN' => $this->frontendOrigin,
            'HTTP_REFERER' => $this->frontendOrigin.'/',
            'HTTP_USER_AGENT' => 'ATTP PostgreSQL login ceremony smoke',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $content = null;

        if (! in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $content = json_encode($payload, JSON_THROW_ON_ERROR);
            $server['CONTENT_TYPE'] = 'application/json';
            $server['CONTENT_LENGTH'] = (string) strlen($content);

            if ($includeCsrf && isset($this->cookies['XSRF-TOKEN'])) {
                $server['HTTP_X_XSRF_TOKEN'] = rawurldecode($this->cookies['XSRF-TOKEN']);
            }
        }

        $request = Request::create(
            $this->backendOrigin.$path,
            $method,
            [],
            $this->cookies,
            [],
            $server,
            $content,
        );
        $response = $this->kernel->handle($request);
        $authenticated = Auth::guard('web')->check();
        $authId = Auth::guard('web')->id();
        $this->kernel->terminate($request, $response);
        $this->retainCookies($response);

        $body = trim((string) $response->getContent());
        $json = $body === '' ? null : json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        return [
            'status' => $response->getStatusCode(),
            'json' => is_array($json) ? $json : null,
            'response' => $response,
            'authenticated' => $authenticated,
            'auth_id' => is_string($authId) ? $authId : null,
        ];
    }

    /** @return list<string> */
    public function cookieNames(): array
    {
        return array_keys($this->cookies);
    }

    private function retainCookies(Response $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (! $cookie instanceof Cookie) {
                continue;
            }

            if ($cookie->getExpiresTime() !== 0 && $cookie->getExpiresTime() <= time()) {
                unset($this->cookies[$cookie->getName()]);

                continue;
            }

            $this->cookies[$cookie->getName()] = $cookie->getValue();
        }
    }
}

function thinkTankLoginSmokeAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function thinkTankLoginSmokeData(array $response): array
{
    $data = $response['json']['data'] ?? null;

    if (! is_array($data)) {
        throw new RuntimeException('The API response did not contain an object in data.');
    }

    return $data;
}

if (! $app->environment('local')) {
    throw new RuntimeException('The smoke must run in local mode so CSRF is enforced without production side effects.');
}

config([
    'app.debug' => false,
    'app.url' => 'http://127.0.0.1:8001',
    'database.default' => 'pgsql',
    'mail.default' => 'array',
    'queue.default' => 'sync',
    'session.driver' => 'database',
    'session.connection' => 'pgsql',
    'session.cookie' => 'attpv1_api_login_smoke_session',
    'session.encrypt' => true,
    'session.domain' => null,
    'session.secure' => false,
    'session.http_only' => true,
    'session.same_site' => 'lax',
    'session.lottery' => [0, 100],
    'cache.default' => 'array',
    'cache.limiter' => 'array',
    'think_tank_portal.require_mfa' => true,
    'think_tank_portal.show_local_otp' => false,
    'think_tank_portal.email_lock_store' => 'array',
    'think_tank_portal.frontend_url' => 'http://localhost:3100',
    'think_tank_portal.allowed_origins' => ['http://localhost:3100'],
    'sanctum.stateful' => ['localhost:3100'],
    'cors.allowed_origins' => ['http://localhost:3100'],
]);

$app->make('session')->forgetDrivers();
$app->make('cache')->forgetDriver('array');
Auth::forgetGuards();
Mail::fake();

$connection = DB::connection('pgsql');
$database = (string) ($connection->selectOne('select current_database() as database')->database ?? '');

thinkTankLoginSmokeAssert(
    $connection->getDriverName() === 'pgsql' && hash_equals($expectedDatabase, $database),
    'The active connection is not the isolated PostgreSQL smoke database.',
);

$runToken = Str::lower(Str::random(12));
$email = "think-tank-api-login-{$runToken}@example.test";
$currentPassword = 'Current-Smoke!'.Str::random(16).'7a';
$newPassword = 'Changed-Smoke!'.Str::random(16).'8B';
$createdUserId = null;
$rolledBack = false;

$connection->beginTransaction();

try {
    $consortium = Consortium::query()->create([
        'code' => 'PG-AUTH-'.Str::upper($runToken),
        'name' => 'PostgreSQL API authentication smoke consortium',
        'country' => 'Ghana',
        'region' => 'West Africa',
        'approved_budget' => 0,
        'currency' => 'USD',
        'status' => 'active',
        'mandate' => 'Transaction-isolated authentication verification.',
    ]);
    $membership = ConsortiumThinkTank::query()->create([
        'consortium_id' => $consortium->getKey(),
        'name' => 'PostgreSQL API authentication smoke think tank',
        'country' => 'Ghana',
        'email' => $email,
        'role' => 'lead',
        'budget_allocated' => 0,
        'status' => 'active',
        'joined_at' => now()->toDateString(),
    ]);
    $user = User::query()->create([
        'name' => 'PostgreSQL API Login Smoke',
        'email' => $email,
        'password' => $currentPassword,
        'user_type' => 'think_tank',
        'think_tank_member_id' => $membership->getKey(),
        'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
        'must_change_password' => true,
        'password_changed_at' => now(),
        'otp_verified_at' => null,
        'is_disabled' => false,
        'is_blacklisted' => false,
    ]);
    $createdUserId = (string) $user->getKey();
    $membership->forceFill(['portal_user_id' => $createdUserId])->save();

    $browser = new ThinkTankApiSmokeBrowser($app->make(HttpKernel::class));
    $csrf = $browser->request('GET', '/sanctum/csrf-cookie');
    thinkTankLoginSmokeAssert($csrf['status'] === 204, 'The CSRF bootstrap route did not return 204.');
    thinkTankLoginSmokeAssert(
        in_array('XSRF-TOKEN', $browser->cookieNames(), true)
            && in_array('attpv1_api_login_smoke_session', $browser->cookieNames(), true),
        'The CSRF bootstrap did not issue both XSRF and session cookies.',
    );

    $csrfRejected = $browser->request('POST', '/api/v1/think-tank/auth/login', [
        'email' => $email,
        'password' => $currentPassword,
    ], false);
    thinkTankLoginSmokeAssert($csrfRejected['status'] === 419, 'A login without CSRF was not rejected with 419.');
    thinkTankLoginSmokeAssert(
        ($csrfRejected['json']['code'] ?? null) === 'CSRF_MISMATCH',
        'The CSRF rejection did not use the stable API error contract.',
    );
    thinkTankLoginSmokeAssert(Mail::sent(LoginOtpMail::class)->isEmpty(), 'The CSRF-rejected request sent mail.');

    $login = $browser->request('POST', '/api/v1/think-tank/auth/login', [
        'email' => mb_strtoupper($email),
        'password' => $currentPassword,
    ]);
    $loginData = thinkTankLoginSmokeData($login);
    thinkTankLoginSmokeAssert($login['status'] === 200, 'Password login did not return 200.');
    thinkTankLoginSmokeAssert(
        ($loginData['state'] ?? null) === 'MFA_REQUIRED'
            && ($loginData['next_action'] ?? null) === 'VERIFY_MFA'
            && ($loginData['user'] ?? null) === null,
        'Password login did not enter the private MFA_REQUIRED state.',
    );
    thinkTankLoginSmokeAssert(! $login['authenticated'], 'Password login authenticated the web guard before MFA.');

    $sent = Mail::sent(LoginOtpMail::class);
    thinkTankLoginSmokeAssert($sent->count() === 1, 'Password login did not fake exactly one OTP email.');
    /** @var LoginOtpMail $otpMail */
    $otpMail = $sent->first();
    $otpCode = $otpMail->otpCode;
    thinkTankLoginSmokeAssert(
        preg_match('/^\d{6}$/D', $otpCode) === 1 && $otpMail->hasTo($email),
        'The captured OTP mail was not addressed to the fixture account with a six-digit code.',
    );

    $challenge = UserLoginOtp::query()
        ->where('user_id', $createdUserId)
        ->whereNull('verified_at')
        ->sole();
    thinkTankLoginSmokeAssert(
        ! hash_equals($otpCode, (string) $challenge->getRawOriginal('otp_code')),
        'The OTP was stored in plaintext.',
    );

    $blockedPending = $browser->request('GET', '/api/v1/think-tank/me');
    thinkTankLoginSmokeAssert(
        $blockedPending['status'] === 401
            && ($blockedPending['json']['code'] ?? null) === 'UNAUTHENTICATED',
        '/me was not blocked while only the password factor was complete.',
    );

    $pendingSession = $browser->request('GET', '/api/v1/think-tank/auth/session');
    $pendingData = thinkTankLoginSmokeData($pendingSession);
    thinkTankLoginSmokeAssert(
        $pendingSession['status'] === 200
            && ($pendingData['state'] ?? null) === 'MFA_REQUIRED'
            && ($pendingData['next_action'] ?? null) === 'VERIFY_MFA'
            && ($pendingData['user'] ?? null) === null
            && is_array($pendingData['challenge'] ?? null)
            && ($pendingData['challenge']['sent'] ?? false) === true
            && ! array_key_exists('local_code', $pendingData['challenge']),
        'The session did not retain only safe pending MFA challenge metadata.',
    );
    thinkTankLoginSmokeAssert(! $pendingSession['authenticated'], 'The pending session resolved an authenticated guard.');

    $invalidCode = $otpCode === '000000' ? '000001' : '000000';
    $invalidOtp = $browser->request('POST', '/api/v1/think-tank/auth/mfa/verify', [
        'code' => $invalidCode,
    ]);
    thinkTankLoginSmokeAssert(
        $invalidOtp['status'] === 422
            && ($invalidOtp['json']['code'] ?? null) === 'MFA_CODE_INVALID'
            && ! $invalidOtp['authenticated'],
        'An invalid OTP was not rejected while preserving the unauthenticated guard.',
    );

    $verified = $browser->request('POST', '/api/v1/think-tank/auth/mfa/verify', [
        'code' => $otpCode,
    ]);
    $verifiedData = thinkTankLoginSmokeData($verified);
    thinkTankLoginSmokeAssert(
        $verified['status'] === 200
            && ($verifiedData['state'] ?? null) === 'PASSWORD_CHANGE_REQUIRED'
            && ($verifiedData['next_action'] ?? null) === 'CHANGE_PASSWORD'
            && ($verifiedData['user'] ?? null) === null
            && $verified['authenticated']
            && hash_equals($createdUserId, (string) $verified['auth_id']),
        'Valid OTP did not authenticate into PASSWORD_CHANGE_REQUIRED.',
    );
    thinkTankLoginSmokeAssert(
        UserLoginOtp::query()->whereKey($challenge->getKey())->whereNotNull('verified_at')->exists(),
        'The valid OTP was not atomically marked as consumed.',
    );

    $blockedForPassword = $browser->request('GET', '/api/v1/think-tank/me');
    thinkTankLoginSmokeAssert(
        $blockedForPassword['status'] === 409
            && ($blockedForPassword['json']['code'] ?? null) === 'PASSWORD_CHANGE_REQUIRED',
        '/me was not blocked until the mandatory password change completed.',
    );

    $replayed = $browser->request('POST', '/api/v1/think-tank/auth/mfa/verify', [
        'code' => $otpCode,
    ]);
    thinkTankLoginSmokeAssert(
        $replayed['status'] === 409
            && ($replayed['json']['code'] ?? null) === 'MFA_NOT_REQUIRED',
        'A consumed OTP was accepted for a second MFA transition.',
    );

    $changed = $browser->request('PUT', '/api/v1/think-tank/auth/password', [
        'current_password' => $currentPassword,
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ]);
    $changedData = thinkTankLoginSmokeData($changed);
    thinkTankLoginSmokeAssert(
        $changed['status'] === 200
            && ($changedData['state'] ?? null) === 'READY'
            && ($changedData['next_action'] ?? null) === 'NONE'
            && is_array($changedData['user'] ?? null)
            && ($changedData['user']['id'] ?? null) === $createdUserId,
        'Password update did not transition the verified session to READY.',
    );

    $readySession = $browser->request('GET', '/api/v1/think-tank/auth/session');
    $readySessionData = thinkTankLoginSmokeData($readySession);
    thinkTankLoginSmokeAssert(
        $readySession['status'] === 200
            && ($readySessionData['state'] ?? null) === 'READY'
            && ($readySessionData['user']['id'] ?? null) === $createdUserId,
        'The regenerated session did not retain the READY authentication state.',
    );

    $me = $browser->request('GET', '/api/v1/think-tank/me');
    $meData = thinkTankLoginSmokeData($me);
    thinkTankLoginSmokeAssert(
        $me['status'] === 200
            && ($meData['id'] ?? null) === $createdUserId
            && ($meData['email'] ?? null) === $email,
        '/me did not return the authenticated fixture after the complete ceremony.',
    );

    $user->refresh();
    thinkTankLoginSmokeAssert(
        $user->must_change_password === false
            && Hash::check($newPassword, $user->getAuthPassword())
            && ! Hash::check($currentPassword, $user->getAuthPassword()),
        'The mandatory password change was not persisted correctly.',
    );
    thinkTankLoginSmokeAssert(
        Mail::sent(LoginOtpMail::class)->count() === 1,
        'The ceremony unexpectedly emitted more than one fake OTP email.',
    );

    echo "THINK_TANK_API_LOGIN_CEREMONY_PG_OK csrf=419 pending=MFA_REQUIRED otp=verified password=READY me=200 mail=fake\n";
} finally {
    while ($connection->transactionLevel() > 0) {
        $connection->rollBack();
    }

    Auth::forgetGuards();
    $rolledBack = true;
}

thinkTankLoginSmokeAssert($rolledBack, 'The PostgreSQL fixture transaction did not roll back.');
thinkTankLoginSmokeAssert(
    $createdUserId === null || ! User::query()->whereKey($createdUserId)->exists(),
    'The PostgreSQL smoke fixture remained after rollback.',
);
