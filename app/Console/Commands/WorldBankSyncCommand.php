<?php

namespace App\Console\Commands;

use App\Services\WorldBankCatalogSyncService;
use App\Services\WorldBankObservationSyncService;
use Illuminate\Console\Command;
use Throwable;

class WorldBankSyncCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'worldbank:sync
                            {--catalog : Sync World Bank topics, indicators, and countries}
                            {--indicator=* : Sync specific WB indicator IDs (example: SP.POP.TOTL)}
                            {--years=20 : Trailing years to sync when using --indicator or --full-range}
                            {--used : Sync indicators already used in local observation storage}
                            {--full-range : Use --years range when syncing used indicators}';

    /**
     * @var string
     */
    protected $description = 'Sync World Bank indicator catalog and observation data';

    public function __construct(
        protected WorldBankCatalogSyncService $worldBankCatalogSyncService,
        protected WorldBankObservationSyncService $worldBankObservationSyncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $yearTo = (int) now()->year;
        $years = max(1, (int) $this->option('years'));
        $yearFrom = $yearTo - $years + 1;

        try {
            if ($this->option('catalog')) {
                $this->info('Syncing World Bank catalog...');
                $catalog = $this->worldBankCatalogSyncService->syncCatalog();
                $this->line('Catalog synced: '
                    . $catalog['topics'] . ' topics, '
                    . $catalog['indicators'] . ' indicators, '
                    . $catalog['countries'] . ' countries.');
            }

            $requestedIndicators = array_values(array_filter((array) $this->option('indicator')));
            if (!empty($requestedIndicators)) {
                $this->info("Syncing " . count($requestedIndicators) . " requested indicator(s) from {$yearFrom} to {$yearTo}...");
                $results = $this->worldBankObservationSyncService->syncIndicators($requestedIndicators, $yearFrom, $yearTo);
                foreach ($results as $result) {
                    $this->line("- {$result['indicator']}: {$result['rows_processed']} rows ({$result['rows_with_values']} with values)");
                }

                return self::SUCCESS;
            }

            $shouldSyncUsedIndicators = (bool) $this->option('used')
                || (bool) $this->option('catalog')
                || empty($requestedIndicators);

            if ($shouldSyncUsedIndicators) {
                $usedYearFrom = (bool) $this->option('full-range')
                    ? $yearFrom
                    : max($yearTo - 2, $yearFrom);

                $this->info("Syncing used indicators from {$usedYearFrom} to {$yearTo}...");
                $results = $this->worldBankObservationSyncService->syncUsedIndicators($usedYearFrom, $yearTo);
                foreach ($results as $result) {
                    $this->line("- {$result['indicator']}: {$result['rows_processed']} rows ({$result['rows_with_values']} with values)");
                }
            }
        } catch (Throwable $exception) {
            $this->error('World Bank sync failed: ' . $exception->getMessage());
            return self::FAILURE;
        }

        $this->info('World Bank sync completed.');

        return self::SUCCESS;
    }
}

