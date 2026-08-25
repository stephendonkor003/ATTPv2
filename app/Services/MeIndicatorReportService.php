<?php

namespace App\Services;

use Illuminate\Support\Collection;

class MeIndicatorReportService
{
    public function __construct(private readonly MeConsolidationEngineService $consolidationEngine) {}

    /** @return array<string, mixed> */
    public function build(array $filters = [], string $mode = 'consolidated'): array
    {
        $mode = $mode === 'individual' ? 'individual' : 'consolidated';
        $engine = $this->consolidationEngine->build($filters);
        $allIndicatorRows = collect($engine['indicatorRows'] ?? []);
        $hasIndividualSelection = $mode === 'individual' && filled($filters['indicator_id'] ?? null);
        $indicatorRows = $mode === 'individual' && ! $hasIndividualSelection
            ? collect()
            : $allIndicatorRows;
        $selectedIndicatorRow = $mode === 'individual' ? $indicatorRows->first() : null;
        $contributionRows = $this->contributionRows($indicatorRows);
        $evidenceRows = $this->evidenceRows($contributionRows);
        $summary = $this->summary($indicatorRows, $contributionRows, $evidenceRows);
        $quality = $this->quality($indicatorRows);

        return array_merge($engine, [
            'mode' => $mode,
            'allIndicatorRows' => $allIndicatorRows,
            'indicatorRows' => $indicatorRows,
            'selectedIndicatorRow' => $selectedIndicatorRow,
            'projectRows' => $this->consolidationEngine->buildProjectRows($indicatorRows),
            'contributionRows' => $contributionRows,
            'evidenceRows' => $evidenceRows,
            'reportSummary' => $summary,
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
            'hasReportData' => $indicatorRows->isNotEmpty(),
        ]);
    }

    public function contributionRows(Collection $indicatorRows): Collection
    {
        return $indicatorRows->flatMap(function (array $row): Collection {
            $indicator = $row['indicator'];
            $project = $indicator->projectComponent;

            return collect($row['source_contributions'] ?? [])->map(function (array $source) use ($row, $indicator, $project): array {
                return [
                    'portfolio' => $project?->program?->sector?->name,
                    'program' => $project?->program?->name,
                    'project_code' => $project?->project_id ?: 'PDO',
                    'project_name' => $project?->name ?: 'Project Development Objective / Cross-project results',
                    'project_component_id' => $project?->id,
                    'project_component_code' => $project?->project_id ?: 'PDO',
                    'project_component_name' => $project?->name ?: 'Project Development Objective / Cross-project results',
                    'results_level' => $indicator->results_level,
                    'results_level_label' => $indicator->resultsLevelLabel(),
                    'indicator_id' => $indicator->id,
                    'indicator_code' => $indicator->indicator_code,
                    'indicator_name' => $indicator->name,
                    'value_type' => $indicator->value_type,
                    'unit' => $row['unit_label'] ?? null,
                    'baseline' => $row['baseline'] ?? null,
                    'target_value' => $row['target_value'] ?? null,
                    'target_text' => $row['target_text'] ?? null,
                    'consolidated_actual' => $row['actual'] ?? null,
                    'achievement_percent' => $row['achievement_percent'] ?? null,
                    'performance_status' => data_get($row, 'classification.label'),
                    'result_id' => $source['id'] ?? null,
                    'source_result_id' => $source['id'] ?? null,
                    'organization' => $source['organization'] ?? 'Secretariat / Internal',
                    'country' => $source['country'] ?? null,
                    'period' => $source['period'] ?? null,
                    'actual' => $source['actual'] ?? null,
                    'rollup_numerator' => $source['rollup_numerator'] ?? null,
                    'rollup_denominator' => $source['rollup_denominator'] ?? null,
                    'data_source' => $source['data_source'] ?? null,
                    'evidence_count' => (int) ($source['evidence_count'] ?? 0),
                    'verified_evidence_count' => (int) ($source['verified_evidence_count'] ?? 0),
                    'achievement_count' => (int) ($source['achievement_count'] ?? 0),
                    'approved_at' => $source['approved_at'] ?? null,
                    'evidence_links' => collect($source['evidence_links'] ?? [])->values(),
                    'calculation_note' => $row['calculation_note'] ?? null,
                ];
            });
        })->values();
    }

