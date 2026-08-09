<?php

namespace App\Services;

use App\Models\IndicatorResult;
use App\Models\MeDataQualityFinding;
use App\Models\MeDataSubmission;
use App\Models\MeDataSubmissionAnswer;
use App\Models\MeDataSubmissionReview;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class MeDataQualityService
{
    public const RULES = [
        'negative_count' => ['severity' => 'error', 'label' => 'Negative result value', 'guidance' => 'Count and numeric results cannot be below zero. Correct the reported value or return the submission.'],
        'percentage_above_100' => ['severity' => 'error', 'label' => 'Percentage above 100', 'guidance' => 'Percentage indicators must remain between 0 and 100. Check the numerator, denominator and entered value.'],
        'invalid_date_value' => ['severity' => 'error', 'label' => 'Invalid reporting date', 'guidance' => 'The submitted date cannot be interpreted. Correct it using a valid calendar date.'],
        'required_evidence_missing' => ['severity' => 'error', 'label' => 'Required evidence missing', 'guidance' => 'Attach at least one valid Means of Verification document before approval.'],
        'percentage_missing_denominator' => ['severity' => 'warning', 'label' => 'Percentage denominator missing', 'guidance' => 'Add a positive denominator and numerator so consolidated percentages can be weighted correctly.'],
        'possible_duplicate_result' => ['severity' => 'warning', 'label' => 'Possible duplicate result', 'guidance' => 'Compare the indicator, organisation and reporting period with the approved record before resolving.'],
        'missing_gender' => ['severity' => 'warning', 'label' => 'Gender disaggregation missing', 'guidance' => 'Provide gender or sex-disaggregated values where required by the approved Indicator Reference Sheet.'],
        'missing_country' => ['severity' => 'warning', 'label' => 'Country disaggregation missing', 'guidance' => 'Provide the required country or location breakdown.'],
        'missing_thematic_classification' => ['severity' => 'warning', 'label' => 'Thematic classification missing', 'guidance' => 'Provide the thematic or priority classification required by the Indicator Reference Sheet.'],
        'date_outside_period' => ['severity' => 'warning', 'label' => 'Date outside reporting period', 'guidance' => 'Confirm the activity date or correct it so it falls inside the selected reporting period.'],
    ];

    /** @return Collection<int, MeDataQualityFinding> */
    public function evaluate(MeDataSubmission $submission): Collection
    {
        return DB::transaction(function () use ($submission): Collection {
            $lockedSubmission = MeDataSubmission::query()->lockForUpdate()->findOrFail($submission->id);

            return $this->evaluateLocked($lockedSubmission);
        });
    }

    /** @return Collection<int, MeDataQualityFinding> */
    private function evaluateLocked(MeDataSubmission $submission): Collection
    {
        $submission->loadMissing([
            'assignment.collection.reportingPeriod',
            'assignment.collection.form.fields.indicator',
            'answers.field.indicator',
            'indicatorResults.indicator.approvedReferenceSheet',
            'evidence',
        ]);
        $submission->dataQualityFindings()->where('status', 'open')->update([
            'status' => 'superseded',
            'resolution_notes' => 'Superseded automatically by a later data-quality evaluation.',
            'resolved_at' => now(),
        ]);

        $findings = collect();
        foreach ($submission->indicatorResults as $result) {
            if ($result->actual_value !== null && (float) $result->actual_value < 0) {
                $findings->push($this->finding($submission, $result, 'negative_count', 'error',
                    'A reported result cannot be negative.'));
            }
            if ($result->indicator?->value_type === 'percentage'
                && $result->actual_value !== null
                && (float) $result->actual_value > 100) {
                $findings->push($this->finding($submission, $result, 'percentage_above_100', 'error',
                    'A percentage result cannot exceed 100.'));
            }
            if ($result->indicator?->value_type === 'percentage'
                && ($result->rollup_denominator === null || (float) $result->rollup_denominator <= 0)) {
                $findings->push($this->finding($submission, $result, 'percentage_missing_denominator', 'warning',
                    'Provide a numerator and a positive denominator so the official percentage can be weighted correctly.'));
            }
            if ($this->isPotentialDuplicate($result)) {
                $findings->push($this->finding($submission, $result, 'possible_duplicate_result', 'warning',
                    'Another approved result exists for this indicator, Think Tank, and reporting period. Confirm that this is not a duplicate.'));
            }
            $requiredDimensions = collect($result->indicator?->approvedReferenceSheet?->disaggregation)
                ->map(fn ($dimension) => strtolower((string) $dimension));
            foreach ([
                'gender' => ['gender', 'sex'],
                'country' => ['country', 'countries', 'location'],
                'thematic_classification' => ['theme', 'thematic', 'priority'],
            ] as $rule => $keys) {
                if ($requiredDimensions->contains(fn (string $dimension): bool => collect($keys)->contains(
                    fn (string $key): bool => str_contains($dimension, $key)
                )) && ! $this->submissionHasDimension($submission, $keys)) {
                    $findings->push($this->finding(
                        $submission,
                        $result,
                        'missing_'.$rule,
                        'warning',
                        'The IRS requests '.str_replace('_', ' ', $rule).' disaggregation, but no matching response was detected. Confirm the supporting details.'
                    ));
                }
            }
        }

        $uploadAnswers = $submission->answers->filter(fn (MeDataSubmissionAnswer $answer): bool => $answer->field?->isUpload() && $this->hasUpload($answer->value));
        $requiresEvidence = $submission->indicatorResults->contains(
            fn (IndicatorResult $result): bool => (bool) $result->indicator?->requires_evidence
        );
        if ($requiresEvidence && $uploadAnswers->isEmpty() && $submission->evidence->isEmpty()) {
            $findings->push($this->finding($submission, null, 'required_evidence_missing', 'error',
                'At least one Means of Verification file is required for the reported indicator results.'));
        }

        $period = $submission->assignment?->collection?->reportingPeriod;
        foreach ($submission->answers as $answer) {
            if (! $period || ! $answer->field?->isTemporal()) {
                continue;
            }
            $value = data_get($answer->value, 'value', $answer->value);
            if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                continue;
            }
            try {
                $date = \Illuminate\Support\Carbon::parse($value);
            } catch (Throwable) {
                $findings->push($this->finding($submission, null, 'invalid_date_value', 'error',
                    "The date entered for {$answer->field->label} is not a valid calendar date.", $answer->field_key));

                continue;
            }
            if ($date->lt($period->period_start) || $date->gt($period->period_end)) {
                $findings->push($this->finding($submission, null, 'date_outside_period', 'warning',
                    "The date entered for {$answer->field->label} falls outside the reporting period.", $answer->field_key));
            }
        }

        $status = $submission->effectiveStatus();
        MeDataSubmissionReview::query()->create([
            'submission_id' => $submission->id,
            'submission_version' => max(1, (int) $submission->current_version),
            'from_status' => $status,
            'to_status' => $status,
            'action' => 'dqa_evaluated',
            'comments' => $findings->isEmpty()
                ? 'Automated data-quality checks completed with no findings.'
                : 'Automated data-quality checks completed with '.$findings->count().' '.str('finding')->plural($findings->count()).'.',
            'metadata' => [
                'finding_count' => $findings->count(),
                'blocking_count' => $findings->where('severity', 'error')->count(),
                'warning_count' => $findings->where('severity', 'warning')->count(),
                'rules_checked' => array_keys(self::RULES),
            ],
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return $findings;
    }

    private function isPotentialDuplicate(IndicatorResult $result): bool
    {
        return IndicatorResult::query()
            ->approved()
            ->where('indicator_id', $result->indicator_id)
            ->where('think_tank_member_id', $result->think_tank_member_id)
            ->where('reporting_period_id', $result->reporting_period_id)
            ->whereKeyNot($result->id)
            ->exists();
    }

    private function hasUpload(mixed $payload): bool
    {
        $value = data_get($payload, 'value', $payload);

        return collect(is_array($value) ? $value : [])->contains(
            fn ($item): bool => is_array($item) && filled($item['path'] ?? null)
        );
    }

    /** @param array<int, string> $keys */
    private function submissionHasDimension(MeDataSubmission $submission, array $keys): bool
    {
        return $submission->answers->contains(function (MeDataSubmissionAnswer $answer) use ($keys): bool {
            $identity = strtolower(implode(' ', array_filter([
                $answer->field_key,
                $answer->field?->label,
            ])));
            $matches = collect($keys)->contains(fn (string $key): bool => str_contains($identity, $key));

            return $matches && $this->hasMeaningfulValue(data_get($answer->value, 'value', $answer->value));
        });
    }

    private function hasMeaningfulValue(mixed $value): bool
    {
        if (is_array($value)) {
            return collect($value)->contains(fn ($item): bool => $this->hasMeaningfulValue($item));
        }

        return $value !== null && trim((string) $value) !== '';
    }

    private function finding(
        MeDataSubmission $submission,
        ?IndicatorResult $result,
        string $rule,
        string $severity,
        string $message,
        ?string $fieldKey = null
    ): MeDataQualityFinding {
        return MeDataQualityFinding::query()->create([
            'submission_id' => $submission->id,
            'indicator_result_id' => $result?->id,
            'rule_code' => $rule,
            'severity' => $severity,
            'field_key' => $fieldKey,
            'message' => $message,
            'status' => 'open',
        ]);
    }
}
