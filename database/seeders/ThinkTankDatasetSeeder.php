<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ThinkTankDatasetSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ThinkDatasetSeeder::class);
    }
}
