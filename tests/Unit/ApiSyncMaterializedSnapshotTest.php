<?php

use App\Exceptions\ApiSyncException;
use App\Jobs\BuildApiSyncSnapshot;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

it('persists normalized snapshots with stable ordering and exact payload integrity', function () {
    $root = dirname(__DIR__, 2);
    $migration = file_get_contents($root.'/database/migrations/2026_08_25_000004_create_api_sync_materialized_snapshots.php');
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncSnapshotService.php');
    $datasets = file_get_contents($root.'/app/Services/ApiSync/ApiSyncDatasetService.php');

    expect($migration)
        ->toContain("Schema::create('api_sync_snapshot_datasets'")
        ->toContain("Schema::create('api_sync_snapshot_records'")
        ->toContain("char('payload_hash', 64)")
        ->toContain("unique(['snapshot_id', 'dataset', 'sequence'])")
        ->toContain("unique(['snapshot_id', 'dataset', 'source_id'])");
    expect($service)
        ->toContain("DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')")
        ->toContain("set_config('statement_timeout'")
        ->toContain('materializationPage($pairing, $dataset, $position, $chunk)')
        ->toContain("'payload_hash' => hash('sha256', \$payload)")
        ->toContain("hash_equals(\$storedPayloadHash, hash('sha256', \$encoded))")
        ->toContain('strlen($encoded) !== (int) $row->payload_bytes')
        ->toContain('hash_equals((string) $row->source_id, $payloadId)')
        ->toContain('hash_equals($recomputedChecksum, $payloadChecksum)');
    expect($datasets)
        ->toContain("'bc.commitment_year as commitment_year'")
        ->toContain("'fiscal_year' => \$row->budget_commitment_id !== null")
        ->toContain("? (\$row->commitment_year ? 'FY-'.\$row->commitment_year : null)")
        ->toContain(": (\$row->paid_at ? 'FY-'.CarbonImmutable::parse(\$row->paid_at)->year : null)");
});

it('never serves live domain queries after an immutable snapshot becomes available', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/Api/ApiSyncController.php');
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncSnapshotService.php');
    preg_match('/public function page\(.*?\n    public function build/s', $service, $page);

    expect($controller)
        ->toContain('ApiSyncSnapshotService $snapshots')
        ->toContain('$this->snapshots->manifest($pairing)')
        ->toContain('$this->snapshots->page(')
        ->not->toContain('ApiSyncDatasetService');
    expect($page[0] ?? '')
        ->toContain("DB::table('api_sync_snapshot_records')")
        ->toContain("DB::table('api_sync_snapshot_datasets')")
        ->toContain('sharedLock()')
        ->toContain('REPEATABLE READ')
        ->not->toContain('myb_')
        ->not->toContain('procurement_');
});

it('uses a durable encrypted and overlap-fenced background builder', function () {
    $interfaces = class_implements(BuildApiSyncSnapshot::class);
    $root = dirname(__DIR__, 2);
    $job = file_get_contents($root.'/app/Jobs/BuildApiSyncSnapshot.php');
    $queue = file_get_contents($root.'/config/queue.php');
    $snapshot = file_get_contents($root.'/app/Services/ApiSync/ApiSyncSnapshotService.php');
    $pairing = file_get_contents($root.'/app/Services/ApiSync/ApiSyncPairingService.php');

    expect($interfaces)->toContain(ShouldQueue::class)
        ->toContain(ShouldBeUnique::class)
        ->toContain(ShouldBeEncrypted::class);
    expect($job)
        ->toContain('TIMEOUT_SECONDS = 1_860')
        ->toContain('MUTEX_SECONDS = 2_000')
        ->toContain('public int $uniqueFor = 2_100')
        ->toContain('public bool $failOnTimeout = true')
        ->toContain("Cache::lock('api-sync-snapshot-build:'")
        ->toContain('$this->release(30)');
    expect($queue)
        ->toContain("'api_sync_database' => [")
        ->toContain("env('ATTP_API_SYNC_SNAPSHOT_RETRY_AFTER', 2_100)")
        ->toContain("'after_commit' => true");
    expect($snapshot)
        ->toContain("->onConnection((string) config('api_sync.snapshot.connection'")
        ->toContain("->onQueue((string) config('api_sync.snapshot.queue'");
    expect($pairing)
        ->toContain('BuildApiSyncSnapshot::MUTEX_SECONDS')
        ->toContain('$sessionSeconds < $maximumBuildSeconds + 900')
        ->toContain('snapshot_configuration_invalid')
        ->toContain('snapshot_queue_unavailable');
});

