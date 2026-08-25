<?php

namespace App\Console\Commands;

use App\Services\ApiSync\ApiSyncPairingService;
use Illuminate\Console\Command;

class ExpireApiSyncPairings extends Command
{
    protected $signature = 'api-sync:expire {--limit=500 : Maximum sessions to expire in one run}';

    protected $description = 'Expire stale ATTP API synchronization codes and credentials';

    public function handle(ApiSyncPairingService $pairings): int
    {
        $count = $pairings->expireStale((int) $this->option('limit'));
        $this->info("Expired {$count} stale API synchronization session(s).");

        return self::SUCCESS;
    }
}
