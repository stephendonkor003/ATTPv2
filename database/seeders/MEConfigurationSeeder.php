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
            ['name' => 'Second', 'code' => 'SECOND', 'interval_unit' => 'second', 'interval_value' => 1, 'frequency_in_days' => null],
            ['name' => 'Minute', 'code' => 'MINUTE', 'interval_unit' => 'minute', 'interval_value' => 1, 'frequency_in_days' => null],
            ['name' => 'Hour', 'code' => 'HOUR', 'interval_unit' => 'hour', 'interval_value' => 1, 'frequency_in_days' => null],
            ['name' => 'Day', 'code' => 'DAY', 'interval_unit' => 'day', 'interval_value' => 1, 'frequency_in_days' => 1],
            ['name' => 'Week', 'code' => 'WEEK', 'interval_unit' => 'week', 'interval_value' => 1, 'frequency_in_days' => 7],
            ['name' => 'Month', 'code' => 'MONTH', 'interval_unit' => 'month', 'interval_value' => 1, 'frequency_in_days' => 30],
            ['name' => 'Quarterly', 'code' => 'QUARTERLY', 'interval_unit' => 'quarterly', 'interval_value' => 1, 'frequency_in_days' => 90],
            ['name' => 'Year', 'code' => 'YEAR', 'interval_unit' => 'year', 'interval_value' => 1, 'frequency_in_days' => 365],
            ['name' => 'Annual', 'code' => 'ANNUAL', 'interval_unit' => 'annual', 'interval_value' => 1, 'frequency_in_days' => 365],
            ['name' => 'Quinquennial', 'code' => 'QUINQUENNIAL', 'interval_unit' => 'quinquennial', 'interval_value' => 1, 'frequency_in_days' => 1825],
            ['name' => 'Once', 'code' => 'ONCE', 'interval_unit' => 'once', 'interval_value' => null, 'frequency_in_days' => null],
        ];

        foreach ($frequencies as $order => $freq) {
            ReportingFrequency::updateOrCreate(
                ['code' => $freq['code']],
                [
                    'name' => $freq['name'],
                    'interval_unit' => $freq['interval_unit'],
                    'interval_value' => $freq['interval_value'],
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
