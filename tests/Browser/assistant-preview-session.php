<?php

// Local browser regression helper: creates and destroys only a temporary session.
// Never changes user credentials, account state, or procurement records.
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Cookie\Middleware\EncryptCookies;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! app()->environment('local', 'testing')) {
    throw new RuntimeException('Browser session fixtures are limited to local/testing environments.');
}

$mode = $argv[1] ?? '';
$statePath = $argv[2] ?? '';
if ($statePath === '') throw new InvalidArgumentException('A private temporary session-state path is required.');
$session = $app['session.store'];

if ($mode === 'destroy') {
    if (is_file($statePath)) {
        $state = json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
        $session->getHandler()->destroy($state['session_id']);
        unlink($statePath);
    }
    echo "TEMPORARY_BROWSER_SESSION_REMOVED\n";
    exit;
}

if ($mode !== 'create') throw new InvalidArgumentException('Use create or destroy.');
if (is_file($statePath)) throw new RuntimeException('Session-state file already exists; remove its session first.');
if (in_array(config('session.driver'), ['array', 'cookie'], true)) {
    throw new RuntimeException('Live browser checks need a persistent server-side session driver.');
}

$assistant = User::with('role')->where('is_disabled', false)
    ->whereHas('role', fn ($query) => $query->whereIn('name', User::ADMINISTRATIVE_ASSISTANT_ROLES))
    ->get()->first(fn (User $user) => ! $user->mustChangePassword() && ! $user->isPasswordExpired());
if (! $assistant) throw new RuntimeException('No active assistant with current credentials is available; no user state was changed.');

$session->start();
$session->put($app['auth']->guard('web')->getName(), $assistant->getAuthIdentifier());
$session->put('password_hash_web', $assistant->getAuthPassword());
$session->put('otp_verified', true);
$session->put('otp_verified_user_id', (string) $assistant->id);
$session->put('otp_verified_at', now()->toIso8601String());
$session->save();
$cookieName = config('session.cookie');
$encrypter = $app['encrypter'];
$cookieValue = $encrypter->encrypt(
    CookieValuePrefix::create($cookieName, $encrypter->getKey()).$session->getId(),
    EncryptCookies::serialized($cookieName),
);
file_put_contents($statePath, json_encode([
    'session_id' => $session->getId(),
    'cookie' => ['name' => $cookieName, 'value' => $cookieValue],
], JSON_THROW_ON_ERROR));
echo "TEMPORARY_BROWSER_SESSION_CREATED\n";
