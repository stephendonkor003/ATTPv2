<?php

namespace Database\Seeders;

use App\Services\AttpMelFrameworkInstaller;
use Illuminate\Database\Seeder;

class AttpMelFrameworkSeeder extends Seeder
{
    public function run(): void
    {
        app(AttpMelFrameworkInstaller::class)->install();
        $this->call(AttpMelThinkTankReportingSeeder::class);
    }
}
