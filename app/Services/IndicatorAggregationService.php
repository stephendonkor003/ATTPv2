<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\MePerformanceReport;
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

        $baseQuery = MePerformanceReportIndicatorResult::query()
            ->where('indicator_id', $indicatorId)
            ->whereHas('report', fn ($query) => $query
                ->whereIn('status', [
                    MePerformanceReport::STATUS_APPROVED,
                    MePerformanceReport::STATUS_ARCHIVED,
                ])
                ->when(
                    $thinkTankMemberId,
                    fn ($ownerQuery) => $ownerQuery->where('think_tank_member_id', $thinkTankMemberId),
                    fn ($ownerQuery) => $ownerQuery->whereNull('think_tank_member_id')
                ));

        MePerformanceReportIndicatorResult::query()
            ->where('indicator_id', $indicatorId)
            ->whereHas('report', fn ($query) => $query
                ->whereNotIn('status', [
                    MePerformanceReport::STATUS_APPROVED,
                    MePerformanceReport::STATUS_ARCHIVED,
                ])
                ->when(
                    $thinkTankMemberId,
                    fn ($ownerQuery) => $ownerQuery->where('think_tank_member_id', $thinkTankMemberId),
                    fn ($ownerQuery) => $ownerQuery->whereNull('think_tank_member_id')
                ))
            ->update([
                'cumulative_year_result' => null,
                'cumulative_programme_result' => null,
                'target_achievement_percent' => null,
            ]);

        $rows = $baseQuery
            ->with('report:id,think_tank_member_id,reporting_year,reporting_quarter')
            ->get()
            ->sortBy(fn ($row) => sprintf(
                '%04d-%s',
                (int) $row->report?->reporting_year,
                (string) $row->report?->reporting_quarter
            ))
            ->values();

        $programmeValues = collect();
        $programmeNumerators = collect();
        $programmeDenominators = collect();
        $yearValues = [];
        $yearNumerators = [];
        $yearDenominators = [];
        foreach ($rows as $row) {
            $year = (int) $row->report?->reporting_year;
            $actual = $row->actual_value === null ? null : (float) $row->actual_value;
            if ($actual !== null) {
                $programmeValues->push($actual);
                $yearValues[$year] ??= collect();
                $yearValues[$year]->push($actual);
            }

            if ($row->rollup_numerator !== null && $row->rollup_denominator !== null) {
                $programmeNumerators->push((float) $row->rollup_numerator);
                $programmeDenominators->push((float) $row->rollup_denominator);
                $yearNumerators[$year] ??= collect();
                $yearDenominators[$year] ??= collect();
                $yearNumerators[$year]->push((float) $row->rollup_numerator);
                $yearDenominators[$year]->push((float) $row->rollup_denominator);
            }

            $method = $row->aggregation_method ?: $indicator->aggregation_method ?: 'non_additive';
            if (in_array($method, ['percentage', 'ratio'], true)
                && ($yearDenominators[$year] ?? collect())->sum() > 0) {
                $yearCumulative = round(
                    ($yearNumerators[$year]->sum() / $yearDenominators[$year]->sum()) * 100,
                    4
                );
                $programmeCumulative = $programmeDenominators->sum() > 0
                    ? round(($programmeNumerators->sum() / $programmeDenominators->sum()) * 100, 4)
                    : null;
            } else {
                $yearCumulative = $this->aggregate($yearValues[$year] ?? collect(), $method);
                $programmeCumulative = $this->aggregate($programmeValues, $method);
            }
            if (! $indicator->is_cumulative) {
                $programmeCumulative = $actual;
            }
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
