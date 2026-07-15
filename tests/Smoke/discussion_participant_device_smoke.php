<?php

use App\Models\AuMemberState;
use App\Models\DiscussionParticipant;
use App\Models\DiscussionParticipantToken;
use App\Services\DiscussionParticipantTokenService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$kernel = $app->make(HttpKernel::class);
$smokeIp = '203.0.113.'.random_int(1, 254);

$requestJson = function (
    string $method,
    string $uri,
    array $payload = [],
    ?string $token = null,
    array $cookies = []
) use ($kernel, $smokeIp): array {
    $server = [
        'HTTP_HOST' => '127.0.0.1:8000',
        'SERVER_PORT' => 8000,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_USER_AGENT' => 'ATTP Remembered Device Smoke/1.0',
        'REMOTE_ADDR' => $smokeIp,
    ];

    if ($token) {
        $server['HTTP_AUTHORIZATION'] = "Bearer {$token}";
    }

    $request = Request::create(
        $uri,
        $method,
        [],
        $cookies,
        [],
        $server,
        $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR)
    );

    $response = $kernel->handle($request);
    $data = json_decode((string) $response->getContent(), true);
    $kernel->terminate($request, $response);

    return [$response, is_array($data) ? $data : []];
};

$assertStatus = function ($response, int $expected, string $context): void {
    if ($response->getStatusCode() !== $expected) {
        throw new RuntimeException(sprintf(
            "%s: expected HTTP %d, received %d.\n%s",
            $context,
            $expected,
            $response->getStatusCode(),
            (string) $response->getContent()
        ));
    }
};

$findCookie = function ($response): ?Cookie {
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getName() === DiscussionParticipantTokenService::COOKIE_NAME) {
            return $cookie;
        }
    }

    return null;
};

DB::beginTransaction();

try {
    $country = AuMemberState::query()->active()->value('name');
    if (! is_string($country) || $country === '') {
        throw new RuntimeException('No active AU member state is available for device-session testing.');
    }

    $email = 'remembered-device-'.Str::lower(Str::random(12)).'@example.test';
    $password = 'RememberMe2026';
    [$registerResponse, $registration] = $requestJson('POST', '/api/discussions/participants/register', [
        'display_name' => 'Remembered Device Test',
        'email' => $email,
        'country' => $country,
        'password' => $password,
        'password_confirmation' => $password,
        'terms' => true,
    ]);
    $assertStatus($registerResponse, 201, 'Remembered-device registration');

    $plainTextToken = $registration['token'] ?? null;
    if (! is_string($plainTextToken) || strlen($plainTextToken) < 40) {
        throw new RuntimeException('Registration did not return a strong opaque Bearer token.');
    }

    if (($registration['remembered_device'] ?? null) !== true) {
        throw new RuntimeException('Registration did not identify the browser as a remembered device.');
    }

    $participant = DiscussionParticipant::query()->where('email', $email)->firstOrFail();
    $storedToken = DiscussionParticipantToken::query()
        ->where('participant_id', $participant->id)
        ->where('token_hash', hash('sha256', $plainTextToken))
        ->firstOrFail();

    if ($storedToken->token_hash === $plainTextToken || strlen($storedToken->token_hash) !== 64) {
        throw new RuntimeException('The remembered-device credential was not stored as a SHA-256 hash.');
    }

    if (! $storedToken->last_used_at
        || ! $storedToken->expires_at
        || $storedToken->expires_at->lt(now()->addDays(364))) {
        throw new RuntimeException('The remembered-device credential was not issued with long-lived activity metadata.');
    }

    $rememberedCookie = $findCookie($registerResponse);
    if (! $rememberedCookie
        || $rememberedCookie->getValue() !== $plainTextToken
        || ! $rememberedCookie->isHttpOnly()
        || $rememberedCookie->getSameSite() !== Cookie::SAMESITE_STRICT
        || $rememberedCookie->getPath() !== '/api/discussions'
        || $rememberedCookie->getExpiresTime() < now()->addDays(364)->getTimestamp()) {
        throw new RuntimeException('Registration did not set the expected protected remembered-device cookie.');
    }

    // Bearer clients remain supported for integrations and non-browser consumers.
    [$bearerMeResponse, $bearerMe] = $requestJson(
        'GET',
        '/api/discussions/participants/me',
        [],
        $plainTextToken
    );
    $assertStatus($bearerMeResponse, 200, 'Bearer-compatible participant restoration');

    if (($bearerMe['participant']['id'] ?? null) !== $participant->id) {
        throw new RuntimeException('Bearer restoration returned the wrong participant.');
    }

    // A nearly expired legacy/device token should be renewed by authenticated use,
    // and the HttpOnly cookie must receive the matching new expiry.
    $storedToken->forceFill([
        'last_used_at' => now()->subMinutes(10),
        'expires_at' => now()->addDays(10),
    ])->saveQuietly();

    [$cookieMeResponse, $cookieMe] = $requestJson(
        'GET',
        '/api/discussions/participants/me',
        [],
        null,
        [DiscussionParticipantTokenService::COOKIE_NAME => $plainTextToken]
    );
    $assertStatus($cookieMeResponse, 200, 'Cookie-only participant restoration');

    $storedToken->refresh();
    $refreshedCookie = $findCookie($cookieMeResponse);
    if (($cookieMe['participant']['id'] ?? null) !== $participant->id
        || ($cookieMe['session']['remembered_device'] ?? null) !== true
        || $storedToken->expires_at->lt(now()->addDays(364))
        || ! $refreshedCookie
        || $refreshedCookie->getExpiresTime() < now()->addDays(364)->getTimestamp()) {
        throw new RuntimeException('Cookie-only restoration did not renew the remembered device session.');
    }

    [$logoutResponse] = $requestJson(
        'POST',
        '/api/discussions/participants/logout',
        [],
        null,
        [DiscussionParticipantTokenService::COOKIE_NAME => $plainTextToken]
    );
    $assertStatus($logoutResponse, 200, 'Remembered-device logout');

    $expiredCookie = $findCookie($logoutResponse);
    if (DiscussionParticipantToken::query()->whereKey($storedToken->id)->exists()
        || ! $expiredCookie
        || $expiredCookie->getExpiresTime() >= time()) {
        throw new RuntimeException('Logout did not revoke both the database token and remembered-device cookie.');
    }

    [$revokedResponse] = $requestJson(
        'GET',
        '/api/discussions/participants/me',
        [],
        null,
        [DiscussionParticipantTokenService::COOKIE_NAME => $plainTextToken]
    );
    $assertStatus($revokedResponse, 401, 'Revoked remembered-device credential');

    $clearedInvalidCookie = $findCookie($revokedResponse);
    if (! $clearedInvalidCookie || $clearedInvalidCookie->getExpiresTime() >= time()) {
        throw new RuntimeException('An invalid remembered-device cookie was not actively cleared.');
    }

    $tokenService = $app->make(DiscussionParticipantTokenService::class);
    $latestIssuedToken = null;
    foreach (range(1, 12) as $deviceNumber) {
        $latestIssuedToken = $tokenService->issue($participant, "Device {$deviceNumber}");
    }

    if ($participant->tokens()->where('expires_at', '>', now())->count() !== 10
        || ! $latestIssuedToken
        || ! DiscussionParticipantToken::query()->whereKey($latestIssuedToken['token']->id)->exists()) {
        throw new RuntimeException('Remembered-device token pruning did not retain the newest device within the safe cap.');
    }

    echo "DISCUSSION_PARTICIPANT_DEVICE_OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
} finally {
    DB::rollBack();
}
