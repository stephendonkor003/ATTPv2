<?php

use App\Models\SubActivity;
use App\Services\FundingToThinkTanksAllocationReconciler;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = DB::connection()->getDatabaseName();
if (! str_starts_with($database, 'attp_server_audit_')) {
    throw new RuntimeException("Safety stop: expected an isolated server-audit database, connected to [{$database}].");
}

DB::beginTransaction();

try {
    $service = $app->make(FundingToThinkTanksAllocationReconciler::class);
    $subActivity = SubActivity::findOrFail(FundingToThinkTanksAllocationReconciler::TARGET_SUB_ACTIVITY_ID);
    $preview = $service->preview($subActivity);

    if (! in_array(($preview['status'] ?? null), ['ready', 'complete'], true)) {
        throw new RuntimeException('The downloaded server allocation fingerprint is neither repairable nor complete.');
    }

    $result = $service->reconcile($subActivity);
    $after = $result['snapshot'] ?? [];

    if (($result['status'] ?? null) !== 'complete'
        || abs((float) ($after['target_total'] ?? 0) - 24_500_000) > 0.004
        || abs((float) data_get($after, 'target_by_year.2025', 0) - 24_800) > 0.004
        || abs((float) data_get($after, 'target_by_year.2028', 0) - 5_118_200) > 0.004
        || abs((float) ($after['sibling_total'] ?? 0) - 24_800) > 0.004) {
        throw new RuntimeException('The downloaded server allocation was not fully reconciled.');
    }

    if (($preview['status'] ?? null) === 'ready' && ($result['changed'] ?? false) !== true) {
        throw new RuntimeException('The repairable server allocation did not apply its corrective transaction.');
    }

    if (($preview['status'] ?? null) === 'complete' && ($result['changed'] ?? true) !== false) {
        throw new RuntimeException('The completed server allocation unexpectedly changed on recheck.');
    }

    $repeat = $service->reconcile($subActivity);
    if (($repeat['changed'] ?? true) !== false || ($repeat['status'] ?? null) !== 'complete') {
        throw new RuntimeException('The downloaded server reconciliation is not idempotent.');
    }

    echo "SERVER_FUNDING_ALLOCATION_RECONCILIATION_SMOKE_OK\n";
} finally {
    DB::rollBack();
}
