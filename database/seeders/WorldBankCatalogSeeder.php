<?php

namespace Database\Seeders;

use App\Services\WorldBankCatalogSyncService;
use App\Services\WorldBankObservationSyncService;
use Illuminate\Database\Seeder;
use Throwable;

class WorldBankCatalogSeeder extends Seeder
{
    /**
     * Seed World Bank catalog and a small default observation set.
     */
    public function run(): void
    {
        /** @var WorldBankCatalogSyncService $catalogSync */
        $catalogSync = app(WorldBankCatalogSyncService::class);
        /** @var WorldBankObservationSyncService $observationSync */
        $observationSync = app(WorldBankObservationSyncService::class);

        try {
            $summary = $catalogSync->syncCatalog();
            $this->command?->info(
                "World Bank catalog synced: {$summary['topics']} topics, {$summary['indicators']} indicators, {$summary['countries']} countries."
            );

            $defaultIndicators = [
                'SP.POP.TOTL',
                'NY.GDP.MKTP.CD',
                'SL.UEM.TOTL.ZS',
                'AG.LND.AGRI.ZS',
                'EN.ATM.CO2E.PC',
            ];

            $yearTo = (int) now()->year;
            $yearFrom = max(1960, $yearTo - 19);

            $results = $observationSync->syncIndicators($defaultIndicators, $yearFrom, $yearTo);
            $this->command?->info('Seeded default World Bank observations for ' . count($results) . ' indicators.');
        } catch (Throwable $exception) {
            $this->command?->warn('World Bank catalog seeding skipped: ' . $exception->getMessage());
        }
    }
}
