<?php

use App\Models\ApiSyncPairing;
use App\Services\ApiSync\ApiSyncDatasetService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$snapshotId = (string) Str::uuid();
$pairing = new ApiSyncPairing;
$pairing->forceFill([
    'snapshot_id' => $snapshotId,
    'snapshot_at' => now(),
    'token_expires_at' => now()->addHours(6),
    'consumer_instance' => 'aupremis-readonly-smoke',
]);

/** @var ApiSyncDatasetService $service */
$service = $app->make(ApiSyncDatasetService::class);

foreach (ApiSyncDatasetService::DATASETS as $dataset) {
    $page = $service->materializationPage($pairing, $dataset, [], 2);
    $assert(count($page['data']) <= 2, "{$dataset} exceeded its requested source chunk bound.");
    $assert(is_bool($page['has_more'] ?? null), "{$dataset} returned malformed source pagination state.");

    foreach ($page['data'] as $record) {
        $assert(isset($record['id'], $record['checksum']), "{$dataset} returned a record without identity controls.");
        $assert(is_array($record['attributes'] ?? null), "{$dataset} returned malformed attributes.");
        $assert(is_array($record['relationships'] ?? null), "{$dataset} returned malformed relationships.");

        if ($dataset === 'budget_allocations') {
            $level = $record['attributes']['level'] ?? null;
            $assert(in_array($level, ['project', 'activity', 'sub_activity'], true), 'An allocation returned a non-canonical level.');
            $assert(str_starts_with($record['id'], $level.':'), 'An allocation ID is not namespaced by its canonical level.');
            $assert(
                str_starts_with((string) ($record['relationships']['allocation_target'] ?? ''), $level.':'),
                'An allocation target is not namespaced by its canonical level.',
            );
        }

        if ($dataset === 'commitments' && isset($record['relationships']['allocation_target'])) {
            $level = $record['attributes']['allocation_level'] ?? null;
            $assert(
                in_array($level, ['project', 'activity', 'sub_activity'], true)
                    && str_starts_with($record['relationships']['allocation_target'], $level.':'),
                'A commitment allocation target is not canonically typed.',
            );
        }

        if ($dataset === 'executions' && isset($record['relationships']['allocation_target'])) {
            $assert(
                preg_match('/^(project|activity|sub_activity):[^:]+$/', $record['relationships']['allocation_target']) === 1,
                'An execution allocation target is not canonically typed.',
            );
        }
    }
}

$historicalSnapshot = now()->subDay();
$historicalPairing = new ApiSyncPairing;
$historicalPairing->forceFill([
    'snapshot_id' => (string) Str::uuid(),
    'snapshot_at' => $historicalSnapshot,
    'token_expires_at' => now()->addHours(6),
    'consumer_instance' => 'aupremis-readonly-snapshot-smoke',
]);

$expectedCommitments = DB::table('myb_budget_commitments as c');
if (Schema::hasColumn('myb_budget_commitments', 'created_at')) {
    $expectedCommitments->where(function (Builder $snapshot) use ($historicalSnapshot): void {
        $snapshot
            ->where(function (Builder $created) use ($historicalSnapshot): void {
                $created->whereNotNull('c.created_at')->where('c.created_at', '<=', $historicalSnapshot);
            })
            ->orWhere(function (Builder $legacy) use ($historicalSnapshot): void {
                $legacy->whereNull('c.created_at')
                    ->where(function (Builder $approval) use ($historicalSnapshot): void {
                        $approval->whereNull('c.approved_at')->orWhere('c.approved_at', '<=', $historicalSnapshot);
                    });
            });
    });
} else {
    $expectedCommitments->where(function (Builder $approval) use ($historicalSnapshot): void {
        $approval->whereNull('c.approved_at')->orWhere('c.approved_at', '<=', $historicalSnapshot);
    });
}
if (Schema::hasColumn('myb_budget_commitments', 'updated_at')) {
    $expectedCommitments->where(function (Builder $updated) use ($historicalSnapshot): void {
        $updated->whereNull('c.updated_at')->orWhere('c.updated_at', '<=', $historicalSnapshot);
    });
}

$materializedCommitmentCount = 0;
$position = [];
do {
    $commitmentPage = $service->materializationPage($historicalPairing, 'commitments', $position, 1_000);
    $materializedCommitmentCount += count($commitmentPage['data']);
    $position = $commitmentPage['next_position'] ?? [];
} while ($commitmentPage['has_more']);

$assert(
    $materializedCommitmentCount === $expectedCommitments->count(),
    'Commitment snapshot filtering no longer preserves pre-snapshot and unapproved records.',
);

echo "ATTP API Sync read-only provider smoke passed.\n";
