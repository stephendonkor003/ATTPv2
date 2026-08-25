<?php

namespace App\Services;

use Illuminate\Support\Collection;

class MeConsolidationEngineService
{
    public function __construct(private readonly AttpMelResultsService $officialResults) {}

    /** @return array<string, mixed> */
    public function build(array $filters = []): array
    {
        $official = $this->officialResults->build([
            ...$filters,
            'report_type' => 'results_framework',
        ]);

        $indicatorRows = collect($official['rows'] ?? [])
            ->when(
                filled($filters['performance_status'] ?? null),
                fn (Collection $rows): Collection => $rows
                    ->where('classification.code', $filters['performance_status'])
                    ->values()
            )
            ->map(fn (array $row): array => $this->enrichIndicatorRow($row))
            ->values();
        $projectRows = $this->buildProjectRows($indicatorRows);
        $ratedIndicators = $indicatorRows->whereNotNull('achievement_percent');
        $cappedIndicatorAttainment = $ratedIndicators
            ->pluck('achievement_percent')
            ->map(fn ($value): float => min(100.0, max(0.0, (float) $value)));
        $reportedOrganizations = $indicatorRows
            ->flatMap(fn (array $row): Collection => collect($row['reporting_organizations'] ?? []))
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $totalExpected = (int) $indicatorRows->sum('expected_organizations');
        $totalReported = (int) $indicatorRows->sum('reported_organizations');
        $evidenceCount = (int) $indicatorRows->sum('evidence_count');
        $verifiedEvidenceCount = (int) $indicatorRows->sum('verified_evidence_count');
        $latestApproval = $indicatorRows
            ->pluck('latest_approved_at')
            ->filter()
            ->sortDesc()
            ->first();
        $quality = [
            'missing_targets' => $indicatorRows->whereNull('target_value')->where('result_count', '>', 0)->count(),
            'not_reported' => $indicatorRows->where('result_count', 0)->count(),
            'non_additive_or_qualitative' => $indicatorRows->filter(fn (array $row): bool => in_array(
                $row['indicator']->value_type,
                ['milestone', 'text'],
                true
            ) || $row['indicator']->organization_rollup_method === 'non_additive')->count(),
            'missing_required_evidence' => $indicatorRows->filter(fn (array $row): bool => (bool) $row['indicator']->requires_evidence
                && $row['result_count'] > 0
                && $row['evidence_count'] === 0)->count(),
            'incomplete_reporting' => $indicatorRows->where('reporting_completeness', '<', 100)->count(),
            'weighted_values_without_weights' => $indicatorRows->filter(function (array $row): bool {
                if ($row['indicator']->organization_rollup_method !== 'weighted_average') {
                    return false;
                }

                return collect($row['source_contributions'] ?? [])->contains(
                    fn (array $source): bool => $source['rollup_numerator'] === null
                        || $source['rollup_denominator'] === null
                        || (float) $source['rollup_denominator'] <= 0
                );
            })->count(),
        ];

        return $official + [
            'indicatorRows' => $indicatorRows,
            'projectRows' => $projectRows,
            'engineSummary' => [
                'project_count' => $projectRows->where('key', '!=', 'pdo')->count(),
                'results_area_count' => $projectRows->count(),
                'indicator_count' => $indicatorRows->count(),
                'reported_indicator_count' => $indicatorRows->where('result_count', '>', 0)->count(),
                'approved_contribution_count' => (int) $indicatorRows->sum('result_count'),
                'organization_count' => $reportedOrganizations->count(),
                'reporting_organizations' => $reportedOrganizations,
                'average_achievement' => $cappedIndicatorAttainment->isEmpty()
                    ? null
                    : round((float) $cappedIndicatorAttainment->avg(), 1),
                'reporting_completeness' => $totalExpected > 0
                    ? round(($totalReported / $totalExpected) * 100, 1)
                    : 0.0,
                'evidence_count' => $evidenceCount,
                'verified_evidence_count' => $verifiedEvidenceCount,
                'evidence_verification_rate' => $evidenceCount > 0
                    ? round(($verifiedEvidenceCount / $evidenceCount) * 100, 1)
                    : null,
                'beneficiary_count' => (int) $indicatorRows->sum('beneficiary_count'),
                'latest_approval_at' => $latestApproval,
            ],
            'quality' => $quality,
            'performanceDistribution' => $indicatorRows
                ->groupBy('classification.code')
                ->map(function (Collection $rows): array {
                    $classification = $rows->first()['classification'];

                    return [
                        'code' => $classification['code'],
                        'label' => $classification['label'],
                        'color' => $this->safeColor($classification['color'] ?? null),
                        'count' => $rows->count(),
                    ];
                })
                ->values(),
            'filters' => $filters,
        ];
    }

