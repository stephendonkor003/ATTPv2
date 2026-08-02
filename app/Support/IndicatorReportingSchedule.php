<?php

namespace App\Support;

use App\Models\Indicator;

final class IndicatorReportingSchedule
{
    public static function isDueInQuarter(Indicator $indicator, string $quarter): bool
    {
        $cadence = $indicator->frequency?->indicatorCadenceKey();

        return match ($cadence) {
            'monthly', 'quarterly' => in_array($quarter, ['Q1', 'Q2', 'Q3', 'Q4'], true),
            'semi_annual' => in_array($quarter, ['Q2', 'Q4'], true),
            'annual' => $quarter === 'Q4',
            default => false,
        };
    }

    public static function isDueInPeriod(Indicator $indicator, string $periodType, string $periodLabel): bool
    {
        $cadence = $indicator->frequency?->indicatorCadenceKey();

        return match ($periodType) {
            'quarter' => in_array($periodLabel, ['Q1', 'Q2', 'Q3', 'Q4'], true)
                && in_array($cadence, ['monthly', 'quarterly'], true),
            'semi_annual' => in_array($periodLabel, ['H1', 'H2'], true)
                && $cadence === 'semi_annual',
            'annual' => $periodLabel === 'ANNUAL' && $cadence === 'annual',
            default => false,
        };
    }

    public static function cadenceLabel(Indicator $indicator): string
    {
        return $indicator->frequency?->indicatorCadenceLabel() ?: 'Not configured';
    }
}