    public function evidenceRows(Collection $contributionRows): Collection
    {
        return $contributionRows->flatMap(function (array $contribution): Collection {
            return collect($contribution['evidence_links'] ?? [])->map(fn (array $evidence): array => [
                'indicator_id' => $contribution['indicator_id'],
                'indicator_code' => $contribution['indicator_code'],
                'indicator_name' => $contribution['indicator_name'],
                'project_code' => $contribution['project_code'],
                'result_id' => $contribution['result_id'],
                'source_result_id' => $contribution['source_result_id'],
                'organization' => $contribution['organization'],
                'country' => $contribution['country'],
                'period' => $contribution['period'],
                'evidence_key' => $evidence['key'] ?? null,
                'source' => $evidence['source'] ?? null,
                'evidence_source' => $evidence['source'] ?? null,
                'title' => $evidence['title'] ?? null,
                'status' => $evidence['status'] ?? null,
                'verified' => (bool) ($evidence['verified'] ?? false),
                'approved_at' => $contribution['approved_at'],
            ]);
        })->unique(fn (array $row): string => implode('|', [
            $row['indicator_code'],
            $row['result_id'],
            $row['evidence_key'],
        ]))->values();
    }

    private function summary(Collection $indicatorRows, Collection $contributionRows, Collection $evidenceRows): array
    {
        $rated = $indicatorRows->whereNotNull('achievement_percent');
        $cappedAttainment = $rated->pluck('achievement_percent')
            ->map(fn ($value): float => min(100.0, max(0.0, (float) $value)));
        $organizations = $contributionRows->pluck('organization')->filter()->unique()->sort()->values();
        $expected = (int) $indicatorRows->sum('expected_organizations');
        $reported = (int) $indicatorRows->sum('reported_organizations');
        $verifiedEvidence = $evidenceRows->where('verified', true)->count();

        return [
            'indicator_count' => $indicatorRows->count(),
            'reported_indicator_count' => $indicatorRows->where('result_count', '>', 0)->count(),
            'approved_contribution_count' => $contributionRows->count(),
            'organization_count' => $organizations->count(),
            'reporting_organizations' => $organizations,
            'average_achievement' => $cappedAttainment->isEmpty()
                ? null
                : round((float) $cappedAttainment->avg(), 1),
            'reporting_completeness' => $expected > 0
                ? round(($reported / $expected) * 100, 1)
                : 0.0,
            'evidence_count' => $evidenceRows->count(),
            'verified_evidence_count' => $verifiedEvidence,
            'evidence_verification_rate' => $evidenceRows->isNotEmpty()
                ? round(($verifiedEvidence / $evidenceRows->count()) * 100, 1)
                : null,
            'achievement_count' => (int) $indicatorRows->sum('achievement_count'),
            'beneficiary_count' => (int) $indicatorRows->sum('beneficiary_count'),
            'female_beneficiaries' => (int) $indicatorRows->sum('female_beneficiaries'),
            'male_beneficiaries' => (int) $indicatorRows->sum('male_beneficiaries'),
            'latest_approval_at' => $indicatorRows->pluck('latest_approved_at')->filter()->sortDesc()->first(),
            'on_track_count' => $indicatorRows->filter(fn (array $row): bool => in_array(
                data_get($row, 'classification.code'),
                ['achieved', 'on_track'],
                true
            ))->count(),
            'attention_count' => $indicatorRows->filter(fn (array $row): bool => in_array(
                data_get($row, 'classification.code'),
                ['needs_attention', 'off_track'],
                true
            ))->count(),
        ];
    }

    private function quality(Collection $indicatorRows): array
    {
        return [
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
    }

    private function safeColor(?string $color): string
    {
        return is_string($color) && preg_match('/^#[0-9a-f]{6}$/i', $color)
            ? $color
            : '#64748b';
    }
}