    /**
     * Project/component consolidation is a scorecard. Raw indicator actuals
     * are deliberately never summed because their units may be incompatible.
     */
    public function buildProjectRows(Collection $indicatorRows): Collection
    {
        return $indicatorRows
            ->groupBy(fn (array $row): string => (string) ($row['indicator']->project_component_id ?: 'pdo'))
            ->map(function (Collection $rows, string $key): array {
                $indicator = $rows->first()['indicator'];
                $project = $indicator->projectComponent;
                $rated = $rows->whereNotNull('achievement_percent');
                $cappedAttainment = $rated
                    ->pluck('achievement_percent')
                    ->map(fn ($value): float => min(100.0, max(0.0, (float) $value)));
                $reportedCount = $rows->where('result_count', '>', 0)->count();
                $totalExpected = (int) $rows->sum('expected_organizations');
                $totalReported = (int) $rows->sum('reported_organizations');
                $onTrackCount = $rows->filter(fn (array $row): bool => in_array(
                    $row['classification']['code'],
                    ['achieved', 'on_track'],
                    true
                ))->count();
                $attentionCount = $rows->filter(fn (array $row): bool => in_array(
                    $row['classification']['code'],
                    ['needs_attention', 'off_track'],
                    true
                ))->count();
                $organizations = $rows
                    ->flatMap(fn (array $row): Collection => collect($row['reporting_organizations'] ?? []))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();
                $evidenceCount = (int) $rows->sum('evidence_count');
                $verifiedEvidence = (int) $rows->sum('verified_evidence_count');
                $status = $this->projectStatus($rated->count(), $onTrackCount, $attentionCount);

                return [
                    'key' => $key,
                    'project' => $project,
                    'code' => $project?->project_id ?: 'PDO',
                    'name' => $project?->name ?: 'Project Development Objective / Cross-project results',
                    'program' => $project?->program?->name,
                    'portfolio' => $project?->program?->sector?->name,
                    'indicator_count' => $rows->count(),
                    'reported_indicator_count' => $reportedCount,
                    'rated_indicator_count' => $rated->count(),
                    'average_achievement' => $cappedAttainment->isEmpty()
                        ? null
                        : round((float) $cappedAttainment->avg(), 1),
                    'on_track_count' => $onTrackCount,
                    'attention_count' => $attentionCount,
                    'not_rated_count' => $rows->count() - $rated->count(),
                    'reporting_completeness' => $totalExpected > 0
                        ? round(($totalReported / $totalExpected) * 100, 1)
                        : 0.0,
                    'approved_contribution_count' => (int) $rows->sum('result_count'),
                    'organization_count' => $organizations->count(),
                    'organizations' => $organizations,
                    'evidence_count' => $evidenceCount,
                    'verified_evidence_count' => $verifiedEvidence,
                    'evidence_verification_rate' => $evidenceCount > 0
                        ? round(($verifiedEvidence / $evidenceCount) * 100, 1)
                        : null,
                    'achievement_count' => (int) $rows->sum('achievement_count'),
                    'beneficiary_count' => (int) $rows->sum('beneficiary_count'),
                    'female_beneficiaries' => (int) $rows->sum('female_beneficiaries'),
                    'male_beneficiaries' => (int) $rows->sum('male_beneficiaries'),
                    'latest_approved_at' => $rows->pluck('latest_approved_at')->filter()->sortDesc()->first(),
                    'status' => $status,
                    'performance_mix' => $rows->groupBy('classification.code')->map->count(),
                    'indicator_rows' => $rows->values(),
                    'calculation_note' => 'Project performance is the unweighted average of rated indicator target-attainment percentages, with each indicator capped at 100%. Raw indicator actuals are never added across unlike units.',
                ];
            })
            ->sortBy(fn (array $row): string => $row['key'] === 'pdo' ? '0000' : $row['code'])
            ->values();
    }

    private function enrichIndicatorRow(array $row): array
    {
        $target = $row['target_value'];
        $actual = $row['actual'];

        return $row + [
            'variance_value' => is_numeric($actual) && $target !== null
                ? round((float) $actual - (float) $target, 4)
                : null,
            'unit_label' => $row['indicator']->unit?->symbol ?: $row['indicator']->unit?->name,
            'time_aggregation_label' => $row['indicator']->aggregationMethodLabel(),
            'organization_rollup_label' => $row['indicator']->organization_rollup_method
                ? (\App\Models\Indicator::ORGANIZATION_ROLLUP_METHODS[$row['indicator']->organization_rollup_method]
                    ?? str($row['indicator']->organization_rollup_method)->headline()->toString())
                : 'Not configured',
        ];
    }

    /** @return array{code:string,label:string,color:string} */
    private function projectStatus(int $ratedCount, int $onTrackCount, int $attentionCount): array
    {
        if ($ratedCount === 0) {
            return ['code' => 'not_rated', 'label' => 'Not yet rated', 'color' => '#64748b'];
        }
        if ($attentionCount > 0 && $onTrackCount > 0) {
            return ['code' => 'mixed', 'label' => 'Mixed performance', 'color' => '#d8941d'];
        }
        if ($attentionCount > 0) {
            return ['code' => 'attention', 'label' => 'Attention required', 'color' => '#c43d38'];
        }

        return ['code' => 'on_track', 'label' => 'On track', 'color' => '#15935d'];
    }

    private function safeColor(?string $color): string
    {
        return is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color)
            ? $color
            : '#64748b';
    }
}
