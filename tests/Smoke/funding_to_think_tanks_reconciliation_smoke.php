<?php

use App\Models\ActivityAllocation;
use App\Models\SubActivity;
use App\Models\SubActivityAllocation;
use App\Services\FundingToThinkTanksAllocationReconciler;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$activityId = '019ea974-4b9f-7348-b643-f9bbd6ffcec4';
$targetId = FundingToThinkTanksAllocationReconciler::TARGET_SUB_ACTIVITY_ID;
$siblingId = '019ea974-4be2-7029-b4ea-4ea6cfd76368';

DB::beginTransaction();

try {
    ActivityAllocation::query()
        ->where('activity_id', $activityId)
        ->where('year', 2028)
        ->update(['amount' => 6_280_000]);

    foreach ([2024 => 0, 2025 => 24_500_000, 2026 => 0, 2027 => 0, 2028 => 0] as $year => $amount) {
        SubActivityAllocation::updateOrCreate(
            ['sub_activity_id' => $targetId, 'year' => $year],
            ['amount' => $amount]
        );
    }

    foreach ([2024 => 0, 2025 => 24_800, 2026 => 0, 2027 => 0, 2028 => 0] as $year => $amount) {
        SubActivityAllocation::updateOrCreate(
            ['sub_activity_id' => $siblingId, 'year' => $year],
            ['amount' => $amount]
        );
    }

    $subActivity = SubActivity::findOrFail($targetId);
    $service = $app->make(FundingToThinkTanksAllocationReconciler::class);
    $preview = $service->preview($subActivity);

    if (($preview['status'] ?? null) !== 'ready') {
        throw new RuntimeException('The compatible server-side parent envelope was not recognized as ready.');
    }

    $result = $service->reconcile($subActivity);
    if (($result['changed'] ?? false) !== true || ($result['status'] ?? null) !== 'complete') {
        throw new RuntimeException('The one-click reconciliation did not complete.');
    }
    if (abs((float) ($result['snapshot']['parent_by_year'][2028] ?? 0) - 6_280_000) > 0.004
        || abs((float) ($result['snapshot']['parent_total'] ?? 0) - 26_787_000) > 0.004) {
        throw new RuntimeException('The valid larger parent envelope was not preserved.');
    }

    $repeat = $service->reconcile($subActivity);
    if (($repeat['changed'] ?? true) !== false || ($repeat['status'] ?? null) !== 'complete') {
        throw new RuntimeException('The reconciliation was not idempotent.');
    }

    echo "FUNDING_TO_THINK_TANKS_RECONCILIATION_SMOKE_OK\n";
} finally {
    DB::rollBack();
}
