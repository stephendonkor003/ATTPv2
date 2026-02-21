<?php

namespace Database\Seeders;

use App\Models\IndicatorLevel;
use App\Models\ReportingFrequency;
use App\Models\IndicatorUnit;
use App\Models\User;
use Illuminate\Database\Seeder;

class MEConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('email', 'amodonlimited@gmail.com')->value('id')
            ?? User::query()->value('id');

        $levels = [
            ['name' => 'Impact', 'description' => 'Highest level change'],
            ['name' => 'Outcome', 'description' => 'Medium-term change'],
            ['name' => 'Output', 'description' => 'Immediate deliverable'],
            ['name' => 'Activity', 'description' => 'Process/Activity level'],
        ];

        foreach ($levels as $order => $level) {
            IndicatorLevel::updateOrCreate(
                ['name' => $level['name']],
                [
                    'description' => $level['description'],
                    'sort_order' => $order,
                    'is_active' => true,
                    'created_by' => $creator,
                ]
            );
        }

        $frequencies = [
            ['name' => 'Monthly', 'code' => 'MONTHLY', 'frequency_in_days' => 30],
            ['name' => 'Quarterly', 'code' => 'QUARTERLY', 'frequency_in_days' => 90],
            ['name' => 'Bi-Annual', 'code' => 'BI_ANNUAL', 'frequency_in_days' => 182],
            ['name' => 'Annual', 'code' => 'ANNUAL', 'frequency_in_days' => 365],
        ];

        foreach ($frequencies as $order => $freq) {
            ReportingFrequency::updateOrCreate(
                ['code' => $freq['code']],
                [
                    'name' => $freq['name'],
                    'frequency_in_days' => $freq['frequency_in_days'],
                    'sort_order' => $order,
                    'is_active' => true,
                    'created_by' => $creator,
                ]
            );
        }

        $units = [
            ['name' => 'Percent', 'symbol' => '%'],
            ['name' => 'Number', 'symbol' => null],
            ['name' => 'Score', 'symbol' => null],
            ['name' => 'Index', 'symbol' => null],
            ['name' => 'USD', 'symbol' => '$'],
        ];

        foreach ($units as $order => $unit) {
            IndicatorUnit::updateOrCreate(
                ['name' => $unit['name']],
                [
                    'symbol' => $unit['symbol'],
                    'sort_order' => $order,
                    'is_active' => true,
                    'created_by' => $creator,
                ]
            );
        }
    }
}
