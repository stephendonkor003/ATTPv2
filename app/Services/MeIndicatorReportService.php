<?php

namespace App\Services;

use App\Models\Indicator;
use App\Models\MeDataSubmission;
use App\Models\MeDataSubmissionAnswer;
use Illuminate\Support\Collection;

class MeIndicatorReportService
{
    public function __construct(private readonly MeConsolidationEngineService $consolidationEngine) {}

    /** @return array<string, mixed> */
    public function build(array $filters = [], string $mode = 'consolidated', bool $includeSubmissionRows = false): array
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
        $submissionFilters = $filters;
        $submissionFilters['indicator_ids'] = $indicatorRows
            ->pluck('indicator.id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return array_merge($engine, [
            'mode' => $mode,
            'allIndicatorRows' => $allIndicatorRows,
            'indicatorRows' => $indicatorRows,
            'selectedIndicatorRow' => $selectedIndicatorRow,
            'projectRows' => $this->consolidationEngine->buildProjectRows($indicatorRows),
            'contributionRows' => $contributionRows,
            'evidenceRows' => $evidenceRows,
            'thinkTankSubmissionRows' => $includeSubmissionRows
                && ($mode !== 'individual' || filled($filters['indicator_id'] ?? null))
                ? $this->thinkTankSubmissionRows($submissionFilters)
                : collect(),
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

    /**
     * Return submitted Think Tank form answers as a separate audit ledger.
     * These rows never feed the official aggregates; those remain approved-only.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    private function thinkTankSubmissionRows(array $filters): Collection
    {
        $indicatorIds = collect($filters['indicator_ids'] ?? [])
            ->filter()
            ->unique()
            ->values();

        if ($indicatorIds->isEmpty()) {
            return collect();
        }

        $submissions = MeDataSubmission::query()
            ->whereHas('answers')
            ->where(function ($statusQuery): void {
                $statusQuery->where(function ($workflowQuery): void {
                    $workflowQuery->whereNotNull('workflow_status')
                        ->whereNotIn('workflow_status', ['', 'draft']);
                })->orWhere(function ($legacyQuery): void {
                    $legacyQuery
                        ->where(fn ($workflowQuery) => $workflowQuery
                            ->whereNull('workflow_status')
                            ->orWhere('workflow_status', ''))
                        ->whereNotIn('status', ['', 'draft']);
                });
            })
            ->with([
                'assignment.thinkTank',
                'assignment.collection.reportingPeriod',
                'assignment.collection.form',
                'submittedBy',
                'reviews.reviewer',
                'answers.field.formSection',
                'answers.field.indicator',
                'indicatorResults' => fn ($query) => $query->whereIn('indicator_id', $indicatorIds)
                    ->with('achievements'),
                'evidence',
            ])
            ->when(
                filled($filters['think_tank_id'] ?? null),
                fn ($query) => $query->whereHas('assignment', fn ($assignmentQuery) => $assignmentQuery
                    ->where('think_tank_member_id', $filters['think_tank_id']))
            )
            ->when(
                filled($filters['reporting_period_id'] ?? null),
                fn ($query) => $query->whereHas('assignment.collection', fn ($collectionQuery) => $collectionQuery
                    ->where('reporting_period_id', $filters['reporting_period_id']))
            )
            ->when(
                blank($filters['reporting_period_id'] ?? null) && filled($filters['reporting_year'] ?? null),
                fn ($query) => $query->whereHas('assignment.collection.reportingPeriod', fn ($periodQuery) => $periodQuery
                    ->where('reporting_year', $filters['reporting_year']))
            )
            ->when(
                filled($filters['country'] ?? null),
                fn ($query) => $query->where(function ($countryQuery) use ($filters, $indicatorIds): void {
                    $countryQuery->whereHas('assignment.thinkTank', fn ($tankQuery) => $tankQuery
                        ->where('country', $filters['country']))
                        ->orWhereHas('indicatorResults', fn ($resultQuery) => $resultQuery
                            ->whereIn('indicator_id', $indicatorIds)
                            ->whereHas('achievements', fn ($achievementQuery) => $achievementQuery
                                ->where('country', $filters['country'])));
                })
            )
            ->when(
                filled($filters['thematic_area'] ?? null),
                fn ($query) => $query->whereHas('indicatorResults', fn ($resultQuery) => $resultQuery
                    ->whereIn('indicator_id', $indicatorIds)
                    ->whereHas('achievements', fn ($achievementQuery) => $achievementQuery
                        ->whereJsonContains('priority_themes', $filters['thematic_area'])))
            )
            ->orderByDesc('submitted_at')
            ->get();
        $indicatorsById = Indicator::query()
            ->whereIn('id', $indicatorIds)
            ->get()
            ->keyBy(fn (Indicator $indicator): string => (string) $indicator->id);

        return $submissions
            ->flatMap(function (MeDataSubmission $submission) use ($indicatorIds, $indicatorsById): Collection {
                $schemaSnapshot = is_array($submission->schema_snapshot) ? $submission->schema_snapshot : [];
                $snapshotFields = collect($schemaSnapshot['fields'] ?? [])->keyBy('field_key');
                $answerContext = $submission->answers->mapWithKeys(function (MeDataSubmissionAnswer $answer) use ($snapshotFields): array {
                    $snapshotField = $snapshotFields->get($answer->field_key, []);
                    $indicatorId = (string) (data_get($snapshotField, 'indicator_id')
                        ?: $answer->field?->indicator_id
                        ?: '');

                    return [(string) $answer->field_key => [
                        'answer' => $answer,
                        'snapshot_field' => $snapshotField,
                        'indicator_id' => $indicatorId,
                    ]];
                });
                $answersByIndicator = $answerContext
                    ->filter(fn (array $context): bool => $context['indicator_id'] !== ''
                        && $indicatorIds->contains($context['indicator_id']))
                    ->groupBy('indicator_id');

                return $answersByIndicator->map(function (Collection $indicatorAnswers, string $indicatorId) use (
                    $submission,
                    $schemaSnapshot,
                    $answerContext,
                    $indicatorsById
                ): array {
                    $indicator = $indicatorsById->get($indicatorId);
                    $formAnswers = $answerContext
                        ->filter(fn (array $context): bool => $context['indicator_id'] === ''
                            || $context['indicator_id'] === $indicatorId)
                        ->sortBy(function (array $context): array {
                            $snapshotField = $context['snapshot_field'];
                            $answer = $context['answer'];

                            return [
                                (int) data_get($snapshotField, 'form_section.sort_order', $answer->field?->formSection?->sort_order ?? 0),
                                (int) data_get($snapshotField, 'sort_order', $answer->field?->sort_order ?? 0),
                                (string) (data_get($snapshotField, 'label') ?: $answer->field?->label ?: $answer->field_key),
                            ];
                        })
                        ->map(function (array $context): array {
                            /** @var MeDataSubmissionAnswer $answer */
                            $answer = $context['answer'];
                            $field = $answer->field;
                            $snapshotField = $context['snapshot_field'];
                            $value = is_array($answer->value) && array_key_exists('value', $answer->value)
                                ? $answer->value['value']
                                : $answer->value;
                            $fieldType = (string) (data_get($snapshotField, 'field_type') ?: $field?->field_type ?: 'response');
                            $isUpload = in_array($fieldType, ['file', 'image'], true);

                            if ($isUpload) {
                                $displayValue = 'Attachment submitted; see evidence links.';
                            } elseif (is_array($value)) {
                                $displayValue = collect($value)
                                    ->flatten()
                                    ->filter(fn ($item): bool => is_scalar($item) && trim((string) $item) !== '')
                                    ->map(fn ($item): string => (string) $item)
                                    ->join(', ');
                            } elseif (is_scalar($value)) {
                                $displayValue = (string) $value;
                            } else {
                                $displayValue = '';
                            }

                            return [
                                'section' => data_get($snapshotField, 'form_section.name')
                                    ?: $field?->formSection?->name
                                    ?: data_get($snapshotField, 'section')
                                    ?: $field?->section
                                    ?: 'General information',
                                'label' => data_get($snapshotField, 'label') ?: $field?->label ?: $answer->field_key,
                                'type' => $fieldType,
                                'value' => trim($displayValue) !== '' ? $displayValue : 'No response recorded',
                            ];
                        })
                        ->values();
                    $result = $submission->indicatorResults->firstWhere('indicator_id', $indicatorId);
                    $evidence = $submission->evidence
                        ->filter(fn ($item): bool => blank($item->indicator_id)
                            || (string) $item->indicator_id === $indicatorId)
                        ->map(fn ($item): array => [
                            'title' => $item->document_title ?: $item->original_name ?: 'Submission evidence',
                            'status' => $item->verification_status ?: 'pending',
                            'file_size' => $item->file_size,
                            'created_at' => $item->created_at,
                        ])
                        ->values();
                    $currentForm = $submission->assignment?->collection?->form;
                    $currentPeriod = $submission->assignment?->collection?->reportingPeriod;

                    return [
                        'submission_id' => (string) $submission->id,
                        'indicator_id' => $indicatorId,
                        'indicator_code' => $indicator?->indicator_code,
                        'indicator_name' => $indicator?->name,
                        'think_tank' => $submission->assignment?->thinkTank?->name ?: 'Think Tank not recorded',
                        'country' => $submission->assignment?->thinkTank?->country,
                        'status' => $submission->effectiveStatus(),
                        'revision' => (int) ($submission->current_version ?: $submission->revision ?: 1),
                        'form_code' => data_get($schemaSnapshot, 'form.code') ?: $currentForm?->code,
                        'form_title' => data_get($schemaSnapshot, 'form.title') ?: $currentForm?->title,
                        'form_version' => data_get($schemaSnapshot, 'form.version') ?: $currentForm?->version,
                        'period' => data_get($schemaSnapshot, 'reporting_period.label') ?: $currentPeriod?->label,
                        'period_start' => data_get($schemaSnapshot, 'reporting_period.period_start') ?: $currentPeriod?->period_start?->toDateString(),
                        'period_end' => data_get($schemaSnapshot, 'reporting_period.period_end') ?: $currentPeriod?->period_end?->toDateString(),
                        'reporting_year' => $currentPeriod?->reporting_year,
                        'collection_status' => $submission->assignment?->collection?->status,
                        'collection_instructions' => $submission->assignment?->collection?->instructions,
                        'opens_at' => $submission->assignment?->collection?->opens_at,
                        'due_at' => $submission->assignment?->collection?->due_at,
                        'closes_at' => $submission->assignment?->collection?->closes_at,
                        'assigned_at' => $submission->assignment?->assigned_at,
                        'submitted_at' => $submission->submitted_at,
                        'submitted_by' => $submission->submittedBy?->name,
                        'submission_notes' => $submission->notes,
                        'review_notes' => $submission->review_notes,
                        'actual' => $result?->actual_text ?? $result?->actual_value,
                        'result_review_status' => $result?->review_status,
                        'approved_at' => $result?->approved_at,
                        'answers' => $formAnswers,
                        'reviews' => $submission->reviews
                            ->map(fn ($review): array => [
                                'action' => $review->action,
                                'from_status' => $review->from_status,
                                'to_status' => $review->to_status,
                                'comments' => $review->comments,
                                'reviewer' => $review->reviewer?->name,
                                'reviewed_at' => $review->reviewed_at,
                            ])
                            ->values(),
                        'evidence' => $evidence,
                    ];
                })->values();
            })
            ->sortByDesc(fn (array $row): int => $row['submitted_at']?->getTimestamp() ?? 0)
            ->values();
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
