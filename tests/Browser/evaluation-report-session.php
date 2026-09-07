<?php

// Test-only local HTTP authentication. Creates one temporary session, never accounts or permissions.
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Cookie\Middleware\EncryptCookies;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! app()->environment('local', 'testing')) {
    throw new RuntimeException('Evaluation browser sessions are limited to local/testing environments.');
}

$mode = $argv[1] ?? '';
$statePath = $argv[2] ?? '';
$access = $argv[3] ?? 'admin';
if (! in_array($access, ['admin', 'viewer'], true)) throw new InvalidArgumentException('Choose admin or viewer access.');
$session = $app['session.store'];

if ($mode === 'destroy') {
    if (is_file($statePath)) {
        $state = json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR);
        $session->getHandler()->destroy($state['session_id']);
        unlink($statePath);
    }
    echo "EVALUATION_BROWSER_SESSION_REMOVED\n";
    exit;
}

$users = User::with(['role.permissions', 'permissions'])->where('is_disabled', false)->get();
$eligible = static fn (User $user): bool => ! $user->isAdministrativeAssistant() && ! $user->isFundingPartner()
    && ! $user->hasActiveLoginBlock()
    && ($user->isSuperAdmin() || (! $user->mustChangePassword() && ! $user->isPasswordExpired()))
    && $user->hasPermission('evaluations.view_all');
$actors = [
    'admin' => $users->first(fn (User $user) => $eligible($user) && ($user->isAdmin() || $user->isSuperAdmin()) && $user->hasPermission('evaluations.manage')),
    'viewer' => $users->first(fn (User $user) => $eligible($user) && ! $user->hasPermission('evaluations.manage')),
];
if ($mode === 'inspect') {
    echo json_encode(array_map(fn ($actor) => $actor !== null, $actors), JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
if ($mode === 'reports') {
    $actor = $actors['admin'];
    if (! $actor) throw new RuntimeException('No eligible existing administrator.');
    Illuminate\Support\Facades\Auth::setUser($actor);
    $request = Illuminate\Http\Request::create('/reports/evaluations/consolidated');
    $request->setUserResolver(fn () => $actor);
    $app->instance('request', $request);
    $procurements = App\Models\Procurement::whereIn('id', App\Models\EvaluationSubmission::whereNotNull('submitted_at')->select('procurement_id')->distinct())->get();
    $reports = [];
    foreach ($procurements as $procurement) {
        $panel = app(App\Services\EvaluationReportReworkPanel::class)->forProcurements([$procurement]);
        $reports[] = ['title' => $procurement->title, 'path' => route('reports.evaluations.procurement', $procurement, false),
            'available' => $panel['available_count'], 'pending' => $panel['pending_count'],
            'restrictions' => collect($panel['groups'])->flatMap(fn ($group) => $group['entries'])->pluck('blocking_reason')->filter()->unique()->values()->all()];
    }
    echo json_encode($reports, JSON_THROW_ON_ERROR).PHP_EOL;
    exit;
}
if ($mode !== 'create' || $statePath === '') throw new InvalidArgumentException('Use create/destroy with a private temporary state path, inspect, or reports.');
if (is_file($statePath)) throw new RuntimeException('A session-state file already exists; destroy its session first.');
if (in_array(config('session.driver'), ['array', 'cookie'], true)) throw new RuntimeException('Live HTTP checks need a persistent server-side session driver.');
$actor = $actors[$access];
if (! $actor) throw new RuntimeException('No eligible existing '.$access.' account; no account state was changed.');

$session->start();
$session->put($app['auth']->guard('web')->getName(), $actor->getAuthIdentifier());
$session->put('password_hash_web', $actor->getAuthPassword());
$session->put('otp_verified', true);
$session->put('otp_verified_user_id', (string) $actor->id);
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
    'access' => $access,
], JSON_THROW_ON_ERROR));
echo "EVALUATION_BROWSER_SESSION_CREATED\n";
