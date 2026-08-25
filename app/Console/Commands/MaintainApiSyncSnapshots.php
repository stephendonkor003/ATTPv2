<?php

namespace App\Console\Commands;

use App\Services\ApiSync\ApiSyncDocumentSnapshotService;
use App\Services\ApiSync\ApiSyncSnapshotService;
use Illuminate\Console\Command;

class MaintainApiSyncSnapshots extends Command
{
    protected $signature = 'api-sync:snapshots:maintain {--limit=100 : Maximum builds and purges to inspect per run}';

    protected $description = 'Dispatch pending immutable API snapshots and purge closed snapshot storage';

    public function handle(
        ApiSyncSnapshotService $snapshots,
        ApiSyncDocumentSnapshotService $documents,
    ): int {
        $limit = max(1, min((int) $this->option('limit'), 1_000));
        $dispatched = $snapshots->dispatchPending($limit);
        $documentDispatched = $documents->dispatchPending($limit);
        $purged = $snapshots->pruneExpired($limit);
        $documentPurged = $documents->pruneExpired($limit);
        $this->info("Dispatched {$dispatched} record and {$documentDispatched} document snapshot build(s); purged {$purged} record and {$documentPurged} document snapshot(s).");

        return self::SUCCESS;
    }
}
