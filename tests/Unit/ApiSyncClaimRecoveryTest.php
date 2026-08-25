<?php

use App\Http\Requests\ApiSyncAbandonRequest;
use App\Http\Requests\ApiSyncClaimRequest;

it('stores only a recovery digest and keeps it out of generic model serialization', function () {
    $root = dirname(__DIR__, 2);
    $migration = file_get_contents($root.'/database/migrations/2026_08_25_000003_add_claim_recovery_to_api_sync_pairings.php');
    $model = file_get_contents($root.'/app/Models/ApiSyncPairing.php');
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncPairingService.php');
    $provider = file_get_contents($root.'/app/Providers/AppServiceProvider.php');

    expect($migration)
        ->toContain("char('claim_recovery_hash', 64)")
        ->toContain("timestamp('abandoned_at')")
        ->not->toContain('claim_recovery_key');
    expect($model)
        ->toContain("'claim_recovery_hash'")
        ->toContain("'abandoned_at' => 'immutable_datetime'")
        ->toContain("STATUS_ABANDONED = 'abandoned'");
    expect($service)
        ->toContain('#[\SensitiveParameter] string $recoveryKey')
        ->toContain("hash('sha256', \$recoveryKey)")
        ->toContain("'claim_recovery_hash' => \$recoveryHash")
        ->not->toContain("'claim_recovery_key' =>");
    expect($provider)
        ->toContain("'claim_recovery_hash'")
        ->toContain("'recovery_key'");
});

it('requires a high entropy recovery header on claims and abandonments', function () {
    $root = dirname(__DIR__, 2);
    $claim = file_get_contents($root.'/app/Http/Requests/ApiSyncClaimRequest.php');
    $abandon = file_get_contents($root.'/app/Http/Requests/ApiSyncAbandonRequest.php');

    expect($claim)
        ->toContain('public function validationData(): array')
        ->toContain("header('X-Claim-Recovery-Key')")
        ->toContain("'min:43'")
        ->toContain("'max:128'")
        ->toContain("'regex:/^[A-Za-z0-9_-]+$/'");
    expect($abandon)
        ->toContain('public function validationData(): array')
        ->toContain("header('X-Claim-Recovery-Key')")
        ->toContain("header('X-Consumer-Instance')")
        ->toContain("header('Idempotency-Key')")
        ->toContain("'idempotency_key' => ['nullable', 'uuid']");
});

it('validates recovery headers without copying them into request input', function () {
    $recoveryKey = str_repeat('A', 43);
    $server = [
        'HTTP_X_CLAIM_RECOVERY_KEY' => $recoveryKey,
        'HTTP_X_CONSUMER_INSTANCE' => 'aupremis-test-01',
        'HTTP_IDEMPOTENCY_KEY' => '946f744d-29a3-4cd4-aa70-0730741033d5',
    ];
    $claim = ApiSyncClaimRequest::create('/api/sync/v1/pairings/claim', 'POST', [
        'code' => '1234567',
        'consumer_instance' => 'aupremis-test-01',
        'consumer_name' => 'AU-PReMIS Test',
    ], [], [], $server);
    $abandon = ApiSyncAbandonRequest::create('/api/sync/v1/pairings/abandon', 'POST', [], [], [], $server);

    expect($claim->validationData()['recovery_key'])->toBe($recoveryKey)
        ->and($claim->all())->not->toHaveKey('recovery_key')
        ->and($abandon->validationData()['recovery_key'])->toBe($recoveryKey)
        ->and($abandon->all())->not->toHaveKey('recovery_key');
});

it('row-locks and idempotently revokes only the matching claimed session', function () {
    $root = dirname(__DIR__, 2);
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncPairingService.php');
    preg_match('/public function abandon\(.*?\n    public function authenticate/s', $service, $method);

    expect($method[0] ?? '')
        ->toContain("where('claim_recovery_hash', \$recoveryHash)")
        ->toContain('lockForUpdate()')
        ->toContain('hash_equals((string) $pairing->claim_recovery_hash, $recoveryHash)')
        ->toContain('hash_equals((string) $pairing->consumer_instance, $consumerInstance)')
        ->toContain('STATUS_CLAIMED')
        ->toContain('STATUS_ABANDONED')
        ->toContain("'token_hash' => null")
        ->toContain('sync_session_abandoned')
        ->toContain('sync_session_abandonment_replayed')
        ->toContain('pairing_abandonment_unavailable');
});

it('exposes a separately throttled non-bearer abandon route with a secret-free response', function () {
    $root = dirname(__DIR__, 2);
    $routes = file_get_contents($root.'/routes/api.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/Api/ApiSyncController.php');
    preg_match('/public function abandon\(.*?\n    private function pairing/s', $controller, $method);

    expect($routes)
        ->toContain("Route::post('/pairings/abandon'")
        ->toContain('throttle:5,1,api-sync-abandon')
        ->and(strpos($routes, "Route::post('/pairings/abandon'"))
        ->toBeLessThan(strpos($routes, "Route::middleware(['api.sync'"));
    expect($method[0] ?? '')
        ->toContain("'status' => 'abandoned'")
        ->toContain("'credential_revoked' => true")
        ->toContain("'Cache-Control' => 'no-store, private'")
        ->not->toContain('access_token')
        ->not->toContain('snapshot');
});

it('documents ambiguous-claim abandonment without suggesting claim retries', function () {
    $documentation = file_get_contents(dirname(__DIR__, 2).'/docs/API_SYNC_PROVIDER.md');

    expect($documentation)
        ->toContain('POST /api/sync/v1/pairings/abandon')
        ->toContain('X-Claim-Recovery-Key')
        ->toContain('{"data":{"status":"abandoned","credential_revoked":true}}')
        ->toContain('never contains a bearer credential, snapshot identifier or dataset metadata')
        ->toContain('A `409` does **not** prove that no session exists')
        ->toContain('hard 24-hour maximum session lifetime')
        ->toContain('Abandonment is also safe to retry')
        ->toContain('AU-PReMIS must not automatically retry the claim');
});

it('caps every recovery uncertainty window at one day', function () {
    $root = dirname(__DIR__, 2);
    $configuration = file_get_contents($root.'/config/api_sync.php');
    $example = file_get_contents($root.'/.env.api-sync.example');
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncPairingService.php');

    expect($configuration)
        ->toContain("'session_ttl_minutes' => min(1_440, max(30,")
        ->and($service)->toContain("min(1_440, max(30, (int) config('api_sync.session_ttl_minutes', 360)))")
        ->and($example)->toContain('Values above 1440 are capped at 24 hours.');
});
