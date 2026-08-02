<?php

namespace App\Services;

use App\Models\Indicator;
use Illuminate\Support\Collection;

class MeConsolidatedReportingService
{
    public function build(Collection $reports): Collection
    {
        return $reports
            ->flatMap(function ($report) {
                return $report->indicatorResults->each(
                    fn ($result) => $result->setRelation('report', $report)
                );
            })
            ->groupBy('indicator_id')
            ->map(function (Collection $sourceResults) {
                // One organization contributes at most one approved value for an
                // indicator and period. If legacy/misconfigured forms overlap,
                // the most recently approved record is authoritative and the
                // suppressed duplicate count remains visible for audit follow-up.
                $results = $this->oneResultPerOrganization($sourceResults);
                $indicator = $results->first()?->indicator;
                $method = $indicator?->organization_rollup_method ?: 'sum';
                $actuals = $results->whereNotNull('actual_value')->map(fn ($result) => (float) $result->actual_value);
                $value = $this->rollup($results, $method);
                $achievements = $results->flatMap->achievements;
                $breakdowns = $achievements->flatMap->breakdowns;

                return [
                    'indicator' => $indicator,
                    'rollup_method' => $method,
                    'rollup_label' => Indicator::ORGANIZATION_ROLLUP_METHODS[$method] ?? str($method)->headline()->toString(),
                    'value' => $value,
                    'organization_count' => $results->pluck('report.think_tank_member_id')->filter()->unique()->count(),
                    'reported_value_count' => $actuals->count(),
                    'duplicate_result_count' => max(0, $sourceResults->count() - $results->count()),
                    'target' => $this->commonTarget($results),
                    'achievement_count' => $achievements->count(),
                    'beneficiary_count' => (int) $breakdowns->sum('beneficiary_count'),
                    'themes' => $achievements->flatMap(fn ($achievement) => $achievement->priority_themes ?? [])->countBy()->sortDesc(),
                    'countries' => $achievements->pluck('country')->filter()->countBy()->sortDesc(),
                    'recs' => $achievements->pluck('rec')->merge($breakdowns->pluck('rec'))->filter()->countBy()->sortDesc(),
                    'geographic_scopes' => $achievements->pluck('geographic_scope')->merge($breakdowns->pluck('geographic_scope'))->filter()->countBy()->sortDesc(),
                    'institution_types' => $breakdowns->pluck('implementing_institution_type')->filter()->countBy()->sortDesc(),
                    'institutions' => $breakdowns->pluck('implementing_institution')->filter()->countBy()->sortDesc(),
                    'gender' => $breakdowns->groupBy(fn ($row) => $row->gender ?: 'not_disaggregated')
                        ->map(fn ($rows) => (int) $rows->sum('beneficiary_count')),
                    'age_groups' => $breakdowns->groupBy(fn ($row) => $row->age_group ?: 'not_disaggregated')
                        ->map(fn ($rows) => (int) $rows->sum('beneficiary_count')),
                    'stakeholders' => $breakdowns->groupBy(fn ($row) => $row->stakeholder_category ?: 'not_disaggregated')
                        ->map(fn ($rows) => (int) $rows->sum('beneficiary_count')),
                ];
            })
            ->sortBy(fn (array $row) => $row['indicator']?->indicator_code)
            ->values();
    }

    private function oneResultPerOrganization(Collection $results): Collection
    {
        return $results
            ->groupBy(fn ($result) => (string) ($result->report?->think_tank_member_id ?: 'unowned-'.$result->id))
            ->map(function (Collection $organizationResults) {
                return $organizationResults
                    ->sortByDesc(function ($result): string {
                        $report = $result->report;
                        $timestamp = $report?->approved_at
                            ?? $report?->updated_at
                            ?? $report?->created_at;

                        return ($timestamp?->format('YmdHis.u') ?? '').'|'.(string) $report?->id;
                    })
                    ->first();
            })
            ->filter()
            ->values();
    }

    private function rollup(Collection $results, string $method): ?float
    {
        $withValues = $results->whereNotNull('actual_value');
        if ($withValues->isEmpty() || $method === 'non_additive') {
            return null;
        }

        return match ($method) {
            'latest' => (float) $withValues->sortByDesc(fn ($result) => $result->report?->approved_at ?? $result->report?->updated_at)->first()->actual_value,
            'average' => round((float) $withValues->avg('actual_value'), 4),
            'weighted_average' => $this->weightedAverage($withValues),
            'minimum' => (float) $withValues->min('actual_value'),
            'maximum' => (float) $withValues->max('actual_value'),
            default => (float) $withValues->sum('actual_value'),
        };
    }

    private function weightedAverage(Collection $results): ?float
    {
        $weighted = $results->whereNotNull('rollup_numerator')->whereNotNull('rollup_denominator');
        $denominator = (float) $weighted->sum('rollup_denominator');

        return $weighted->isEmpty() || $denominator <= 0
            ? null
            : round(((float) $weighted->sum('rollup_numerator') / $denominator) * 100, 4);
    }

    private function commonTarget(Collection $results): ?float
    {
        $targets = $results->pluck('target_value')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value)->unique();

        return $targets->count() === 1 ? $targets->first() : null;
    }
}
