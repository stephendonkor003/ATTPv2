<?php

use App\Exceptions\ApiSyncException;
use App\Services\ApiSync\ApiSyncV2EndpointGuard;
use App\Services\ApiSync\ApiSyncV2SignatureService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

it('verifies canonical RSA SHA-256 invitations with a pinned 3072-bit key', function () {
    Container::getInstance()->instance('config', new Repository);
    $configCandidates = array_filter([
        getenv('OPENSSL_CONF') ?: null,
        '/etc/ssl/openssl.cnf',
        ...glob('C:/laragon/bin/apache/*/conf/openssl.cnf'),
        ...glob('C:/laragon/bin/php/*/extras/ssl/openssl.cnf'),
    ]);
    $opensslConfig = collect($configCandidates)->first(fn (string $path): bool => is_file($path));
    $privateKey = openssl_pkey_new(array_filter([
        'private_key_bits' => 3072,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'config' => $opensslConfig,
    ]));
    expect($privateKey)->not->toBeFalse();
    $details = openssl_pkey_get_details($privateKey);
    $publicPem = (string) $details['key'];

    config()->set('api_sync.enabled', true);
    config()->set('api_sync.v2.enabled', true);
    config()->set('api_sync.v2.maximum_clock_skew_seconds', 300);
    config()->set('api_sync.v2.central.key_id', 'aupremis-test-rsa-01');
    config()->set('api_sync.v2.central.public_key_pem', $publicPem);
    config()->set('api_sync.v2.central.public_key_path', null);
    config()->set('api_sync.v2.central.public_key_sha256', hash('sha256', trim($publicPem)."\n"));

    $service = new ApiSyncV2SignatureService;
    $invitationId = 'd9c65f64-1324-4ac2-bdd3-b027c8e656c9';
    $nonce = '7f46aa28-5f28-47e6-89b2-9a3df3d31c1d';
    $timestamp = (string) now()->timestamp;
    $payload = [
        'target_origin' => 'https://project.example.org',
        'protocol_version' => '2.0',
        'invitation_id' => $invitationId,
        'requested_datasets' => ['projects', 'activities'],
    ];
    $hash = hash('sha256', $service->canonicalJson($payload));
    $canonical = implode("\n", ['POST', '/api/sync/v2/invitations', $timestamp, $nonce, $invitationId, $hash]);
    openssl_sign($canonical, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    $request = Request::create('/api/sync/v2/invitations', 'POST', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_AUPREMIS_KEY_ID' => 'aupremis-test-rsa-01',
        'HTTP_X_AUPREMIS_TIMESTAMP' => $timestamp,
        'HTTP_X_AUPREMIS_NONCE' => $nonce,
        'HTTP_X_AUPREMIS_REQUEST_ID' => $invitationId,
        'HTTP_X_AUPREMIS_SIGNATURE' => base64_encode($signature),
    ], $service->canonicalJson($payload));

    expect($service->verifyRequest($request, $payload))
        ->toMatchArray(['nonce' => $nonce, 'request_id' => $invitationId, 'payload_hash' => $hash]);
    expect(fn () => $service->verifyRequest($request, [...$payload, 'target_origin' => 'https://evil.example']))
        ->toThrow(ApiSyncException::class, 'signature is invalid');
});

it('requires an explicit IP allowlist even when private central networking is enabled', function () {
    $application = new Application(dirname(__DIR__, 2));
    $application->instance('env', 'testing');
    $application->instance('config', new Repository([
        'api_sync' => ['v2' => ['central' => [
            'allow_private_networks' => true,
            'allowed_ips' => [],
        ]]],
    ]));
    Container::setInstance($application);
    $guard = new ApiSyncV2EndpointGuard;

    expect(fn () => $guard->httpOptions('http://127.0.0.1:8000'))
        ->toThrow(ApiSyncException::class, 'disallowed network address');

    config()->set('api_sync.v2.central.allowed_ips', ['127.0.0.1']);
    expect($guard->httpOptions('http://127.0.0.1:8000'))
        ->toHaveKey('allow_redirects', false)
        ->toHaveKey('curl');
});

it('adds a digest-only replay-safe inbound invitation schema', function () {
    $root = dirname(__DIR__, 2);
    $migration = file_get_contents($root.'/database/migrations/2026_08_25_000005_create_inbound_api_sync_invitations.php');
    $model = file_get_contents($root.'/app/Models/ApiSyncInvitation.php');

    expect($migration)
        ->toContain("Schema::create('api_sync_invitations'")
        ->toContain("char('credential_digest', 64)->unique()")
        ->toContain("uuid('invitation_nonce')->unique()")
        ->toContain("uuid('activation_request_id')->nullable()->unique()")
        ->toContain("Schema::create('api_sync_v2_nonces'")
        ->toContain("foreignUuid('inbound_invitation_id')")
        ->not->toContain("string('code'")
        ->not->toContain("text('code'");
    expect($model)
        ->toContain("'confirmation_receipt' => 'encrypted:array'")
        ->toContain("'credential_digest'")
        ->toContain('STATUS_APPROVAL_IN_PROGRESS')
        ->toContain('STATUS_ACTIVATION_PENDING')
        ->toContain('STATUS_ACTIVATION_RECEIVED');
});

