<?php

use App\Exceptions\ApiSyncException;
use App\Services\ApiSync\ApiSyncCursor;
use App\Services\ApiSync\ApiSyncDatasetService;
use App\Support\ApiSyncAllocationIdentity;

it('publishes the normalized v1 datasets in dependency order', function () {
    expect(ApiSyncDatasetService::DATASETS)->toBe([
        'portfolios',
        'programmes',
        'projects',
        'activities',
        'sub_activities',
        'fiscal_years',
        'budget_allocations',
        'commitments',
        'executions',
    ]);
});

it('normalizes allocation levels into collision-safe external identifiers', function () {
    expect(ApiSyncAllocationIdentity::normalizeLevel('project'))->toBe('project')
        ->and(ApiSyncAllocationIdentity::normalizeLevel('Projects'))->toBe('project')
        ->and(ApiSyncAllocationIdentity::normalizeLevel('Activities'))->toBe('activity')
        ->and(ApiSyncAllocationIdentity::normalizeLevel('Sub Activity'))->toBe('sub_activity')
        ->and(ApiSyncAllocationIdentity::normalizeLevel('subactivities'))->toBe('sub_activity')
        ->and(ApiSyncAllocationIdentity::externalId('sub-activity', ' allocation-42 '))->toBe('sub_activity:allocation-42')
        ->and(ApiSyncAllocationIdentity::normalizeLevel('sub-component'))->toBeNull()
        ->and(ApiSyncAllocationIdentity::externalId('unexpected-level', 'allocation-42'))->toBeNull()
        ->and(ApiSyncAllocationIdentity::externalId('activity', ''))->toBeNull();
});

it('cryptographically binds opaque cursors to a dataset snapshot and consumer', function () {
    $service = new ApiSyncCursor('unit-test-api-sync-pepper-that-is-not-production');
    $snapshot = 'ee5f2213-f50c-44ee-8bb7-4caf76aec8cb';
    $cursor = $service->encode('projects', $snapshot, 'aupremis-test-01', ['id' => '01991fa8-8ccd-70a7-a860-7025c05f5ea2']);

    expect($service->decode($cursor, 'projects', $snapshot, 'aupremis-test-01'))
        ->toBe(['id' => '01991fa8-8ccd-70a7-a860-7025c05f5ea2']);

    expect(fn () => $service->decode($cursor, 'programmes', $snapshot, 'aupremis-test-01'))
        ->toThrow(ApiSyncException::class);
    expect(fn () => $service->decode($cursor.'tampered', 'projects', $snapshot, 'aupremis-test-01'))
        ->toThrow(ApiSyncException::class);
});

it('keeps pairing credentials digest-only and prevents claim replay', function () {
    $root = dirname(__DIR__, 2);
    $migration = file_get_contents($root.'/database/migrations/2026_08_25_000001_create_api_sync_tables.php');
    $pairingService = file_get_contents($root.'/app/Services/ApiSync/ApiSyncPairingService.php');
    $claimRequest = file_get_contents($root.'/app/Http/Requests/ApiSyncClaimRequest.php');

    expect($migration)
        ->toContain("char('code_hash', 64)")
        ->toContain("char('token_hash', 64)")
        ->toContain("char('claim_idempotency_hash', 64)")
        ->not->toContain("string('pairing_code'")
        ->not->toContain("string('access_token'");
    expect($pairingService)
        ->toContain('random_int(1_000_000, 9_999_999)')
        ->toContain('random_bytes(32)')
        ->toContain("hash('sha256', \$token)")
        ->toContain('pairing_claim_already_processed')
        ->toContain("'token_hash' => null");
    expect($claimRequest)
        ->toContain("header('Idempotency-Key')")
        ->toContain("header('X-Claim-Recovery-Key')")
        ->toContain("'idempotency_key' => ['required', 'uuid']");
    expect(file_get_contents($root.'/app/Http/Controllers/System/ApiSyncController.php'))
        ->not->toContain('api_sync_generated_code')
        ->toContain("'Cache-Control', 'no-store, private'");
});

it('gates the UI and publishes bounded authenticated API routes', function () {
    $root = dirname(__DIR__, 2);
    $webRoutes = file_get_contents($root.'/routes/web.php');
    $apiRoutes = file_get_contents($root.'/routes/api.php');
    $sidebar = file_get_contents($root.'/resources/views/layouts/partials/sidebar.blade.php');
    $datasetService = file_get_contents($root.'/app/Services/ApiSync/ApiSyncDatasetService.php');
    $snapshotService = file_get_contents($root.'/app/Services/ApiSync/ApiSyncSnapshotService.php');

    expect($webRoutes)
        ->toContain('permission:api_sync.view')
        ->toContain('permission:api_sync.generate')
        ->toContain('api-sync-generate');
    expect(file_get_contents($root.'/app/Http/Controllers/System/ApiSyncController.php'))
        ->toContain("'current_password' => ['required', 'current_password']");
    expect($sidebar)
        ->toContain("@can('api_sync.view')")
        ->toContain('API Sync')
        ->and(strpos($sidebar, 'ATTP AI Guide'))->toBeLessThan(strpos($sidebar, '> API Sync'));
    expect($apiRoutes)
        ->toContain("prefix('sync/v1')")
        ->toContain("'/pairings/claim'")
        ->toContain("'/pairings/abandon'")
        ->toContain("['api.sync', 'throttle:180,1,api-sync-data']")
        ->toContain("'/datasets/{dataset}'")
        ->toContain("'/pairings/complete'");
    expect($datasetService)
        ->toContain('min($requestedLimit, 1_000)')
        ->toContain('limit($limit + 1)')
        ->toContain("leftJoin('myb_budget_commitments as bc'")
        ->toContain('ApiSyncAllocationIdentity::externalId(')
        ->toContain("Schema::hasColumn('myb_budget_commitments', 'created_at')")
        ->toContain('whereNull("{$alias}.updated_at")')
        ->toContain("->where('d.paid_at', '<=', \$pairing->snapshot_at)")
        ->toContain('fiscalYears($pairing)')
        ->not->toContain("'vendor_id'")
        ->not->toContain("'payment_method'")
        ->not->toContain("'signed_documents'");
    expect($snapshotService)
        ->toContain("DB::table('api_sync_snapshot_records')")
        ->toContain('min($requestedLimit, $maximum)')
        ->toContain('limit($limit + 1)');
});

it('documents the exact consumer contract and ambiguous-claim recovery', function () {
    $root = dirname(__DIR__, 2);
    $documentation = file_get_contents($root.'/docs/API_SYNC_PROVIDER.md');

    expect($documentation)
        ->toContain('POST /api/sync/v1/pairings/claim')
        ->toContain('POST /api/sync/v1/pairings/abandon')
        ->toContain('GET /api/sync/v1/manifest')
        ->toContain('GET /api/sync/v1/datasets/projects')
        ->toContain('POST /api/sync/v1/pairings/complete')
        ->toContain('X-Consumer-Instance')
        ->toContain('pairing_claim_already_processed')
        ->toContain('sub_activity:<source-id>')
        ->toContain('Commitment snapshot membership')
        ->toContain('picked up only by a later synchronization')
        ->toContain('must not automatically retry the claim');
});
