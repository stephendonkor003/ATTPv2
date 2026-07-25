<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\MePerformanceReportIndicatorResult;
use Illuminate\Support\Collection;

class IndicatorAggregationService
{
    /**
     * Recalculate snapshots for one indicator and reporting owner. Only an
     * explicitly configured "sum" method is additive.
     */
    public function recalculate(string $indicatorId, ?string $thinkTankMemberId = null): void
    {
        $indicator = Indicator::query()->find($indicatorId);
        if (! $indicator) {
            return;
        }

        $rows = MePerformanceReportIndicatorResult::query()
            ->where('indicator_id', $indicatorId)
            ->whereHas('report', fn ($query) => $query->when(
                $thinkTankMemberId,
                fn ($ownerQuery) => $ownerQuery->where('think_tank_member_id', $thinkTankMemberId),
                fn ($ownerQuery) => $ownerQuery->whereNull('think_tank_member_id')
            ))
            ->with('report:id,think_tank_member_id,reporting_year,reporting_quarter')
            ->get()
            ->sortBy(fn ($row) => sprintf(
                '%04d-%s',
                (int) $row->report?->reporting_year,
                (string) $row->report?->reporting_quarter
            ))
            ->values();

        $programmeValues = collect();
        $yearValues = [];
        foreach ($rows as $row) {
            $year = (int) $row->report?->reporting_year;
            $actual = $row->actual_value === null ? null : (float) $row->actual_value;
            if ($actual !== null) {
                $programmeValues->push($actual);
                $yearValues[$year] ??= collect();
                $yearValues[$year]->push($actual);
            }

            $method = $row->aggregation_method ?: $indicator->aggregation_method ?: 'non_additive';
            $yearCumulative = $this->aggregate($yearValues[$year] ?? collect(), $method);
            $programmeCumulative = $this->aggregate($programmeValues, $method);
            if ($method === 'sum' && $programmeCumulative !== null && $indicator->baseline_value !== null) {
                $programmeCumulative += (float) $indicator->baseline_value;
            } elseif ($programmeCumulative === null && $indicator->baseline_value !== null) {
                $programmeCumulative = (float) $indicator->baseline_value;
            }

            $annualTarget = $row->annual_target ?? $indicator->annual_target;
            $achievement = $yearCumulative !== null
                && $annualTarget !== null
                && (float) $annualTarget !== 0.0
                    ? round(($yearCumulative / (float) $annualTarget) * 100, 2)
                    : null;

            $row->updateQuietly([
                'aggregation_method' => $method,
                'annual_target' => $annualTarget,
                'life_of_programme_target' => $row->life_of_programme_target
                    ?? $indicator->life_of_programme_target,
                'cumulative_year_result' => $yearCumulative,
                'cumulative_programme_result' => $programmeCumulative,
                'target_achievement_percent' => $achievement,
            ]);
        }
    }

    public function aggregate(Collection $values, string $method): ?float
    {
        $values = $values->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value);
        if ($values->isEmpty()) {
            return null;
        }

        return match ($method) {
            'sum' => (float) $values->sum(),
            'average' => (float) $values->average(),
            'minimum' => (float) $values->min(),
            'maximum' => (float) $values->max(),
            default => (float) $values->last(),
        };
    }
}
