<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\ThinkTankProcurementSpreadsheetMigrationService;
use Illuminate\Database\Seeder;

final class AboveTenThousandThinkTankProcurementSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(ThinkTankProcurementSpreadsheetMigrationService::class)
            ->seed(ThinkTankProcurementSpreadsheetMigrationService::BAND_AT_OR_ABOVE);

        $this->command?->info(sprintf(
            'Seeded %d FY2026 Think Tank procurement items at or above USD 10,000 across %d plans (USD %s).',
            $result['unique_items_in_band'],
            $result['plans_touched'],
            $result['amount_usd'],
        ));
    }
}
