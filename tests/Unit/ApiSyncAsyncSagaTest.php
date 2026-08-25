<?php

it('keeps every API sync surface fail closed unless the deployment explicitly enables it', function () {
    $root = dirname(__DIR__, 2);
    $core = file_get_contents($root.'/config/api_sync.php');
    $documents = file_get_contents($root.'/config/api_sync_documents.php');
    $example = file_get_contents($root.'/.env.api-sync.example');

    expect($core)
        ->toContain("env('ATTP_API_SYNC_ENABLED', false)")
        ->toContain("env('ATTP_API_SYNC_V2_ENABLED', false)")
        ->toContain("env('ATTP_API_SYNC_V2_DOCUMENTS_ENABLED', false)");
    expect($documents)->toContain("env('ATTP_API_SYNC_V2_DOCUMENTS_ENABLED', false)");
    expect($example)
        ->toContain('ATTP_API_SYNC_ENABLED=true')
        ->toContain('ATTP_API_SYNC_V2_ENABLED=true')
        ->toContain('ATTP_API_SYNC_V2_DOCUMENTS_ENABLED=true');
});

it('uses an additive authorization and finalization schema without storing the seven digit code', function () {
    $root = dirname(__DIR__, 2);
    $migration = file_get_contents($root.'/database/migrations/2026_08_25_000007_add_async_authorization_to_api_sync_invitations.php');
    $model = file_get_contents($root.'/app/Models/ApiSyncInvitation.php');

    expect($migration)
        ->toContain("uuid('authorization_id')->nullable()->unique()")
        ->toContain("text('authorization_receipt')->nullable()")
        ->toContain("timestamp('authorization_verified_at')->nullable()")
        ->toContain("string('terminal_error_code', 100)->nullable()")
        ->not->toContain('authorization_code')
        ->not->toContain('pull_credential');
    expect($model)
        ->toContain("STATUS_ACTIVATION_PENDING = 'activation_pending'")
        ->toContain("'authorization_receipt' => 'encrypted:array'")
        ->toContain("'terminal_error_code'");
});

it('adds PostgreSQL lifecycle checks for invitation and pairing secrets without blocking a bounded completion retry', function () {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_25_000009_enforce_api_sync_lifecycle_checks.php');

    expect($migration)
        ->toContain('api_sync_pairings_status_chk')
        ->toContain('api_sync_pairings_digest_chk')
        ->toContain('api_sync_pairings_lifecycle_chk')
        ->toContain("status <> 'completed' OR (completed_at IS NOT NULL AND (token_hash IS NULL OR token_expires_at > completed_at))")
        ->toContain('api_sync_invitations_status_chk')
        ->toContain('approval_attempts BETWEEN 0 AND 10')
        ->toContain('api_sync_invitations_auth_tuple_chk')
        ->toContain('api_sync_invitations_activation_tuple_chk')
        ->toContain('api_sync_invitations_receipt_tuple_chk')
        ->toContain('NOT VALID')
        ->toContain('VALIDATE CONSTRAINT');
});

it('returns approval promptly and permits signed bearer activation when the 202 response was lost', function () {
    $root = dirname(__DIR__, 2);
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncInvitationService.php');
    $routes = file_get_contents($root.'/routes/api.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/Api/ApiSyncInvitationController.php');

    expect($service)
        ->toContain(': ApiSyncInvitation::STATUS_ACTIVATION_PENDING,')
        ->toContain('$this->pairings->ensureAsyncQueue(in_array(')
        ->toContain('$approvalResponseWasLost = $locked->status === ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS')
        ->toContain('Str::isUuid((string) $locked->confirmation_request_id)')
        ->toContain('(! $signedAuthorizationStored && ! $approvalResponseWasLost)')
        ->toContain("'approval_response_recovered' => \$approvalResponseWasLost")
        ->toContain("'status' => ApiSyncInvitation::STATUS_ACTIVE")
        ->not->toContain("'code_digest'");
    expect($routes)
        ->toContain("'/invitations/{invitation}/activate'")
        ->toContain("'/invitations/{invitation}/finalize'");
    expect($controller)
        ->toContain('$this->invitations->finalize($invitation, $payload, $request)');
});

