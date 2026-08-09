<?php

namespace App\Console\Commands;

use App\Services\AttpMelFrameworkInstaller;
use Illuminate\Console\Command;

class InstallAttpMelFramework extends Command
{
    protected $signature = 'mel:install-attp-framework';

    protected $description = 'Idempotently install or align the World Bank P179804 ATTP MEL Results Framework';

    public function handle(AttpMelFrameworkInstaller $installer): int
    {
        $result = $installer->install();
        $this->info(sprintf(
            'ATTP MEL framework %s installed: %d indicators and %d official target records.',
            $result['framework']->version,
            $result['indicators'],
            $result['targets']
        ));

        return self::SUCCESS;
    }
}
