<?php

use App\Mail\DiscussionParticipantPasswordResetMail;
use App\Models\DiscussionParticipant;
use App\Models\DiscussionParticipantPasswordReset;
use App\Services\DiscussionParticipantTokenService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$kernel = $app->make(HttpKernel::class);
$smokeIpSeed = random_int(1, 200);
$requestNumber = 0;

$requestJson = function (
    string $method,
    string $uri,
    array $payload = [],
    array $cookies = []
) use ($kernel, $smokeIpSeed, &$requestNumber): array {
    $requestNumber++;
    $smokeIp = '198.51.100.'.(($smokeIpSeed + $requestNumber) % 254 + 1);
    $request = Request::create(
        $uri,
        $method,
        [],
        $cookies,
        [],
        [
            'HTTP_HOST' => '127.0.0.1:8000',
            'SERVER_PORT' => 8000,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_USER_AGENT' => 'ATTP Participant Password Reset Smoke/1.0',
            'REMOTE_ADDR' => $smokeIp,
        ],
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

$resetPayloadFromMail = function (DiscussionParticipantPasswordResetMail $mail): array {
    $query = parse_url($mail->resetUrl, PHP_URL_QUERY);
    parse_str(is_string($query) ? $query : '', $parameters);

    if (($parameters['password_reset'] ?? null) !== '1'
        || ! is_string($parameters['email'] ?? null)
        || ! is_string($parameters['token'] ?? null)) {
        throw new RuntimeException('The participant reset email did not contain the expected Join-page reset URL.');
    }

    return $parameters;
};

$findDeviceCookie = function ($response): ?Cookie {
    foreach ($response->headers->getCookies() as $cookie) {
        if ($cookie->getName() === DiscussionParticipantTokenService::COOKIE_NAME) {
            return $cookie;
        }
    }

    return null;
};

Mail::fake();
DB::beginTransaction();

try {
    $email = 'participant-reset-'.Str::lower(Str::random(12)).'@example.test';
    $oldPassword = 'OldPassword2026';
    $newPassword = 'NewPassword2026';
    $participant = DiscussionParticipant::query()->create([
        'display_name' => 'Password Reset Smoke',
        'email' => $email,
        'password' => $oldPassword,
        'status' => 'active',
        'terms_accepted_at' => now(),
    ]);

    $device = $app->make(DiscussionParticipantTokenService::class)
        ->issue($participant, 'Password reset smoke browser');

    [$unknownResponse, $unknownBody] = $requestJson(
        'POST',
        '/api/discussions/participants/password/forgot',
        ['email' => 'unknown-'.Str::lower(Str::random(10)).'@example.test']
    );
    $assertStatus($unknownResponse, 200, 'Unknown participant forgot-password request');
    Mail::assertNothingSent();

    [$firstForgotResponse, $firstForgotBody] = $requestJson(
        'POST',
        '/api/discussions/participants/password/forgot',
        ['email' => Str::upper($email)]
    );
    $assertStatus($firstForgotResponse, 200, 'Known participant forgot-password request');

    if ($unknownBody !== $firstForgotBody) {
        throw new RuntimeException('Forgot-password responses disclose whether a participant email exists.');
    }

    $firstMail = Mail::sent(DiscussionParticipantPasswordResetMail::class)->last();
    if (! $firstMail instanceof DiscussionParticipantPasswordResetMail || ! $firstMail->hasTo($email)) {
        throw new RuntimeException('The reset email was not addressed to the discussion participant.');
    }

    $firstResetPayload = $resetPayloadFromMail($firstMail);
    $firstReset = DiscussionParticipantPasswordReset::query()
        ->where('participant_id', $participant->id)
        ->firstOrFail();

    if ($firstReset->token_hash !== hash('sha256', $firstResetPayload['token'])
        || $firstReset->token_hash === $firstResetPayload['token']
        || strlen($firstReset->token_hash) !== 64
        || $firstReset->used_at
        || $firstReset->expires_at->lt(now()->addMinutes(59))) {
        throw new RuntimeException('The participant reset credential was not securely stored with the expected expiry.');
    }

    // Issuing another link must invalidate the older link for this participant.
    [$secondForgotResponse] = $requestJson(
        'POST',
        '/api/discussions/participants/password/forgot',
        ['email' => $email]
    );
    $assertStatus($secondForgotResponse, 200, 'Replacement participant reset link');
    $secondMail = Mail::sent(DiscussionParticipantPasswordResetMail::class)->last();
    $secondResetPayload = $resetPayloadFromMail($secondMail);

    [$replacedResponse] = $requestJson(
        'POST',
        '/api/discussions/participants/password/reset',
        [
            'email' => $email,
            'token' => $firstResetPayload['token'],
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]
    );
    $assertStatus($replacedResponse, 422, 'Superseded participant reset link');

    // Expired credentials must fail with the same public reset error.
    DiscussionParticipantPasswordReset::query()
        ->where('participant_id', $participant->id)
        ->update(['expires_at' => now()->subMinute()]);
    [$expiredResponse, $expiredBody] = $requestJson(
        'POST',
        '/api/discussions/participants/password/reset',
        [
            'email' => $email,
            'token' => $secondResetPayload['token'],
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]
    );
    $assertStatus($expiredResponse, 422, 'Expired participant reset link');

    if (! isset($expiredBody['errors']['token'])) {
        throw new RuntimeException('An invalid reset link did not return a token-scoped validation error.');
    }

    [$thirdForgotResponse] = $requestJson(
        'POST',
        '/api/discussions/participants/password/forgot',
        ['email' => $email]
    );
    $assertStatus($thirdForgotResponse, 200, 'Fresh participant reset link');
    $thirdMail = Mail::sent(DiscussionParticipantPasswordResetMail::class)->last();
    $thirdResetPayload = $resetPayloadFromMail($thirdMail);

    [$resetResponse] = $requestJson(
        'POST',
        '/api/discussions/participants/password/reset',
        [
            'email' => $email,
            'token' => $thirdResetPayload['token'],
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ],
        [DiscussionParticipantTokenService::COOKIE_NAME => $device['plain_text_token']]
    );
    $assertStatus($resetResponse, 200, 'Valid participant password reset');

    $participant->refresh();
    $usedReset = DiscussionParticipantPasswordReset::query()
        ->where('participant_id', $participant->id)
        ->firstOrFail();
    $expiredDeviceCookie = $findDeviceCookie($resetResponse);

    if (! Hash::check($newPassword, $participant->password)
        || Hash::check($oldPassword, $participant->password)
        || ! $usedReset->used_at
        || $participant->tokens()->exists()
        || ! $expiredDeviceCookie
        || $expiredDeviceCookie->getExpiresTime() >= time()) {
        throw new RuntimeException('A successful password reset did not update the password and revoke remembered devices.');
    }

    [$reusedResponse] = $requestJson(
        'POST',
        '/api/discussions/participants/password/reset',
        [
            'email' => $email,
            'token' => $thirdResetPayload['token'],
            'password' => 'AnotherPassword2026',
            'password_confirmation' => 'AnotherPassword2026',
        ]
    );
    $assertStatus($reusedResponse, 422, 'Reused participant reset link');

    [$oldLoginResponse] = $requestJson('POST', '/api/discussions/participants/login', [
        'email' => $email,
        'password' => $oldPassword,
    ]);
    $assertStatus($oldLoginResponse, 422, 'Old participant password after reset');

    [$newLoginResponse] = $requestJson('POST', '/api/discussions/participants/login', [
        'email' => $email,
        'password' => $newPassword,
    ]);
    $assertStatus($newLoginResponse, 200, 'New participant password after reset');

    Mail::assertSent(DiscussionParticipantPasswordResetMail::class, 3);

    echo "DISCUSSION_PARTICIPANT_PASSWORD_RESET_OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
} finally {
    DB::rollBack();
}