it('fails closed before v2 activation when snapshot work is not durably queued', function (string $driver, ?int $retryAfter) {
    $container = Illuminate\Container\Container::getInstance();
    $connection = [
        'driver' => $driver,
        'connection' => null,
    ];
    if ($retryAfter !== null) {
        $connection['retry_after'] = $retryAfter;
    }
    $container->instance('config', new Illuminate\Config\Repository([
        'api_sync' => [
            'session_ttl_minutes' => 360,
            'snapshot' => ['connection' => 'unsafe_v2_test', 'maximum_build_seconds' => 900],
        ],
        'database' => [
            'default' => 'pgsql',
            'connections' => ['pgsql' => ['driver' => 'pgsql']],
        ],
        'cache' => [
            'default' => 'shared_test_database',
            'stores' => ['shared_test_database' => [
                'driver' => 'database',
                'connection' => 'pgsql',
                'lock_connection' => 'pgsql',
                'table' => 'cache',
                'lock_table' => 'cache_locks',
            ]],
        ],
        'queue' => ['connections' => ['unsafe_v2_test' => $connection]],
    ]));

    $service = (new ReflectionClass(App\Services\ApiSync\ApiSyncPairingService::class))->newInstanceWithoutConstructor();

    expect(fn () => $service->ensureAsyncQueue())
        ->toThrow(App\Exceptions\ApiSyncException::class);
})->with([
    'synchronous driver' => ['sync', null],
    'discarding null driver' => ['null', null],
    'SQS without a visibility proof' => ['sqs', null],
    'database driver missing retry lease' => ['database', null],
    'unknown custom driver' => ['custom', 2_100],
    'retry window equal to the snapshot mutex' => ['database', 2_000],
]);

it('accepts only a dedicated database snapshot queue on the default PostgreSQL connection with a sufficient retry lease', function () {
    $container = Illuminate\Container\Container::getInstance();
    $container->instance('config', new Illuminate\Config\Repository([
        'api_sync' => [
            'session_ttl_minutes' => 360,
            'snapshot' => ['connection' => 'api_sync_database', 'maximum_build_seconds' => 900],
        ],
        'database' => [
            'default' => 'pgsql',
            'connections' => ['pgsql' => ['driver' => 'pgsql']],
        ],
        'cache' => [
            'default' => 'shared_test_database',
            'stores' => ['shared_test_database' => [
                'driver' => 'database',
                'connection' => 'pgsql',
                'lock_connection' => 'pgsql',
                'table' => 'cache',
                'lock_table' => 'cache_locks',
            ]],
        ],
        'queue' => ['connections' => ['api_sync_database' => [
            'driver' => 'database',
            'connection' => null,
            'retry_after' => 2_100,
        ]]],
    ]));

    $service = (new ReflectionClass(App\Services\ApiSync\ApiSyncPairingService::class))->newInstanceWithoutConstructor();

    expect(fn () => $service->ensureAsyncQueue())->not->toThrow(App\Exceptions\ApiSyncException::class);
});

it('rejects process-local or differently bound snapshot lock stores', function (array $store) {
    $container = Illuminate\Container\Container::getInstance();
    $container->instance('config', new Illuminate\Config\Repository([
        'api_sync' => [
            'session_ttl_minutes' => 360,
            'snapshot' => ['connection' => 'api_sync_database', 'maximum_build_seconds' => 900],
        ],
        'database' => [
            'default' => 'pgsql',
            'connections' => [
                'pgsql' => ['driver' => 'pgsql'],
                'reporting' => ['driver' => 'pgsql'],
            ],
            'redis' => [],
        ],
        'cache' => ['default' => 'unsafe_test', 'stores' => ['unsafe_test' => $store]],
        'queue' => ['connections' => ['api_sync_database' => [
            'driver' => 'database',
            'connection' => 'pgsql',
            'retry_after' => 2_100,
        ]]],
    ]));

    $service = (new ReflectionClass(App\Services\ApiSync\ApiSyncPairingService::class))->newInstanceWithoutConstructor();

    expect(fn () => $service->ensureAsyncQueue())
        ->toThrow(App\Exceptions\ApiSyncException::class);
})->with([
    'local file locks' => [['driver' => 'file']],
    'local array locks' => [['driver' => 'array']],
    'different database connection' => [[
        'driver' => 'database',
        'connection' => 'reporting',
        'lock_connection' => 'reporting',
        'table' => 'cache',
        'lock_table' => 'cache_locks',
    ]],
    'missing Redis connection' => [[
        'driver' => 'redis',
        'connection' => 'missing',
        'lock_connection' => 'missing',
    ]],
]);

