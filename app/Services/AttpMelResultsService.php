<?php

namespace App\Services;

use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\IndicatorResult;
use App\Models\IndicatorTarget;
use App\Models\MeFramework;
use App\Models\MeReportingPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttpMelResultsService
{
    /** @return array<string, mixed> */
    public function build(array $filters = [], ?string $forcedThinkTankId = null): array
    {
        $framework = MeFramework::query()->current()->with('thresholds')->first();
        if (! $framework) {
            return [
                'framework' => null,
                'rows' => collect(),
                'summary' => $this->emptySummary(),
                'analytics' => $this->emptyAnalytics(),
            ];
        }

        $period = filled($filters['reporting_period_id'] ?? null)
            ? MeReportingPeriod::query()->find($filters['reporting_period_id'])
            : null;
        $projectYear = max(1, min(99, (int) ($filters['project_year'] ?? 1)));
        $reportingYear = $period?->reporting_year ?: ($filters['reporting_year'] ?? null);
        $thinkTankId = $forcedThinkTankId ?: ($filters['think_tank_id'] ?? null);

        $indicatorQuery = Indicator::query()
            ->where('framework_id', $framework->id)
            ->where('is_active', true)
            ->with(['projectComponent:id,project_id,name', 'unit:id,name,symbol', 'approvedReferenceSheet', 'calculationRules', 'targets' => fn ($q) => $q->where('approval_status', 'approved')])
            ->when(filled($filters['component_id'] ?? null), fn (Builder $q) => $q->where('project_component_id', $filters['component_id']))
            ->when(filled($filters['indicator_id'] ?? null), fn (Builder $q) => $q->whereKey($filters['indicator_id']))
            ->orderBy('display_order');

        $activeThinkTanks = ConsortiumThinkTank::query()
            ->where('status', 'active')
            ->when(
                filled($filters['country'] ?? null),
                fn (Builder $query) => $query->where('country', $filters['country'])
            )
            ->count();
        $rows = $indicatorQuery->get()->map(function (Indicator $indicator) use (
            $framework, $period, $projectYear, $reportingYear, $thinkTankId, $filters, $activeThinkTanks
        ): array {
            $target = $this->targetFor($indicator, $projectYear, $reportingYear, $thinkTankId);
            $sourceIndicators = $this->sourceIndicators($indicator);
            $periodResults = $this->results($sourceIndicators, $period, $reportingYear, $thinkTankId, $filters, false);
            $cumulativeResults = $indicator->is_cumulative
                ? $this->results($sourceIndicators, $period, $reportingYear, $thinkTankId, $filters, true)
                : $periodResults;
            $historyResults = $indicator->is_cumulative
                ? $cumulativeResults
                : $this->results($sourceIndicators, $period, $reportingYear, $thinkTankId, $filters, true);

            $rule = $indicator->calculationRules->firstWhere('is_active', true);
            $periodResults = $this->applyQualificationFilter($periodResults, data_get($rule?->configuration, 'achievement_filter'));
            $cumulativeResults = $this->applyQualificationFilter($cumulativeResults, data_get($rule?->configuration, 'achievement_filter'));
            $historyResults = $this->applyQualificationFilter($historyResults, data_get($rule?->configuration, 'achievement_filter'));
            $periodActual = $this->aggregate($indicator, $periodResults);
            $cumulativeActual = $this->aggregate($indicator, $cumulativeResults);
            $evidence = $periodResults->flatMap(
                fn (IndicatorResult $result) => $result->dataSubmission?->evidence ?? collect()
            )->unique('id');
            $breakdowns = $periodResults->flatMap->achievements->flatMap->breakdowns;
            $actual = $indicator->is_cumulative ? $cumulativeActual : $periodActual;
            $targetValue = $target?->target_value !== null ? (float) $target->target_value : null;
            $targetText = $target?->target_text;
            $achievement = $this->achievement($indicator, $actual, $targetValue, $targetText);
            $classification = $this->classification($framework, $achievement, $indicator, $actual, $targetText);
            [$reportedOrganizations, $expectedOrganizations] = $this->reportingCoverage(
                $indicator,
                $periodResults,
                $thinkTankId,
                $activeThinkTanks
            );
            $completeness = $expectedOrganizations > 0
                ? min(100, round(($reportedOrganizations / $expectedOrganizations) * 100, 1))
                : 0;

            return [
                'indicator' => $indicator,
                'target' => $target,
                'target_value' => $targetValue,
                'target_text' => $targetText,
                'baseline' => $indicator->baseline_value,
                'period_actual' => $periodActual,
                'cumulative_actual' => $cumulativeActual,
                'actual' => $actual,
                'achievement_percent' => $achievement,
                'variance' => $targetValue !== null && $actual !== null ? round($actual - $targetValue, 4) : null,
                'trend' => $this->trend($indicator, $historyResults),
                'classification' => $classification,
                'result_count' => $periodResults->count(),
                'evidence_count' => $evidence->count(),
                'verified_evidence_count' => $evidence->where('verification_status', 'verified')->count(),
                'female_beneficiaries' => (int) $breakdowns->where('gender', 'female')->sum('beneficiary_count'),
                'male_beneficiaries' => (int) $breakdowns->where('gender', 'male')->sum('beneficiary_count'),
                'reported_organizations' => $reportedOrganizations,
                'expected_organizations' => $expectedOrganizations,
                'reporting_completeness' => $completeness,
                'calculation_note' => $rule
                    ? 'System-calculated from approved qualifying records using '.$rule->calculation_key.'.'
                    : 'Calculated from approved indicator results only.',
            ];
        });

        $rows = $this->forReportType($rows, (string) ($filters['report_type'] ?? 'results_framework'));

        $numericRows = $rows->filter(fn (array $row): bool => $row['achievement_percent'] !== null);
        $pdoRows = $rows->filter(fn (array $row): bool => $row['indicator']->results_level === 'pdo');
        $summary = [
            'indicator_count' => $rows->count(),
            'pdo_count' => $pdoRows->count(),
            'approved_result_count' => $rows->sum('result_count'),
            'evidence_count' => $rows->sum('evidence_count'),
            'verified_evidence_count' => $rows->sum('verified_evidence_count'),
            'average_achievement' => $numericRows->isEmpty() ? null : round($numericRows->avg('achievement_percent'), 1),
            'on_track_count' => $rows->filter(fn (array $row): bool => in_array($row['classification']['code'], ['achieved', 'on_track'], true))->count(),
            'attention_count' => $rows->filter(fn (array $row): bool => in_array($row['classification']['code'], ['needs_attention', 'off_track'], true))->count(),
            'reported_indicator_count' => $rows->where('result_count', '>', 0)->count(),
            'not_reported_count' => $rows->where('result_count', 0)->count(),
            'on_track_rate' => $numericRows->isEmpty()
                ? null
                : round(($numericRows->filter(fn (array $row): bool => in_array($row['classification']['code'], ['achieved', 'on_track'], true))->count() / $numericRows->count()) * 100, 1),
            'average_completeness' => $rows->isEmpty() ? 0 : round($rows->avg('reporting_completeness'), 1),
            'evidence_verification_rate' => $rows->sum('evidence_count') > 0
                ? round(($rows->sum('verified_evidence_count') / $rows->sum('evidence_count')) * 100, 1)
                : null,
        ];
        $analytics = $this->analytics($rows, $summary);

        return compact('framework', 'rows', 'summary', 'analytics', 'period', 'projectYear', 'reportingYear', 'thinkTankId');
    }

    private function sourceIndicators(Indicator $indicator): Collection
    {
        $rule = $indicator->calculationRules->firstWhere('is_active', true);
        $codes = collect($rule?->configuration['source_indicator_codes'] ?? [])->filter();

        return $codes->isEmpty()
            ? collect([$indicator])
            : Indicator::query()->whereIn('indicator_code', $codes)->get();
    }

    private function results(
        Collection $indicators,
        ?MeReportingPeriod $period,
        ?int $reportingYear,
        ?string $thinkTankId,
        array $filters,
        bool $cumulative
    ): Collection {
        $query = IndicatorResult::query()
            ->approved()
            ->whereIn('indicator_id', $indicators->pluck('id'))
            ->with(['achievements.breakdowns', 'thinkTank:id,name,country', 'dataSubmission.evidence']);
        if ($thinkTankId) {
            $query->where('think_tank_member_id', $thinkTankId);
        }
        if ($period) {
            $cumulative
                ? $query->whereDate('period_end', '<=', $period->period_end)
                : $query->where('reporting_period_id', $period->id);
        } elseif ($reportingYear) {
            $cumulative
                ? $query->whereDate('period_end', '<=', Carbon::create($reportingYear, 12, 31))
                : $query->whereYear('period_end', $reportingYear);
        }
        if (filled($filters['country'] ?? null)) {
            $country = $filters['country'];
            $query->where(function (Builder $q) use ($country): void {
                $q->whereHas('thinkTank', fn (Builder $tank) => $tank->where('country', $country))
                    ->orWhereHas('achievements', fn (Builder $achievement) => $achievement->where('country', $country));
            });
        }
        if (filled($filters['thematic_area'] ?? null)) {
            $theme = $filters['thematic_area'];
            $query->whereHas('achievements', fn (Builder $achievement) => $achievement->whereJsonContains('priority_themes', $theme));
        }
        if (! $period && in_array($filters['report_type'] ?? null, ['semi_annual', 'annual'], true)) {
            $query->where('period_type', $filters['report_type']);
        }

        return $query->get()
            ->groupBy(function (IndicatorResult $result): string {
                if (filled($result->deduplication_key)) {
                    return (string) $result->deduplication_key;
                }
                $sourceIdentity = filled($result->source_record_id)
                    ? implode('|', [$result->source_record_type, $result->source_record_id])
                    : 'aggregate';

                return implode('|', array_filter([
                    $result->indicator_id,
                    $result->think_tank_member_id ?: 'secretariat',
                    $result->reporting_period_id ?: $result->period_label,
                    $sourceIdentity,
                ]));
            })
            ->map(fn (Collection $duplicates) => $duplicates->sortByDesc('approved_at')->first())
            ->values();
    }

    private function aggregate(Indicator $indicator, Collection $results): mixed
    {
        if ($results->isEmpty()) {
            return null;
        }
        if ($indicator->value_type === 'milestone') {
            return $results->sortByDesc('approved_at')->first()?->actual_text;
        }
        if ($indicator->value_type === 'percentage') {
            $weighted = $results->whereNotNull('rollup_numerator')->whereNotNull('rollup_denominator');
            $denominator = (float) $weighted->sum('rollup_denominator');
            if ($denominator > 0) {
                return round(((float) $weighted->sum('rollup_numerator') / $denominator) * 100, 4);
            }

            return $results->whereNotNull('actual_value')->isEmpty()
                ? null
                : round((float) $results->whereNotNull('actual_value')->avg('actual_value'), 4);
        }
        if ($indicator->value_type === 'boolean') {
            return $results->contains(fn (IndicatorResult $result): bool => (float) $result->actual_value > 0);
        }
        $values = $results->pluck('actual_value')->filter(fn ($value) => $value !== null)->map(fn ($value) => (float) $value);
        if ($values->isEmpty()) {
            return null;
        }

        $latest = $results
            ->sortBy(fn (IndicatorResult $result): string => sprintf(
                '%s|%s|%s',
                (string) ($result->getRawOriginal('period_end') ?? ''),
                (string) ($result->getRawOriginal('approved_at') ?? ''),
                (string) ($result->getRawOriginal('id') ?? '')
            ))
            ->last();

        return match ($indicator->aggregation_method) {
            'average' => round((float) $values->avg(), 4),
            'minimum' => (float) $values->min(),
            'maximum' => (float) $values->max(),
            'latest', 'percentage', 'ratio', 'non_additive' => $latest?->actual_value !== null
                ? (float) $latest->actual_value
                : null,
            default => (float) $values->sum(),
        };
    }

    private function targetFor(Indicator $indicator, int $projectYear, ?int $reportingYear, ?string $thinkTankId): ?IndicatorTarget
    {
        $targets = $indicator->targets->where('approval_status', 'approved');
        if ($thinkTankId) {
            $allocated = $targets->where('target_scope', 'think_tank')->where('think_tank_member_id', $thinkTankId);
            if ($allocated->isNotEmpty()) {
                $targets = $allocated;
            } else {
                $targets = $targets->where('target_scope', 'project');
            }
        } else {
            $targets = $targets->where('target_scope', 'project');
        }

        $latest = fn (Collection $matches): ?IndicatorTarget => $matches
            ->sortByDesc(fn (IndicatorTarget $target): string => sprintf(
                '%010d|%s|%s',
                (int) $target->revision,
                $target->effective_from?->format('Ymd') ?? '00000000',
                $target->created_at?->format('YmdHis.u') ?? ''
            ))
            ->first();

        return $latest($targets->where('project_year', $projectYear))
            ?? ($reportingYear ? $latest($targets->where('reporting_year', $reportingYear)) : null)
            ?? $latest($targets->where('period_label', 'END'));
    }

    /** @return array{direction:string,label:string,change:?float,current:mixed,previous:mixed} */
    private function trend(Indicator $indicator, Collection $results): array
    {
        $periods = $results
            ->groupBy(fn (IndicatorResult $result): string => (string) (
                $result->reporting_period_id
                ?: $result->period_end?->format('Y-m-d')
                ?: $result->period_label
                ?: $result->id
            ))
            ->map(fn (Collection $periodResults): array => [
                'results' => $periodResults,
                'order' => $periodResults->max(fn (IndicatorResult $result): int => $result->period_end?->getTimestamp()
                    ?? $result->approved_at?->getTimestamp()
                    ?? 0
                ),
            ])
            ->sortBy('order')
            ->values();

        if ($periods->count() < 2) {
            return [
                'direction' => 'none',
                'label' => 'No prior period',
                'change' => null,
                'current' => $periods->isEmpty() ? null : $this->aggregate($indicator, $periods->last()['results']),
                'previous' => null,
            ];
        }

        $current = $this->aggregate($indicator, $periods->last()['results']);
        $previous = $this->aggregate($indicator, $periods->get($periods->count() - 2)['results']);

        if ($indicator->value_type === 'boolean') {
            $direction = $current === $previous ? 'flat' : ($current ? 'up' : 'down');

            return [
                'direction' => $direction,
                'label' => $direction === 'flat' ? 'No change' : ($direction === 'up' ? 'Improved' : 'Declined'),
                'change' => null,
                'current' => $current,
                'previous' => $previous,
            ];
        }

        if ($indicator->value_type === 'milestone') {
            return [
                'direction' => $current === $previous ? 'flat' : 'changed',
                'label' => $current === $previous ? 'No change' : 'Milestone updated',
                'change' => null,
                'current' => $current,
                'previous' => $previous,
            ];
        }

        if (! is_numeric($current) || ! is_numeric($previous)) {
            return ['direction' => 'none', 'label' => 'Not available', 'change' => null, 'current' => $current, 'previous' => $previous];
        }

        $change = round((float) $current - (float) $previous, 4);
        $direction = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat');
        $suffix = $indicator->value_type === 'percentage' ? ' pp' : '';

        return [
            'direction' => $direction,
            'label' => match ($direction) {
                'up' => '+'.number_format($change, 2).$suffix,
                'down' => number_format($change, 2).$suffix,
                default => 'No change',
            },
            'change' => $change,
            'current' => $current,
            'previous' => $previous,
        ];
    }

    private function forReportType(Collection $rows, string $reportType): Collection
    {
        return match ($reportType) {
            'pdo_performance' => $rows->filter(
                fn (array $row): bool => $row['indicator']->results_level === 'pdo'
            )->values(),
            'component_performance' => $rows->filter(
                fn (array $row): bool => $row['indicator']->results_level !== 'pdo'
            )->values(),
            'reporting_compliance' => $rows->sortBy('reporting_completeness')->values(),
            'evidence_verification' => $rows->filter(
                fn (array $row): bool => $row['indicator']->requires_evidence || $row['evidence_count'] > 0
            )->sortByDesc('result_count')->values(),
            'gender_disaggregation' => $rows->filter(
                fn (array $row): bool => ($row['female_beneficiaries'] + $row['male_beneficiaries']) > 0
            )->values(),
            'think_tank_performance' => $rows->filter(
                fn (array $row): bool => in_array($row['indicator']->reporting_source, ['think_tank', 'both', 'system_calculated'], true)
            )->values(),
            'target_vs_actual' => $rows->sortByDesc(
                fn (array $row): float => abs((float) ($row['variance'] ?? 0))
            )->filter(fn (array $row): bool => $row['target_value'] !== null && is_numeric($row['actual']))->values(),
            default => $rows,
        };
    }

    private function achievement(Indicator $indicator, mixed $actual, ?float $target, ?string $targetText): ?float
    {
        if ($actual === null) {
            return null;
        }
        if ($indicator->value_type === 'boolean') {
            $normalizedTarget = strtolower(trim((string) $targetText));
            if (in_array($normalizedTarget, ['yes', 'true', '1'], true)) {
                $expected = true;
            } elseif (in_array($normalizedTarget, ['no', 'false', '0'], true)) {
                $expected = false;
            } elseif ($target !== null) {
                $expected = $target > 0;
            } else {
                return null;
            }

            return $actual === $expected ? 100.0 : 0.0;
        }
        if (! is_numeric($actual) || $target === null || $target == 0.0) {
            return null;
        }

        return round(((float) $actual / $target) * 100, 2);
    }

    private function classification(MeFramework $framework, ?float $achievement, Indicator $indicator, mixed $actual, ?string $targetText): array
    {
        if ($indicator->value_type === 'milestone') {
            return $actual === null
                ? ['code' => 'not_rated', 'label' => 'Not reported', 'color' => '#64748b']
                : ['code' => 'qualitative_result', 'label' => 'Qualitative result', 'color' => '#6b63a8'];
        }
        if ($achievement === null) {
            return $actual === null
                ? ['code' => 'not_rated', 'label' => 'Not reported', 'color' => '#64748b']
                : ['code' => 'not_rated', 'label' => 'Actual available; target missing', 'color' => '#64748b'];
        }
        $threshold = $framework->thresholds->first(fn ($item): bool => ($item->minimum_percent === null || $achievement >= (float) $item->minimum_percent)
            && ($item->maximum_percent === null || $achievement <= (float) $item->maximum_percent));

        return $threshold
            ? [
                'code' => $threshold->code,
                'label' => $threshold->label,
                'color' => $this->safeColor($threshold->color),
            ]
            : ['code' => 'not_rated', 'label' => 'Not rated', 'color' => '#64748b'];
    }

    private function applyQualificationFilter(Collection $results, ?array $filter): Collection
    {
        if (! $filter) {
            return $results;
        }

        return $results->filter(function (IndicatorResult $result) use ($filter): bool {
            return $result->achievements->contains(function ($achievement) use ($filter): bool {
                return $achievement->breakdowns->contains(function ($breakdown) use ($filter): bool {
                    $dimensions = (array) $breakdown->additional_dimensions;
                    foreach ($filter as $key => $expected) {
                        if (($dimensions[$key] ?? null) !== $expected) {
                            return false;
                        }
                    }

                    return true;
                });
            });
        })->values();
    }

    private function emptySummary(): array
    {
        return [
            'indicator_count' => 0,
            'pdo_count' => 0,
            'approved_result_count' => 0,
            'evidence_count' => 0,
            'verified_evidence_count' => 0,
            'average_achievement' => null,
            'on_track_count' => 0,
            'attention_count' => 0,
            'reported_indicator_count' => 0,
            'not_reported_count' => 0,
            'on_track_rate' => null,
            'average_completeness' => 0,
            'evidence_verification_rate' => null,
        ];
    }

    /** @return array{0:int,1:int} */
    private function reportingCoverage(
        Indicator $indicator,
        Collection $results,
        ?string $thinkTankId,
        int $activeThinkTanks
    ): array {
        if ($thinkTankId) {
            return [$results->isEmpty() ? 0 : 1, 1];
        }

        $thinkTankReporters = $results->pluck('think_tank_member_id')->filter()->unique()->count();
        $secretariatReported = $results->contains(
            fn (IndicatorResult $result): bool => blank($result->think_tank_member_id)
        );

        return match ($indicator->reporting_source) {
            'secretariat' => [$results->isEmpty() ? 0 : 1, 1],
            'both' => [$thinkTankReporters + ($secretariatReported ? 1 : 0), max(1, $activeThinkTanks + 1)],
            'think_tank', 'system_calculated' => [
                $thinkTankReporters ?: ($results->isEmpty() ? 0 : 1),
                max(1, $activeThinkTanks),
            ],
            default => [$results->isEmpty() ? 0 : 1, 1],
        };
    }

    /** @return array<string, mixed> */
    private function analytics(Collection $rows, array $summary): array
    {
        $performance = $rows
            ->groupBy(fn (array $row): string => $row['classification']['code'])
            ->map(function (Collection $group): array {
                $classification = $group->first()['classification'];

                return [
                    'code' => $classification['code'],
                    'label' => $classification['label'],
                    'color' => $this->safeColor($classification['color']),
                    'count' => $group->count(),
                ];
            })
            ->sortBy(function (array $item): int {
                $position = array_search($item['code'], [
                    'achieved', 'on_track', 'needs_attention', 'off_track', 'qualitative_result', 'not_rated',
                ], true);

                return $position === false ? 99 : $position;
            })
            ->values()
            ->all();

        $components = $rows
            ->groupBy(fn (array $row): string => (string) ($row['indicator']->project_component_id ?: 'pdo'))
            ->map(function (Collection $group): array {
                $first = $group->first()['indicator'];
                $numeric = $group->filter(fn (array $row): bool => $row['achievement_percent'] !== null);

                return [
                    'key' => (string) ($first->project_component_id ?: 'pdo'),
                    'label' => $first->projectComponent?->name ?: 'Project Development Objective',
                    'short_label' => $first->projectComponent?->project_id ?: 'PDO',
                    'indicator_count' => $group->count(),
                    'reported_count' => $group->where('result_count', '>', 0)->count(),
                    'average_achievement' => $numeric->isEmpty() ? null : round((float) $numeric->avg('achievement_percent'), 1),
                    'average_completeness' => round((float) $group->avg('reporting_completeness'), 1),
                ];
            })
            ->values()
            ->all();

        $attainment = $rows
            ->filter(fn (array $row): bool => $row['achievement_percent'] !== null)
            ->map(fn (array $row): array => [
                'code' => $row['indicator']->indicator_code,
                'name' => $row['indicator']->name,
                'achievement' => round((float) $row['achievement_percent'], 1),
                'color' => $this->safeColor($row['classification']['color']),
            ])
            ->values()
            ->all();

        $gender = [
            'female' => (int) $rows->sum('female_beneficiaries'),
            'male' => (int) $rows->sum('male_beneficiaries'),
        ];

        $trends = collect(['up', 'down', 'flat', 'changed', 'none'])
            ->mapWithKeys(fn (string $direction): array => [
                $direction => $rows->where('trend.direction', $direction)->count(),
            ])
            ->all();

        $attention = $rows
            ->filter(fn (array $row): bool => $row['result_count'] > 0 && (
                in_array($row['classification']['code'], ['needs_attention', 'off_track'], true)
                || $row['reporting_completeness'] < 100
            ))
            ->sortBy(fn (array $row): array => [
                $row['achievement_percent'] ?? PHP_FLOAT_MAX,
                $row['reporting_completeness'],
            ])
            ->take(5)
            ->map(fn (array $row): array => [
                'code' => $row['indicator']->indicator_code,
                'name' => $row['indicator']->name,
                'achievement' => $row['achievement_percent'],
                'completeness' => $row['reporting_completeness'],
                'classification' => $row['classification'],
            ])
            ->values()
            ->all();

        return [
            'performance' => $performance,
            'components' => $components,
            'attainment' => $attainment,
            'gender' => $gender,
            'trends' => $trends,
            'attention' => $attention,
            'quality' => [
                'reporting_completeness' => $summary['average_completeness'],
                'evidence_verification' => $summary['evidence_verification_rate'],
            ],
        ];
    }

    private function emptyAnalytics(): array
    {
        return [
            'performance' => [],
            'components' => [],
            'attainment' => [],
            'gender' => ['female' => 0, 'male' => 0],
            'trends' => ['up' => 0, 'down' => 0, 'flat' => 0, 'changed' => 0, 'none' => 0],
            'attention' => [],
            'quality' => ['reporting_completeness' => 0, 'evidence_verification' => null],
        ];
    }

    private function safeColor(?string $color): string
    {
        return is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color)
            ? $color
            : '#64748b';
    }
}
