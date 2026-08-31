<?php

namespace App\Console\Commands;

use App\Services\ProcurementSubmissionScreeningAutomation;
use App\Services\ProcurementSubmissionScreeningService;
use Illuminate\Console\Command;

class QueuePendingThreepapScreenings extends Command
{
    protected $signature = 'threepap:screen-pending
                            {--limit=25 : Maximum submissions or interrupted runs to recover}';

    protected $description = 'Queue recent procurement submissions and recover interrupted 3PAP screenings';

    public function handle(
        ProcurementSubmissionScreeningAutomation $automation,
        ProcurementSubmissionScreeningService $screeningService,
    ): int {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 500],
        ]);

        if ($limit === false) {
            $this->error('The --limit option must be an integer between 1 and 500.');

            return self::INVALID;
        }

        if (! (bool) config('services.threepap_checker.automatic.enabled', true)) {
            $this->info('Automatic 3PAP screening is disabled; no submissions were queued.');

            return self::SUCCESS;
        }

        if (! $screeningService->isConfigured()) {
            $this->warn('3PAP is not configured; recent unscreened submissions remain eligible for the next recovery run.');

            return self::SUCCESS;
        }

        $summary = $automation->recoverPending($limit);

        $this->table(
            ['Queued missing', 'Re-dispatched interrupted', 'Skipped'],
            [[
                number_format($summary['queued']),
                number_format($summary['redispatched']),
                number_format($summary['skipped']),
            ]],
        );

        return self::SUCCESS;
    }
}
