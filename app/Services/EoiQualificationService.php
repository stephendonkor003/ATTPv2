<?php

namespace App\Services;

use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EoiQualificationService
{
    public const QUALIFIED_SHORTLIST_LIMIT = 8;

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

        $evaluations = $this->mergeEvaluations($linkedEvaluations, $assignments);

        $applicants = FormSubmission::query()
            ->where('procurement_id', $procurement->getKey())
            ->with(['submitter', 'values'])
            ->where(function (Builder $query): void {
                $query->whereNull('status')
                    ->orWhereNotIn('status', $this->excludedApplicantStatuses());
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

        $qualifiedRanking = $this->rankQualifiedApplicants($applicantRows);
        $rankedQualifiedApplicants = $qualifiedRanking->keyBy(
            fn (array $row): string => (string) data_get($row, 'applicant.id')
        );

        // The panel outcome and the shortlist progression are deliberately kept
        // separate. A published proposal round can retain an historical candidate
        // record even where that applicant now sits below the current top-eight
        // shortlist. Exports must therefore communicate the current progression
        // decision without mutating the underlying applicant workflow/history.
        $applicantRows = $applicantRows
            ->map(function (array $row) use ($rankedQualifiedApplicants): array {
                $rankedRow = $rankedQualifiedApplicants->get(
                    (string) data_get($row, 'applicant.id')
                );

                return [
                    ...$row,
                    ...$this->applicantProgression($row, $rankedRow),
                ];
            })
            ->values();

        $qualifiedRanking = $applicantRows
            ->filter(fn (array $row): bool => filled($row['qualification_rank'] ?? null))
            ->sortBy('qualification_rank')
            ->values();
        $qualifiedShortlist = $qualifiedRanking
            ->take(self::QUALIFIED_SHORTLIST_LIMIT)
            ->values();
        $qualifiedOutsideShortlist = $qualifiedRanking
            ->where('within_qualified_shortlist', false)
            ->values();

        $panelMemberIds = $assignments->pluck('user_id')->filter()->unique();

        return [
            'procurement' => $procurement,
            'evaluations' => $evaluations,
            'applicants' => $applicantRows,
            'qualified_ranking' => $qualifiedRanking,
            'qualified_shortlist' => $qualifiedShortlist,
            'qualified_outside_shortlist' => $qualifiedOutsideShortlist,
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
                'final_not_qualified' => $applicantRows
                    ->where('outcome.code', self::OUTCOME_NOT_QUALIFIED)
                    ->where('panel_complete', true)
                    ->count(),
                'pending' => $applicantRows
                    ->where('outcome.code', self::OUTCOME_PENDING)
                    ->count(),
                'panel_incomplete' => $applicantRows->where('panel_complete', false)->count(),
                'advance' => $applicantRows->where('can_advance', true)->count(),
                'qualified_shortlist' => $qualifiedShortlist->count(),
                'qualified_below_shortlist' => $qualifiedOutsideShortlist->count(),
                'shortlist_proceeding' => $qualifiedShortlist->count(),
                'shortlist_not_proceeding' => $qualifiedOutsideShortlist->count(),
                'panel_members' => $panelMemberIds->count(),
                'submitted_evaluations' => $applicantRows->sum('completed_tasks'),
            ],
        ];
    }

    /**
     * Order panel-complete qualified applicants without inventing a numeric EOI score.
     *
     * Fully Qualified precedes Average Qualified. Within the same outcome, the
     * greater share and count of Qualified decisions comes first. Exact
     * categorical ties use applicant name and submission code so a strict
     * shortlist position remains stable and auditable.
     */
    public function rankQualifiedApplicants(iterable $applicantRows): Collection
    {
        $outcomeOrder = [
            self::OUTCOME_FULLY_QUALIFIED => 0,
            self::OUTCOME_AVERAGE_QUALIFIED => 1,
        ];

        return collect($applicantRows)
            ->filter(fn (array $row): bool => (bool) ($row['can_advance'] ?? false)
                && (bool) ($row['panel_complete'] ?? false)
                && array_key_exists((string) data_get($row, 'outcome.code'), $outcomeOrder))
            ->sort(function (array $left, array $right) use ($outcomeOrder): int {
                $leftOutcome = (string) data_get($left, 'outcome.code');
                $rightOutcome = (string) data_get($right, 'outcome.code');
                $outcomeComparison = ($outcomeOrder[$leftOutcome] ?? 9)
                    <=> ($outcomeOrder[$rightOutcome] ?? 9);

                if ($outcomeComparison !== 0) {
                    return $outcomeComparison;
                }

                $leftQualified = max(0, (int) data_get($left, 'counts.qualified', 0));
                $rightQualified = max(0, (int) data_get($right, 'counts.qualified', 0));
                $leftTotal = max(1, (int) ($left['total_decisions'] ?? 0));
                $rightTotal = max(1, (int) ($right['total_decisions'] ?? 0));
                $shareComparison = ($rightQualified * $leftTotal)
                    <=> ($leftQualified * $rightTotal);

                if ($shareComparison !== 0) {
                    return $shareComparison;
                }

                $qualifiedCountComparison = $rightQualified <=> $leftQualified;

                if ($qualifiedCountComparison !== 0) {
                    return $qualifiedCountComparison;
                }

                $averageComparison = max(0, (int) data_get($left, 'counts.average_qualified', 0))
                    <=> max(0, (int) data_get($right, 'counts.average_qualified', 0));

                if ($averageComparison !== 0) {
                    return $averageComparison;
                }

                $nameComparison = strnatcasecmp(
                    (string) data_get($left, 'applicant.display_name', ''),
                    (string) data_get($right, 'applicant.display_name', '')
                );

                if ($nameComparison !== 0) {
                    return $nameComparison;
                }

                $codeComparison = strnatcasecmp(
                    (string) data_get($left, 'applicant.procurement_submission_code', ''),
                    (string) data_get($right, 'applicant.procurement_submission_code', '')
                );

                if ($codeComparison !== 0) {
                    return $codeComparison;
                }

                return strcmp(
                    (string) data_get($left, 'applicant.id', ''),
                    (string) data_get($right, 'applicant.id', '')
                );
            })
            ->values()
            ->map(fn (array $row, int $index): array => [
                ...$row,
                'qualification_rank' => $index + 1,
                'within_qualified_shortlist' => $index < self::QUALIFIED_SHORTLIST_LIMIT,
                'qualified_shortlist_status' => $index < self::QUALIFIED_SHORTLIST_LIMIT
                    ? 'proceeding'
                    : 'not_proceeding',
            ]);
    }

    /**
     * Add the current shortlist decision used consistently by the web report and
     * every internal report download. It is presentation data, not a workflow
     * state transition, so historical proposal-round candidates remain intact.
     *
     * @param  array<string, mixed>  $applicantRow
     * @param  array<string, mixed>|null  $rankedRow
     * @return array<string, mixed>
     */
    private function applicantProgression(array $applicantRow, ?array $rankedRow): array
    {
        $rank = $rankedRow['qualification_rank'] ?? null;
        $withinShortlist = (bool) ($rankedRow['within_qualified_shortlist'] ?? false);

        if ($rank !== null) {
            return [
                'qualification_rank' => (int) $rank,
                'within_qualified_shortlist' => $withinShortlist,
                'qualified_shortlist_status' => $withinShortlist ? 'proceeding' : 'not_proceeding',
                'progression' => [
                    'code' => $withinShortlist ? 'proceeding' : 'not_proceeding',
                    'label' => $withinShortlist ? 'Proceeding' : 'Not proceeding',
                    'workflow' => $withinShortlist
                        ? 'Proceeding to Technical Evaluation'
                        : 'Not proceeding — outside current top-'.self::QUALIFIED_SHORTLIST_LIMIT.' shortlist',
                    'note' => $withinShortlist
                        ? 'Ranked within the current top-'.self::QUALIFIED_SHORTLIST_LIMIT.' qualified applicants.'
                        : 'Panel-qualified, but ranked below the current top-'.self::QUALIFIED_SHORTLIST_LIMIT.' shortlist positions.',
                ],
            ];
        }

        if (! (bool) ($applicantRow['panel_complete'] ?? false)) {
            return [
                'qualification_rank' => null,
                'within_qualified_shortlist' => false,
                'qualified_shortlist_status' => null,
                'progression' => [
                    'code' => 'awaiting_panel',
                    'label' => 'Awaiting panel completion',
                    'workflow' => 'No final routing',
                    'note' => 'Every active panel task must be complete before a shortlist decision is released.',
                ],
            ];
        }

        if (data_get($applicantRow, 'outcome.code') === self::OUTCOME_NOT_QUALIFIED) {
            return [
                'qualification_rank' => null,
                'within_qualified_shortlist' => false,
                'qualified_shortlist_status' => null,
                'progression' => [
                    'code' => 'does_not_advance',
                    'label' => 'Does not advance',
                    'workflow' => 'Does not advance',
                    'note' => 'A final Not Qualified panel decision stops progression.',
                ],
            ];
        }

        return [
            'qualification_rank' => null,
            'within_qualified_shortlist' => false,
            'qualified_shortlist_status' => null,
            'progression' => [
                'code' => 'awaiting_decision',
                'label' => 'Awaiting final decision',
                'workflow' => 'No final routing',
                'note' => 'A valid final panel decision is required before progression can be confirmed.',
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
        $procurementId = $applicant->procurement_id
            ?: $applicant->procurement?->getKey();

        if (! $procurementId) {
            return null;
        }

        return DB::transaction(function () use ($applicant, $procurementId): ?string {
            $procurement = Procurement::query()
                ->withTrashed()
                ->whereKey($procurementId)
                ->lockForUpdate()
                ->first();
            $lockedApplicant = FormSubmission::query()
                ->whereKey($applicant->getKey())
                ->where('procurement_id', $procurementId)
                ->lockForUpdate()
                ->first();

            if (! $procurement || ! $lockedApplicant) {
                return null;
            }

            $lockedApplicant->setRelation('procurement', $procurement);

            return $this->synchronizeLockedApplicantStage($lockedApplicant, $procurement);
        }, 3);
    }

    private function synchronizeLockedApplicantStage(
        FormSubmission $applicant,
        Procurement $procurement
    ): ?string {
        $assignments = $this->assignmentQuery($procurement)
            ->where(function (Builder $query) use ($applicant): void {
                $query->whereNull('form_submission_id')
                    ->orWhere('form_submission_id', $applicant->getKey());
            })
            ->get();
        $submissionRecords = $this->submissionQuery($procurement)
            ->where('form_submission_id', $applicant->getKey())
            ->get();

        if ($assignments->isEmpty() && $submissionRecords->isEmpty()) {
            return null;
        }

        $evaluations = $this->mergeEvaluations(collect(), $assignments);
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
            FormSubmission::STATUS_TECHNICAL_PROPOSAL_INVITED,
            FormSubmission::STATUS_TECHNICAL_PROPOSAL_SUBMITTED,
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
                'description' => $panelComplete
                    ? 'At least one panel decision is Not Qualified, so the applicant does not advance.'
                    : 'A Not Qualified decision is recorded, but final routing remains held until every assigned panel task is complete.',
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
                    fn (EvaluationSubmission $record): bool => filled($record->evaluation_assignment_id)
                        ? (string) $record->evaluation_assignment_id === (string) $assignment->getKey()
                        : (string) $record->evaluation_id === (string) $assignment->evaluation_id
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

        $completedTasks = $expectedTasks
            ->filter(fn (array $task): bool => $this->submissionCompletesEvaluation(
                $task['submission'],
                $task['evaluation']
            ));
        $panelComplete = $expectedTasks->isNotEmpty()
            && $completedTasks->count() === $expectedTasks->count();

        $decisionValues = $completedTasks
            ->pluck('submission')
            ->filter()
            ->flatMap(fn (EvaluationSubmission $submission) => $submission->criteriaScores)
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
            'next_stage' => match (true) {
                ! $panelComplete => 'Awaiting EOI panel',
                $canAdvance => 'Technical Evaluation',
                $outcome['code'] === self::OUTCOME_NOT_QUALIFIED => 'Does not advance',
                default => 'Awaiting EOI panel',
            },
            'panel_complete' => $panelComplete,
            'assignment_baseline_available' => $expectedTasks->isNotEmpty(),
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
                ->filter(fn (array $report): bool => $report['members']->isNotEmpty())
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
                    ->first(fn (EvaluationSubmission $record): bool => filled($record->evaluation_assignment_id)
                        ? (string) $record->evaluation_assignment_id === (string) $assignment->getKey()
                        : (string) $record->evaluator_id === (string) $assignment->user_id);

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
            ->where('workflow_stage', EvaluationAssignment::STAGE_APPLICATION)
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
            ->where(function (Builder $query): void {
                $query->whereNull('evaluation_assignment_id')
                    ->orWhereHas('assignment', fn (Builder $assignment) => $assignment
                        ->where('workflow_stage', EvaluationAssignment::STAGE_APPLICATION));
            })
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
        Collection $assignments
    ): Collection {
        return $linkedEvaluations
            ->concat($assignments->pluck('evaluation'))
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
