<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EoiQualificationService
{
    public const OUTCOME_FULLY_QUALIFIED = 'fully_qualified';

    public const OUTCOME_AVERAGE_QUALIFIED = 'average_qualified';

    public const OUTCOME_NOT_QUALIFIED = 'not_qualified';

    public const OUTCOME_PENDING = 'pending';

    /**
     * Build the complete, applicant-level EOI panel report for a procurement.
     */
    public function buildProcurementReport(Procurement $procurement): array
    {
        $assignments = $this->assignmentQuery($procurement)->get();
        $submissionRecords = $this->submissionQuery($procurement)->get();
        $linkedEvaluations = $procurement->evaluations()
            ->where('evaluations.type', Evaluation::TYPE_EOI)
            ->with($this->evaluationRelations())
            ->get()
            ->concat(
                $procurement->directEvaluations()
                    ->where('type', Evaluation::TYPE_EOI)
                    ->with($this->evaluationRelations())
                    ->get()
            );

        $evaluations = $this->mergeEvaluations(
            $linkedEvaluations,
            $assignments,
            $submissionRecords
        );

        $workflowApplicantIds = $submissionRecords
            ->pluck('form_submission_id')
            ->filter()
            ->unique()
            ->values();

        $applicants = FormSubmission::query()
            ->where('procurement_id', $procurement->getKey())
            ->with(['submitter', 'values'])
            ->where(function (Builder $query) use ($workflowApplicantIds): void {
                $query->whereNull('status')
                    ->orWhereNotIn('status', $this->excludedApplicantStatuses());

                if ($workflowApplicantIds->isNotEmpty()) {
                    $query->orWhereIn('id', $workflowApplicantIds);
                }
            })
            ->orderBy('submitted_at')
            ->orderBy('created_at')
            ->get();

        $applicantRows = $applicants
            ->map(fn (FormSubmission $applicant): array => $this->buildApplicantRow(
                $applicant,
                $assignments,
                $submissionRecords,
                $evaluations
            ))
            ->sortBy(function (array $row): string {
                $outcomeOrder = [
                    self::OUTCOME_FULLY_QUALIFIED => '1',
                    self::OUTCOME_AVERAGE_QUALIFIED => '2',
                    self::OUTCOME_PENDING => '3',
                    self::OUTCOME_NOT_QUALIFIED => '4',
                ];

                return ($outcomeOrder[$row['outcome']['code']] ?? '9')
                    .strtolower($row['applicant']->display_name);
            })
            ->values();

        $completedSubmissions = $submissionRecords
            ->filter(fn (EvaluationSubmission $submission): bool => filled($submission->submitted_at));
        $panelMemberIds = $assignments->pluck('user_id')
            ->merge($completedSubmissions->pluck('evaluator_id'))
            ->filter()
            ->unique();

        return [
            'procurement' => $procurement,
            'evaluations' => $evaluations,
            'applicants' => $applicantRows,
            'generated_at' => now(),
            'stats' => [
                'total_applicants' => $applicantRows->count(),
                'fully_qualified' => $applicantRows
                    ->where('outcome.code', self::OUTCOME_FULLY_QUALIFIED)
                    ->count(),
                'average_qualified' => $applicantRows
                    ->where('outcome.code', self::OUTCOME_AVERAGE_QUALIFIED)
                    ->count(),
                'not_qualified' => $applicantRows
                    ->where('outcome.code', self::OUTCOME_NOT_QUALIFIED)
                    ->count(),
                'pending' => $applicantRows
                    ->where('outcome.code', self::OUTCOME_PENDING)
                    ->count(),
                'advance' => $applicantRows->where('can_advance', true)->count(),
                'panel_members' => $panelMemberIds->count(),
                'submitted_evaluations' => $completedSubmissions->count(),
            ],
        ];
    }

    /**
     * Apply the derived EOI gate to an applicant's workflow status.
     *
     * Advancement is persisted only after every expected panel task is complete.
     */
    public function synchronizeApplicantStage(FormSubmission $applicant): ?string
    {
        $applicant->loadMissing('procurement');

        if (! $applicant->procurement) {
            return null;
        }

        $assignments = $this->assignmentQuery($applicant->procurement)
            ->where(function (Builder $query) use ($applicant): void {
                $query->whereNull('form_submission_id')
                    ->orWhere('form_submission_id', $applicant->getKey());
            })
            ->get();
        $submissionRecords = $this->submissionQuery($applicant->procurement)
            ->where('form_submission_id', $applicant->getKey())
            ->get();

        if ($assignments->isEmpty() && $submissionRecords->isEmpty()) {
            return null;
        }

        $evaluations = $this->mergeEvaluations(collect(), $assignments, $submissionRecords);
        $row = $this->buildApplicantRow(
            $applicant->loadMissing(['submitter', 'values']),
            $assignments,
            $submissionRecords,
            $evaluations
        );

        $targetStatus = FormSubmission::STATUS_EOI_EVALUATION;

        if ($row['panel_complete'] && $row['total_decisions'] > 0) {
            $targetStatus = $row['counts']['not_qualified'] > 0
                ? FormSubmission::STATUS_EOI_NOT_QUALIFIED
                : FormSubmission::STATUS_TECHNICAL_EVALUATION;
        }

        $transitionableStatuses = [
            null,
            FormSubmission::STATUS_SUBMITTED,
            'prescreen_passed',
            FormSubmission::STATUS_EOI_EVALUATION,
            FormSubmission::STATUS_EOI_NOT_QUALIFIED,
            FormSubmission::STATUS_TECHNICAL_EVALUATION,
        ];

        if (! in_array($applicant->status, $transitionableStatuses, true)) {
            return null;
        }

        if ($applicant->status !== $targetStatus) {
            $applicant->forceFill(['status' => $targetStatus])->save();
        }

        return $targetStatus;
    }

    /**
     * Re-evaluate every reportable applicant after a procurement-wide panel change.
     */
    public function synchronizeProcurementStages(Procurement $procurement): void
    {
        FormSubmission::query()
            ->where('procurement_id', $procurement->getKey())
            ->where(function (Builder $query): void {
                $query->whereNull('status')
                    ->orWhereNotIn('status', $this->excludedApplicantStatuses());
            })
            ->each(fn (FormSubmission $applicant) => $this->synchronizeApplicantStage($applicant));
    }

    /**
     * The non-numeric EOI veto rule, exposed independently for deterministic tests.
     */
    public function classify(iterable $decisions, bool $panelComplete): array
    {
        $values = collect($decisions)
            ->map(fn ($decision): ?int => $this->normaliseDecision($decision))
            ->filter(fn (?int $decision): bool => $decision !== null)
            ->values();

        if ($values->contains(0)) {
            return [
                'code' => self::OUTCOME_NOT_QUALIFIED,
                'label' => 'Not Qualified',
                'tone' => 'danger',
                'description' => 'At least one panel decision is Not Qualified, so the applicant does not advance.',
            ];
        }

        if (! $panelComplete || $values->isEmpty()) {
            return [
                'code' => self::OUTCOME_PENDING,
                'label' => 'Awaiting Panel',
                'tone' => 'pending',
                'description' => 'The final outcome will be released after every assigned evaluator submits.',
            ];
        }

        if ($values->contains(1)) {
            return [
                'code' => self::OUTCOME_AVERAGE_QUALIFIED,
                'label' => 'Average Qualified',
                'tone' => 'warning',
                'description' => 'No Not Qualified decision was recorded; the applicant advances to Technical Evaluation.',
            ];
        }

        return [
            'code' => self::OUTCOME_FULLY_QUALIFIED,
            'label' => 'Fully Qualified',
            'tone' => 'success',
            'description' => 'Every submitted criterion is Qualified; the applicant advances to Technical Evaluation.',
        ];
    }

    private function buildApplicantRow(
        FormSubmission $applicant,
        Collection $assignments,
        Collection $submissionRecords,
        Collection $evaluations
    ): array {
        $applicantAssignments = $assignments
            ->filter(fn (EvaluationAssignment $assignment): bool => ! $assignment->form_submission_id
                || (string) $assignment->form_submission_id === (string) $applicant->getKey())
            ->values();
        $applicantRecords = $submissionRecords
            ->where('form_submission_id', $applicant->getKey())
            ->sortByDesc(fn (EvaluationSubmission $submission): int => $submission->submitted_at?->getTimestamp() ?? 0)
            ->values();

        $expectedTasks = $applicantAssignments
            ->map(function (EvaluationAssignment $assignment) use ($applicantRecords): array {
                $submission = $applicantRecords->first(
                    fn (EvaluationSubmission $record): bool => (string) $record->evaluation_id === (string) $assignment->evaluation_id
                        && (string) $record->evaluator_id === (string) $assignment->user_id
                );

                return [
                    'key' => $this->taskKey($assignment->evaluation_id, $assignment->user_id),
                    'evaluation' => $assignment->evaluation,
                    'evaluator' => $assignment->evaluator,
                    'evaluator_id' => $assignment->user_id,
                    'submission' => $submission,
                    'assigned' => true,
                ];
            })
            ->unique('key')
            ->values();

        // Legacy/imported submissions may not have surviving assignment rows.
        if ($expectedTasks->isEmpty()) {
            $expectedTasks = $applicantRecords
                ->map(fn (EvaluationSubmission $submission): array => [
                    'key' => $this->taskKey(
                        $submission->evaluation_id,
                        $submission->evaluator_id,
                        $submission->getKey()
                    ),
                    'evaluation' => $submission->evaluation,
                    'evaluator' => $submission->evaluator,
                    'evaluator_id' => $submission->evaluator_id,
                    'submission' => $submission,
                    'assigned' => false,
                ])
                ->unique('key')
                ->values();
        }

        $completedTasks = $expectedTasks
            ->filter(fn (array $task): bool => $this->submissionCompletesEvaluation(
                $task['submission'],
                $task['evaluation']
            ));
        $panelComplete = $expectedTasks->isNotEmpty()
            && $completedTasks->count() === $expectedTasks->count();

        $completedRecords = $applicantRecords
            ->filter(fn (EvaluationSubmission $submission): bool => filled($submission->submitted_at));
        $decisionValues = $completedRecords
            ->flatMap->criteriaScores
            ->pluck('decision')
            ->filter(fn ($decision): bool => $decision !== null && $decision !== '')
            ->map(fn ($decision): int => (int) $decision)
            ->filter(fn (int $decision): bool => array_key_exists($decision, Evaluation::EOI_DECISIONS))
            ->values();

        $counts = $this->decisionCounts($decisionValues);
        $outcome = $this->classify($decisionValues, $panelComplete);
        $canAdvance = $panelComplete
            && $decisionValues->isNotEmpty()
            && $counts['not_qualified'] === 0;
        $expectedEvaluatorKeys = $expectedTasks
            ->map(fn (array $task): string => $task['evaluator_id']
                ? (string) $task['evaluator_id']
                : 'task:'.$task['key'])
            ->unique();
        $completedEvaluatorKeys = $completedTasks
            ->map(fn (array $task): string => $task['evaluator_id']
                ? (string) $task['evaluator_id']
                : 'task:'.$task['key'])
            ->unique();

        return [
            'applicant' => $applicant,
            'counts' => $counts,
            'total_decisions' => $decisionValues->count(),
            'outcome' => $outcome,
            'can_advance' => $canAdvance,
            'next_stage' => $canAdvance
                ? 'Technical Evaluation'
                : ($outcome['code'] === self::OUTCOME_NOT_QUALIFIED
                    ? 'Does not advance'
                    : 'Awaiting EOI panel'),
            'panel_complete' => $panelComplete,
            'expected_tasks' => $expectedTasks->count(),
            'completed_tasks' => $completedTasks->count(),
            'expected_evaluators' => $expectedEvaluatorKeys->count(),
            'completed_evaluators' => $completedEvaluatorKeys->count(),
            'completion_percent' => $expectedTasks->isEmpty()
                ? 0
                : (int) round(($completedTasks->count() / $expectedTasks->count()) * 100),
            'evaluation_reports' => $evaluations
                ->map(fn (Evaluation $evaluation): array => $this->buildEvaluationReport(
                    $evaluation,
                    $applicantAssignments,
                    $applicantRecords
                ))
                ->filter(fn (array $report): bool => $report['members']->isNotEmpty()
                    || $report['criteria']->isNotEmpty())
                ->values(),
        ];
    }

    private function buildEvaluationReport(
        Evaluation $evaluation,
        Collection $assignments,
        Collection $submissionRecords
    ): array {
        $evaluationAssignments = $assignments
            ->where('evaluation_id', $evaluation->getKey())
            ->values();
        $evaluationSubmissions = $submissionRecords
            ->where('evaluation_id', $evaluation->getKey())
            ->values();

        $members = $evaluationAssignments
            ->map(function (EvaluationAssignment $assignment) use ($evaluation, $evaluationSubmissions): array {
                $submission = $evaluationSubmissions
                    ->sortByDesc(fn (EvaluationSubmission $record): int => $record->submitted_at?->getTimestamp() ?? 0)
                    ->first(fn (EvaluationSubmission $record): bool => (string) $record->evaluator_id === (string) $assignment->user_id);

                return $this->memberRow(
                    $this->taskKey($evaluation->getKey(), $assignment->user_id),
                    $assignment->evaluator,
                    $assignment->user_id,
                    $submission,
                    $evaluation,
                    true
                );
            })
            ->unique('key')
            ->values();

        foreach ($evaluationSubmissions as $submission) {
            $key = $this->taskKey(
                $evaluation->getKey(),
                $submission->evaluator_id,
                $submission->getKey()
            );

            if ($members->contains('key', $key)) {
                continue;
            }

            $members->push($this->memberRow(
                $key,
                $submission->evaluator,
                $submission->evaluator_id,
                $submission,
                $evaluation,
                false
            ));
        }

        $criteria = $evaluation->sections
            ->sortBy(fn ($section): string => str_pad((string) ($section->sort_order ?? 0), 8, '0', STR_PAD_LEFT)
                .(string) $section->created_at
                .(string) $section->getKey())
            ->flatMap(function ($section) use ($members): Collection {
                return $section->criteria->map(function ($criterion) use ($section, $members): array {
                    $assessments = $members
                        ->filter(fn (array $member): bool => $member['submitted'])
                        ->map(function (array $member) use ($criterion): ?array {
                            $score = $member['submission']?->criteriaScores
                                ->firstWhere('evaluation_criteria_id', $criterion->getKey());

                            if (! $score || $score->decision === null || $score->decision === '') {
                                return null;
                            }

                            $decision = (int) $score->decision;

                            if (! array_key_exists($decision, Evaluation::EOI_DECISIONS)) {
                                return null;
                            }

                            return [
                                'member_key' => $member['key'],
                                'evaluator_id' => $member['evaluator_id'],
                                'evaluator_name' => $member['name'],
                                'decision' => $decision,
                                'label' => Evaluation::EOI_DECISIONS[$decision],
                                'comment' => trim((string) $score->comment),
                            ];
                        })
                        ->filter()
                        ->values();
                    $decisionValues = $assessments->pluck('decision');
                    $expectedMembers = $members->where('assigned', true);

                    if ($expectedMembers->isEmpty()) {
                        $expectedMembers = $members;
                    }

                    $criterionComplete = $expectedMembers->isNotEmpty()
                        && $expectedMembers->every(
                            fn (array $member): bool => $assessments->contains('member_key', $member['key'])
                        );

                    return [
                        'criterion' => $criterion,
                        'section' => $section,
                        'assessments' => $assessments,
                        'counts' => $this->decisionCounts($decisionValues),
                        'outcome' => $this->classify($decisionValues, $criterionComplete),
                    ];
                });
            })
            ->values();

        return [
            'evaluation' => $evaluation,
            'members' => $members,
            'criteria' => $criteria,
        ];
    }

    private function memberRow(
        string $key,
        $evaluator,
        ?string $evaluatorId,
        ?EvaluationSubmission $submission,
        Evaluation $evaluation,
        bool $assigned
    ): array {
        $submitted = filled($submission?->submitted_at);
        $decisions = $submitted
            ? $submission->criteriaScores
                ->pluck('decision')
                ->filter(fn ($decision): bool => $decision !== null && $decision !== '')
                ->map(fn ($decision): int => (int) $decision)
            : collect();

        return [
            'key' => $key,
            'evaluator_id' => $evaluatorId,
            'name' => $evaluator?->name ?? 'Unassigned evaluator',
            'email' => $evaluator?->email,
            'assigned' => $assigned,
            'submitted' => $submitted,
            'task_complete' => $this->submissionCompletesEvaluation($submission, $evaluation),
            'submitted_at' => $submission?->submitted_at,
            'submission' => $submission,
            'counts' => $this->decisionCounts($decisions),
        ];
    }

    private function submissionCompletesEvaluation(
        ?EvaluationSubmission $submission,
        ?Evaluation $evaluation
    ): bool {
        if (! $submission || ! $evaluation || ! $submission->submitted_at) {
            return false;
        }

        $criterionIds = $evaluation->sections
            ->flatMap->criteria
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->unique();

        if ($criterionIds->isEmpty()) {
            return false;
        }

        $completedCriterionIds = $submission->criteriaScores
            ->filter(fn ($score): bool => $score->decision !== null
                && $score->decision !== ''
                && array_key_exists((int) $score->decision, Evaluation::EOI_DECISIONS))
            ->pluck('evaluation_criteria_id')
            ->map(fn ($id): string => (string) $id)
            ->unique();

        return $criterionIds->diff($completedCriterionIds)->isEmpty();
    }

    private function decisionCounts(iterable $decisions): array
    {
        $values = collect($decisions)
            ->map(fn ($decision): ?int => $this->normaliseDecision($decision))
            ->filter(fn (?int $decision): bool => $decision !== null);

        return [
            'qualified' => $values->filter(fn (int $decision): bool => $decision === 2)->count(),
            'average_qualified' => $values->filter(fn (int $decision): bool => $decision === 1)->count(),
            'not_qualified' => $values->filter(fn (int $decision): bool => $decision === 0)->count(),
        ];
    }

    private function normaliseDecision($decision): ?int
    {
        if (is_int($decision)) {
            return array_key_exists($decision, Evaluation::EOI_DECISIONS)
                ? $decision
                : null;
        }

        if (is_string($decision) && preg_match('/^[012]$/', trim($decision)) === 1) {
            return (int) trim($decision);
        }

        return null;
    }

    private function assignmentQuery(Procurement $procurement): Builder
    {
        return EvaluationAssignment::query()
            ->where('procurement_id', $procurement->getKey())
            ->whereHas('evaluation', fn (Builder $query) => $query->where('type', Evaluation::TYPE_EOI))
            ->with([
                'evaluator:id,name,email',
                'evaluation' => fn ($query) => $query->with($this->evaluationRelations()),
            ]);
    }

    private function submissionQuery(Procurement $procurement): Builder
    {
        return EvaluationSubmission::query()
            ->where('procurement_id', $procurement->getKey())
            ->whereHas('evaluation', fn (Builder $query) => $query->where('type', Evaluation::TYPE_EOI))
            ->with([
                'evaluator:id,name,email',
                'criteriaScores.criteria',
                'evaluation' => fn ($query) => $query->with($this->evaluationRelations()),
            ]);
    }

    private function evaluationRelations(): array
    {
        return [
            'sections' => fn ($query) => $query
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->orderBy('id'),
            'sections.criteria',
        ];
    }

    private function mergeEvaluations(
        Collection $linkedEvaluations,
        Collection $assignments,
        Collection $submissionRecords
    ): Collection {
        return $linkedEvaluations
            ->concat($assignments->pluck('evaluation'))
            ->concat($submissionRecords->pluck('evaluation'))
            ->filter(fn ($evaluation): bool => $evaluation instanceof Evaluation && $evaluation->isEoi())
            ->unique(fn (Evaluation $evaluation): string => (string) $evaluation->getKey())
            ->sortBy('name')
            ->values();
    }

    private function excludedApplicantStatuses(): array
    {
        return [
            'draft',
            FormSubmission::STATUS_REVISION_REQUESTED,
            FormSubmission::STATUS_WITHDRAWN,
            'prescreen_failed',
        ];
    }

    private function taskKey(?string $evaluationId, ?string $evaluatorId, ?string $fallback = null): string
    {
        return (string) $evaluationId.':'.($evaluatorId ?: 'submission:'.$fallback);
    }
}
