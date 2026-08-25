<?php

namespace App\Console\Commands;

use App\Services\ApiSync\ApiSyncInvitationService;
use Illuminate\Console\Command;

class MaintainApiSyncInvitations extends Command
{
    protected $signature = 'api-sync:invitations:maintain {--limit=500 : Maximum invitations to inspect}';

    protected $description = 'Expire unapproved AU-PReMIS invitations and prune obsolete replay nonces';

    public function handle(ApiSyncInvitationService $invitations): int
    {
        $result = $invitations->expireAndPrune((int) $this->option('limit'));
        $this->info("Expired {$result['expired_invitations']} invitation(s); pruned {$result['pruned_nonces']} replay nonce(s).");

        return self::SUCCESS;
    }
}