it('also validates a separately configured document snapshot queue', function () {
    $container = Illuminate\Container\Container::getInstance();
    $container->instance('config', new Illuminate\Config\Repository([
        'api_sync' => [
            'session_ttl_minutes' => 360,
            'snapshot' => ['connection' => 'api_sync_database', 'maximum_build_seconds' => 900],
        ],
        'api_sync_documents' => ['queue' => ['connection' => 'unsafe_document_v2_test']],
        'database' => ['default' => 'pgsql'],
        'queue' => ['connections' => [
            'api_sync_database' => ['driver' => 'database', 'connection' => null, 'retry_after' => 2_100],
            'unsafe_document_v2_test' => ['driver' => 'sync'],
        ]],
    ]));

    $service = (new ReflectionClass(App\Services\ApiSync\ApiSyncPairingService::class))->newInstanceWithoutConstructor();

    expect(fn () => $service->ensureAsyncQueue(true))
        ->toThrow(App\Exceptions\ApiSyncException::class);
});

it('never reports an exact lost-response activation retry as active after local terminal state', function () {
    $digest = hash('sha256', 'bounded-test-credential');
    $invitation = (new App\Models\ApiSyncInvitation)->forceFill([
        'status' => App\Models\ApiSyncInvitation::STATUS_ACTIVE,
    ]);
    $pairing = (new class extends App\Models\ApiSyncPairing
    {
        public function getDateFormat(): string
        {
            return 'Y-m-d H:i:s';
        }
    })->forceFill([
        'status' => App\Models\ApiSyncPairing::STATUS_CLAIMED,
        'token_hash' => $digest,
        'token_expires_at' => now()->addMinute(),
    ]);
    $service = (new ReflectionClass(App\Services\ApiSync\ApiSyncInvitationService::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod($service, 'isLiveActivationPairing');

    expect($method->invoke($service, $invitation, $pairing, $digest))->toBeTrue();
    foreach ([
        App\Models\ApiSyncPairing::STATUS_REVOKED,
        App\Models\ApiSyncPairing::STATUS_EXPIRED,
        App\Models\ApiSyncPairing::STATUS_COMPLETED,
    ] as $terminalStatus) {
        $pairing->forceFill(['status' => $terminalStatus]);
        expect($method->invoke($service, $invitation, $pairing, $digest))->toBeFalse();
    }
    $pairing->forceFill([
        'status' => App\Models\ApiSyncPairing::STATUS_CLAIMED,
        'token_expires_at' => now()->subSecond(),
    ]);
    expect($method->invoke($service, $invitation, $pairing, $digest))->toBeFalse();

    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/ApiSync/ApiSyncInvitationService.php');
    expect($source)
        ->toContain("'activation_session_terminal'")
        ->toContain('if ($this->isLiveActivationPairing($locked, $existing, $digest))');
});

it('distinguishes ambiguous responses from signed terminal rejection and revokes activated access', function () {
    $root = dirname(__DIR__, 2);
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncInvitationService.php');

    $verifyPosition = strpos($service, '$this->signatures->verifyResponse($response, $path, $envelope');
    $statusPosition = strpos($service, 'if ($response->status() !== 202)');
    expect($verifyPosition)->toBeInt()->toBeLessThan($statusPosition);
    expect($service)
        ->toContain('An unavailable or unverifiable response is ambiguous')
        ->toContain('same request ID and nonce')
        ->toContain('revokeAfterSignedCentralRejection')
        ->toContain("'token_hash' => null")
        ->toContain('$this->snapshots->requestPurge($pairing)')
        ->toContain("'status' => in_array(\$errorCode, ['invitation_expired', 'activation_credential_expired'], true)")
        ->toContain("'terminal_error_code' => \$errorCode");
});

it('finalization is signed bound idempotent and cannot broaden the approved scope', function () {
    $root = dirname(__DIR__, 2);
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncInvitationService.php');

    expect($service)
        ->toContain('$this->signatures->verifyRequest($request, $payload)')
        ->toContain("'outcome' => ['required', Rule::in(['accepted', 'rejected'])]")
        ->toContain("hash_equals(strtolower((string) \$invitation->id), \$signature['request_id'])")
        ->toContain("where('nonce', \$signature['nonce'])->first()")
        ->toContain("(string) \$seen->purpose, 'finalization'")
        ->toContain('finalizationAlreadyApplied')
        ->toContain("array_values(\$validated['approved_datasets']) !== array_values((array) \$invitation->requested_datasets)")
        ->toContain("array_values(\$validated['approved_scopes']) !== array_values((array) \$invitation->requested_scopes)")
        ->toContain('invalid_document_scope_pair');
});

it('recovers a lost complete response with the same bounded credential and no data access', function () {
    $root = dirname(__DIR__, 2);
    $middleware = file_get_contents($root.'/app/Http/Middleware/AuthenticateApiSyncV2.php');
    $pairings = file_get_contents($root.'/app/Services/ApiSync/ApiSyncPairingService.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/Api/ApiSyncInvitationController.php');

    expect($middleware)
        ->toContain("routeIs('api.sync.v2.complete')")
        ->toContain('$pairing->status === ApiSyncPairing::STATUS_COMPLETED')
        ->toContain('$invitation->status === ApiSyncInvitation::STATUS_COMPLETED')
        ->toContain('$pairing->token_expires_at?->isFuture()')
        ->toContain('return $next($request);');
    expect($pairings)
        ->toContain('$locked->status === ApiSyncPairing::STATUS_COMPLETED')
        ->toContain("'completed_at' => \$locked->completed_at ?? now()")
        ->toContain("'status' => ApiSyncInvitation::STATUS_COMPLETED")
        ->toContain("\$completed->where('status', ApiSyncPairing::STATUS_COMPLETED)")
        ->toContain("'completion_retry_expired'")
        ->toContain("'token_hash' => null");
    expect($controller)
        ->toContain("'credential_revoked' => true")
        ->toContain("'snapshot_id' => (string) \$validated['snapshot_id']")
        ->not->toContain("whereIn('status', [ApiSyncInvitation::STATUS_ACTIVATION_RECEIVED");
});

it('expires the inbound invitation atomically with its pairing', function () {
    $pairings = file_get_contents(dirname(__DIR__, 2).'/app/Services/ApiSync/ApiSyncPairingService.php');

    expect($pairings)
        ->toContain('ApiSyncInvitation::query()->lockForUpdate()->find($binding->inbound_invitation_id)')
        ->toContain('if ($sessionExpired && $invitation && in_array($invitation->status')
        ->toContain('ApiSyncInvitation::STATUS_ACTIVE,')
        ->toContain("'status' => ApiSyncInvitation::STATUS_EXPIRED");
});

it('uses invitation then pairing locks for completion and scheduled expiry with deadlock retries', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/ApiSync/ApiSyncPairingService.php');
    $complete = str($source)->after('public function complete(')->before('public function revoke(')->toString();
    $expiry = str($source)->after('public function expireStale(')->before('public function ensureAsyncQueue(')->toString();

    expect(strpos($complete, 'ApiSyncInvitation::query()->lockForUpdate()'))
        ->toBeLessThan(strpos($complete, 'ApiSyncPairing::query()->lockForUpdate()'));
    expect($complete)
        ->toContain("'pairing_binding_changed'")
        ->toContain('}, 3);');
    expect(strpos($expiry, 'ApiSyncInvitation::query()->lockForUpdate()'))
        ->toBeLessThan(strpos($expiry, 'ApiSyncPairing::query()->lockForUpdate()'));
    expect($expiry)
        ->toContain("select(['id', 'inbound_invitation_id'])")
        ->toContain('}, 3);');
});

it('uses the same invitation then pairing lock order when middleware observes credential expiry', function () {
    $middleware = file_get_contents(dirname(__DIR__, 2).'/app/Http/Middleware/AuthenticateApiSyncV2.php');

    expect($middleware)
        ->toContain('$bindingMatches = $lockedInvitation !== null')
        ->toContain('}, 3);');
    expect(strpos($middleware, 'ApiSyncInvitation::query()->lockForUpdate()'))
        ->toBeLessThan(strpos($middleware, 'ApiSyncPairing::query()->lockForUpdate()'));
});

it('keeps finalization decline and revoke security audits inside their state transactions', function () {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/ApiSync/ApiSyncInvitationService.php');

    foreach ([
        ['public function finalize(', "'invitation_finalized'", 'public function approve('],
        ['public function decline(', "'invitation_declined'", 'public function revoke('],
        ['public function revoke(', "'invitation_revoked'", 'public function expireAndPrune('],
    ] as [$start, $auditEvent, $end]) {
        $method = str($service)->after($start)->before($end)->toString();
        expect($method)
            ->toContain('DB::transaction(function () use (')
            ->toContain($auditEvent)
            ->and(strpos($method, $auditEvent))->toBeLessThan(strrpos($method, '}, 3);'));
    }
});

it('keeps stored signed authorizations activatable beyond the seven digit code window', function () {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/ApiSync/ApiSyncInvitationService.php');

    expect($service)
        ->toContain('$signedAuthorizationStored = $locked->status === ApiSyncInvitation::STATUS_ACTIVATION_PENDING')
        ->toContain('$locked->credential_expires_at?->isPast()')
        ->toContain('$locked->expires_at?->isPast() && ! $signedAuthorizationStored && ! $approvalResponseWasLost')
        ->toContain("'activation_credential_expired'");
});

it('keeps lost approval responses recoverable until credential expiry instead of code expiry', function () {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/ApiSync/ApiSyncInvitationService.php');

    expect($service)
        ->toContain('$approvalResponseWasLost = $locked->status === ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS')
        ->toContain('Str::isUuid((string) $locked->confirmation_request_nonce)')
        ->toContain("ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS,\n                        ApiSyncInvitation::STATUS_ACTIVATION_PENDING,")
        ->toContain("->where('credential_expires_at', '<=', now())")
        ->not->toContain("whereIn('status', [ApiSyncInvitation::STATUS_PENDING, ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS])\n            ->where('expires_at'");
});

it('commits receive authorization and completion lifecycle events atomically with replay healing', function () {
    $root = dirname(__DIR__, 2);
    $invitations = file_get_contents($root.'/app/Services/ApiSync/ApiSyncInvitationService.php');
    $pairings = file_get_contents($root.'/app/Services/ApiSync/ApiSyncPairingService.php');
    $audit = file_get_contents($root.'/app/Services/ApiSync/ApiSyncInvitationAuditService.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/Api/ApiSyncInvitationController.php');
    $migration = file_get_contents($root.'/database/migrations/2026_08_25_000010_enforce_api_sync_lifecycle_audit_once.php');
    $receive = str($invitations)->after('public function receive(')->before('public function activate(')->toString();
    $approve = str($invitations)->after('public function approve(')->before('public function decline(')->toString();
    $complete = str($pairings)->after('public function complete(')->before('public function revoke(')->toString();

    expect($receive)
        ->toContain("recordOnce(\n                    \$invitation,\n                    'invitation_received'")
        ->toContain("recordOnce(\n                    \$locked,\n                    'invitation_received'")
        ->and(strpos($receive, "'invitation_received'"))->toBeLessThan(strrpos($receive, '}, 3);'));
    expect(strpos($receive, '$existing = ApiSyncInvitation::query()->find($invitationId)'))
        ->toBeLessThan(strpos($receive, '$this->assertInvitationTimes('));
    expect($approve)
        ->toContain("recordOnce(\n                        \$locked,\n                        'invitation_authorized'")
        ->toContain("'authorization_persistence_unavailable'")
        ->and(strpos($approve, "'invitation_authorized'"))->toBeLessThan(strrpos($approve, '}, 3);'));
    expect($complete)
        ->toContain("'invitation_transfer_completed'")
        ->and(strpos($complete, "'invitation_transfer_completed'"))->toBeLessThan(strrpos($complete, '}, 3);'));
    expect($audit)
        ->toContain('public function recordOnce(')
        ->toContain("hash('sha256', strtolower((string) \$invitation->id).\"\\0\".\$eventType)");
    expect($migration)
        ->toContain("char('lifecycle_key', 64)")
        ->toContain("unique('lifecycle_key', 'api_sync_invitation_lifecycle_once_idx')")
        ->not->toContain('DELETE FROM api_sync_invitation_events');
    expect($controller)->not->toContain("invitationAudit->record(\$invitation->fresh(), 'invitation_transfer_completed'");
});

it('requires shared database or redis locks in addition to the durable snapshot queue', function () {
    $service = file_get_contents(dirname(__DIR__, 2).'/app/Services/ApiSync/ApiSyncPairingService.php');
    $guard = str($service)->after('private function ensureSharedLockStore(): void')->toString();

    expect($service)->toContain('$this->ensureSharedLockStore();');
    expect($guard)
        ->toContain("in_array(\$driver, ['database', 'redis'], true)")
        ->toContain("'snapshot_lock_store_unavailable'")
        ->toContain('config("cache.stores.{$store}.lock_connection")')
        ->toContain('config("database.connections.{$cacheConnection}.driver")')
        ->toContain('config("database.redis.{$redisLockConnection}")');
});
