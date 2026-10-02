<?php

namespace App\Console\Commands;

use App\Services\ProcurementOpportunityExpiryService;
use Illuminate\Console\Command;

class CloseExpiredProcurementOpportunities extends Command
{
    protected $signature = 'procurement:close-expired
        {--limit=250 : Maximum published opportunities to inspect per run}';

    protected $description = 'Close procurement opportunities whose application window has expired.';

    public function handle(ProcurementOpportunityExpiryService $expiry): int
    {
        $result = $expiry->closeExpired((int) $this->option('limit'));

        $this->info(sprintf(
            'Closed %d expired procurement opportunity(s); created %d Think Tank workflow event(s).',
            $result['closed'],
            $result['think_tank_events'],
        ));

        if ($result['applicant_close_notifications_supported']) {
            $this->info(sprintf(
                'Queued %d applicant closure notification(s).',
                $result['applicant_notifications'],
            ));
        } else {
            $this->line('Applicant closure emails were not queued because no reviewed closed-opportunity lifecycle template is registered.');
        }

        return self::SUCCESS;
    }
}