it('uses the full payload nonce and central binding after a concurrent invitation insert', function () {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/ApiSync/ApiSyncInvitationService.php');
    $catch = str($service)->after('} catch (QueryException $exception) {')->before("throw new ApiSyncException('signed_nonce_reused'")->toString();

    expect($catch)
        ->toContain("hash_equals((string) \$duplicate->invitation_payload_hash, \$signature['payload_hash'])")
        ->toContain("hash_equals(strtolower((string) \$duplicate->invitation_nonce), \$signature['nonce'])")
        ->toContain("hash_equals((string) \$duplicate->central_instance_id, (string) \$validated['central_instance_id'])")
        ->toContain('if ($sameRequest)');
});

it('makes the signed inbound handshake primary while preserving v1 compatibility', function () {
    $root = dirname(__DIR__, 2);
    $routes = file_get_contents($root.'/routes/api.php');
    $webRoutes = file_get_contents($root.'/routes/web.php');
    $middleware = file_get_contents($root.'/app/Http/Middleware/AuthenticateApiSyncV2.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/Api/ApiSyncInvitationController.php');
    $view = file_get_contents($root.'/resources/views/system/api-sync/index.blade.php');

    expect($routes)
        ->toContain("prefix('sync/v1')")
        ->toContain("prefix('sync/v2')")
        ->toContain("'/invitations/{invitation}/activate'")
        ->toContain("'/invitations/{invitation}/finalize'")
        ->toContain("['api.sync.v2', 'throttle:180,1,api-sync-v2-data']")
        ->toContain("'/manifest'")
        ->toContain("'/datasets/{dataset}'")
        ->toContain("'/complete'");
    expect($webRoutes)
        ->toContain('permission:api_sync.invitations.approve')
        ->toContain('permission:api_sync.invitations.decline')
        ->toContain('permission:api_sync.invitations.revoke');
    expect($middleware)
        ->toContain("whereNotNull('inbound_invitation_id')")
        ->toContain("hash('sha256', \$token)")
        ->toContain("header('X-Consumer-Instance')")
        ->toContain('STATUS_ACTIVATION_RECEIVED');
    expect($controller)
        ->toContain('permitsDataset($dataset)')
        ->toContain("in_array('records.read'")
        ->toContain("'document_transfer'")
        ->toContain("'credential_revoked' => true");
    expect($view)
        ->toContain('Incoming AU-PReMIS synchronization')
        ->toContain('Seven-digit code from AU-PReMIS')
        ->toContain('Continue working')
        ->toContain('Legacy locally generated pairings');
});

it('never persists flashes or audits the seven-digit central code', function () {
    $root = dirname(__DIR__, 2);
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncInvitationService.php');
    $webController = file_get_contents($root.'/app/Http/Controllers/System/ApiSyncController.php');
    $requestAudit = file_get_contents($root.'/app/Http/Middleware/SystemAuditLogger.php');
    $audit = file_get_contents($root.'/app/Services/ApiSync/ApiSyncInvitationAuditService.php');

    expect($service)
        ->toContain('#[\\SensitiveParameter] string $code')
        ->toContain("'code' => \$code")
        ->not->toContain("'code_hash' => hash_hmac")
        ->not->toContain("'confirmation_receipt' => \$body");
    expect($webController)
        ->toContain("request->request->remove('authorization_code')")
        ->not->toContain('withInput(');
    expect($requestAudit)
        ->toContain("'authorization_code'")
        ->toContain("'pairing_code'")
        ->toContain("'code'");
    expect($audit)
        ->toContain("'authorization_code'")
        ->toContain("'[REDACTED]'");
});

it('binds activation to exact origins scope credential and asynchronous immutable snapshots', function () {
    $root = dirname(__DIR__, 2);
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncInvitationService.php');
    $guard = file_get_contents($root.'/app/Services/ApiSync/ApiSyncV2EndpointGuard.php');
    $config = file_get_contents($root.'/config/api_sync.php');
    $documentation = file_get_contents($root.'/docs/API_SYNC_PROVIDER.md');

    expect($service)
        ->toContain('hash_equals((string) $invitation->credential_digest, $digest)')
        ->toContain('STATUS_APPROVAL_IN_PROGRESS')
        ->toContain('STATUS_ACTIVATION_PENDING')
        ->toContain("'token_expires_at' => \$locked->credential_expires_at")
        ->toContain('$this->snapshots->initialize($pairing)')
        ->toContain('$this->snapshots->dispatch($pairing)')
        ->toContain("'credential_expires_at'")
        ->toContain("'confirmation_receipt' => \$receipt");
    expect($guard)
        ->toContain("'/api/v2/portfolio-sync/invitations/'")
        ->toContain("'allow_redirects' => false")
        ->toContain('CURLOPT_RESOLVE')
        ->toContain('FILTER_FLAG_NO_PRIV_RANGE')
        ->toContain('(! $allowPrivate || ! in_array(strtolower($address), $allowedIps, true))')
        ->toContain("environment('production') && ! defined('CURLOPT_RESOLVE')");
    expect($config)
        ->toContain("'allowed_datasets'")
        ->toContain("'records.read'")
        ->toContain("'documents.metadata.read'")
        ->toContain("'documents.content.read'")
        ->toContain("'enabled' => (bool) env('ATTP_API_SYNC_V2_DOCUMENTS_ENABLED'");
    expect($documentation)
        ->toContain('POST /api/sync/v2/invitations')
        ->toContain('signed activation authorization')
        ->toContain('single-worker PHP built-in server')
        ->toContain('POST /api/sync/v2/complete');
});