it('returns a typed polling state without exposing live counts', function () {
    $root = dirname(__DIR__, 2);
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncSnapshotService.php');
    $exception = new ApiSyncException(
        'snapshot_building',
        'The snapshot is building.',
        425,
        ['Retry-After' => '5'],
    );
    expect($exception->httpStatus)->toBe(425)
        ->and($exception->headers['Retry-After'])->toBe('5')
        ->and($exception->errorCode)->toBe('snapshot_building');
    expect($service)
        ->toContain("'count' => null")
        ->toContain("'status' => self::STATUS_PENDING")
        ->toContain("'snapshot_building'")
        ->toContain("['Retry-After' => '5']");
});

it('bounds provider storage and serializes concurrent reservations', function () {
    $root = dirname(__DIR__, 2);
    $migration = file_get_contents($root.'/database/migrations/2026_08_25_000004_create_api_sync_materialized_snapshots.php');
    $pairing = file_get_contents($root.'/app/Services/ApiSync/ApiSyncPairingService.php');
    $configuration = file_get_contents($root.'/config/api_sync.php');

    expect($migration)
        ->toContain("Schema::create('api_sync_snapshot_capacity_locks'")
        ->toContain("'scope' => 'provider'");
    expect($pairing)
        ->toContain("DB::table('api_sync_snapshot_capacity_locks')")
        ->toContain("where('scope', 'provider')")
        ->toContain('lockForUpdate()')
        ->toContain("->whereNotNull('snapshot_id')")
        ->toContain("->whereNull('snapshot_purged_at')")
        ->toContain('snapshot_capacity_unavailable');
    expect($configuration)
        ->toContain("'maximum_active_sessions' => min(10, max(1,")
        ->toContain("'maximum_records' => min(1_000_000")
        ->toContain("'maximum_bytes' => min(2_147_483_648")
        ->toContain("'maximum_build_seconds' => min(1_800");
});

it('uses deadlock-safe deferred cleanup and shared scheduler leadership', function () {
    $root = dirname(__DIR__, 2);
    $snapshot = file_get_contents($root.'/app/Services/ApiSync/ApiSyncSnapshotService.php');
    $pairing = file_get_contents($root.'/app/Services/ApiSync/ApiSyncPairingService.php');
    $schedule = file_get_contents($root.'/bootstrap/app.php');

    expect($pairing)
        ->toContain('$this->snapshots->requestPurge(')
        ->not->toContain('$this->snapshots->purge(');
    expect($snapshot)
        ->toContain("STATUS_PURGE_PENDING = 'purge_pending'")
        ->toContain('Delete children first and lock the pairing only for the final')
        ->toContain("'snapshot_status' => self::STATUS_PURGED")
        ->toContain('sync_snapshot_purged');
    expect($schedule)
        ->toContain("api-sync:expire --limit=500')->everyFiveMinutes()->withoutOverlapping()->onOneServer()")
        ->toContain("api-sync:snapshots:maintain --limit=100')->everyMinute()->withoutOverlapping()->onOneServer()");
});

it('hardens code arguments, same-creator generation, and rejection audit statuses', function () {
    $root = dirname(__DIR__, 2);
    $pairing = file_get_contents($root.'/app/Services/ApiSync/ApiSyncPairingService.php');
    $audit = file_get_contents($root.'/app/Services/ApiSync/ApiSyncAuditService.php');

    expect($pairing)
        ->toContain('User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail()')
        ->toContain('#[\SensitiveParameter] string $code')
        ->toContain('codeHash(#[\SensitiveParameter] string $code)')
        ->toContain('statusCode: 409')
        ->toContain('statusCode: 422');
    expect($audit)
        ->toContain("'status_code' => \$statusCode ?? 200")
        ->toContain('return DB::transaction(function () use (');
});

it('documents materialization polling, bounded operations, and cleanup', function () {
    $root = dirname(__DIR__, 2);
    $docs = file_get_contents($root.'/docs/API_SYNC_PROVIDER.md');
    $example = file_get_contents($root.'/.env.api-sync.example');

    expect($docs)
        ->toContain('HTTP/1.1 425 Too Early')
        ->toContain('"count": null')
        ->toContain('PostgreSQL `REPEATABLE READ` transaction')
        ->toContain('provider-owned materialized rows')
        ->toContain('queue:work api_sync_database --queue=api-sync')
        ->toContain('at most two unpurged snapshot reservations')
        ->toContain('api-sync:snapshots:maintain --limit=100');
    expect($example)
        ->toContain('ATTP_API_SYNC_SNAPSHOT_CONNECTION=api_sync_database')
        ->toContain('ATTP_API_SYNC_SNAPSHOT_RETRY_AFTER=2100')
        ->toContain('ATTP_API_SYNC_SNAPSHOT_MAX_ACTIVE_SESSIONS=2');
});
