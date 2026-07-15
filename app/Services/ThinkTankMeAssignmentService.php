<?php

namespace App\Services;

use App\Models\ConsortiumThinkTank;
use App\Models\MeDataCollection;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeDataEntryForm;
use App\Models\MeDataSubmission;
use App\Models\MeDataSubmissionAnswer;
use App\Models\MeReportingPeriod;
use Illuminate\Support\Collection;

class ThinkTankMeAssignmentService
{
    /**
     * Build the member-scoped assignment data shared by the portal dashboard
     * and the full M&E data-collection workspace.
     *
     * @param  array<string, scalar>  $portalRouteParams
     * @return array{
     *     groups: Collection<string, Collection<int, array<string, mixed>>>,
     *     summary: array{total: int, open: int, upcoming: int, submitted: int, closed: int, action_required: int},
     *     priority: Collection<int, array<string, mixed>>,
     *     index_url: string
     * }
     */
    public function overview(
        ConsortiumThinkTank $member,
        array $portalRouteParams = [],
        bool $canSubmit = false,
        int $priorityLimit = 4
    ): array {
        $assignments = MeDataCollectionAssignment::query()
            ->where('think_tank_member_id', $member->getKey())
            ->whereHas('collection', fn ($query) => $query->whereIn('status', [
                MeDataCollection::STATUS_OPEN,
                MeDataCollection::STATUS_CLOSED,
            ]))
            ->whereHas('collection.form', fn ($query) => $query->where('status', MeDataEntryForm::STATUS_PUBLISHED))
            ->with([
                'collection.form.fields',
                'collection.form.indicator.unit',
                'collection.reportingPeriod',
                'submission.answers',
            ])
            ->latest('assigned_at')
            ->get();

        $groups = collect([
            'open' => collect(),
            'upcoming' => collect(),
            'submitted' => collect(),
            'closed' => collect(),
        ]);

        foreach ($assignments as $assignment) {
            $state = $this->stateFor($assignment);
            $groups->get($state)->push(
                $this->cardFor($assignment, $state, $portalRouteParams, $canSubmit)
            );
        }

        $summary = [
            'total' => $assignments->count(),
            'open' => $groups->get('open')->count(),
            'upcoming' => $groups->get('upcoming')->count(),
            'submitted' => $groups->get('submitted')->count(),
            'closed' => $groups->get('closed')->count(),
            'action_required' => $groups->get('open')->count(),
        ];

        $priority = $groups->get('open')
            ->sort(function (array $left, array $right): int {
                $overdueOrder = ((int) $right['is_overdue']) <=> ((int) $left['is_overdue']);

                if ($overdueOrder !== 0) {
                    return $overdueOrder;
                }

                return ($left['due_at']?->getTimestamp() ?? PHP_INT_MAX)
                    <=> ($right['due_at']?->getTimestamp() ?? PHP_INT_MAX);
            })
            ->values()
            ->concat($groups->get('upcoming')->sortBy('opens_at')->values())
            ->concat($groups->get('submitted')->sortByDesc(
                fn (array $card) => $card['assignment']->assigned_at?->getTimestamp() ?? 0
            )->values())
            ->take(max(1, $priorityLimit))
            ->values();

        return [
            'groups' => $groups,
            'summary' => $summary,
            'priority' => $priority,
            'index_url' => route('think-tank.me-data.index', $portalRouteParams),
        ];
    }

    public function stateFor(MeDataCollectionAssignment $assignment): string
    {
        $submission = $assignment->submission;

        if ($submission && in_array($submission->status, [
            MeDataSubmission::STATUS_SUBMITTED,
            MeDataSubmission::STATUS_VALIDATED,
            MeDataSubmission::STATUS_APPROVED,
        ], true)) {
            return 'submitted';
        }

        $collection = $assignment->collection;
        $period = $collection?->reportingPeriod;

        if (! $collection
            || $collection->status === MeDataCollection::STATUS_CLOSED
            || $period?->status === MeReportingPeriod::STATUS_CLOSED
            || ($collection->closes_at && $collection->closes_at->isPast())) {
            return 'closed';
        }

        if ($collection->status === MeDataCollection::STATUS_DRAFT
            || ($collection->opens_at && $collection->opens_at->isFuture())) {
            return 'upcoming';
        }

        return $collection->isAcceptingSubmissions() ? 'open' : 'closed';
    }

    /**
     * @return array{answered: int, total: int, percent: int}
     */
    public function progressFor(Collection $fields, ?MeDataSubmission $submission): array
    {
        $total = $fields->count();
        $values = $this->answerValues($submission);
        $answered = $fields->filter(function ($field) use ($values): bool {
            return ! $this->answerIsBlank($values->get((string) $field->id));
        })->count();

        return [
            'answered' => $answered,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($answered / $total) * 100) : 0,
        ];
    }

    /**
     * @param  array<string, scalar>  $portalRouteParams
     * @return array<string, mixed>
     */
    private function cardFor(
        MeDataCollectionAssignment $assignment,
        string $state,
        array $portalRouteParams,
        bool $canSubmit
    ): array {
        $collection = $assignment->collection;
        $form = $collection->form;
        $indicator = $form->indicator;
        $period = $collection->reportingPeriod;
        $submission = $assignment->submission;
        $canEdit = $canSubmit
            && $state === 'open'
            && (! $submission || $submission->isEditable())
            && $collection->isAcceptingSubmissions()
            && $period?->status === MeReportingPeriod::STATUS_ACTIVE;

        return [
            'assignment' => $assignment,
            'state' => $state,
            'form_title' => $form->title,
            'form_code' => $form->code,
            'indicator' => $indicator,
            'indicator_id' => $indicator?->id,
            'indicator_code' => $indicator?->indicator_code,
            'indicator_name' => $indicator?->name,
            'indicator_unit' => $indicator?->unit?->symbol ?: $indicator?->unit?->name,
            'description' => $form->description,
            'period_label' => $period?->label ?: 'Reporting period',
            'period_start' => $period?->period_start,
            'period_end' => $period?->period_end,
            'opens_at' => $collection->opens_at,
            'due_at' => $collection->due_at,
            'closes_at' => $collection->closes_at,
            'is_overdue' => $collection->isPastDue() && $state === 'open',
            'submission_status' => $submission?->status,
            'submission_status_label' => $this->statusLabel($submission?->status),
            'progress' => $this->progressFor($form->fields, $submission),
            'url' => route('think-tank.me-data.show', array_merge(
                ['assignment' => $assignment->getKey()],
                $portalRouteParams
            )),
            'can_edit' => $canEdit,
            'action_label' => $this->actionLabel($state, $submission, $canEdit),
        ];
    }

    private function answerValues(?MeDataSubmission $submission): Collection
    {
        if (! $submission) {
            return collect();
        }

        $submission->loadMissing('answers');

        return $submission->answers->mapWithKeys(function (MeDataSubmissionAnswer $answer): array {
            $payload = $answer->value;

            return [
                (string) $answer->field_id => is_array($payload) && array_key_exists('value', $payload)
                    ? $payload['value']
                    : $payload,
            ];
        });
    }

    private function answerIsBlank(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return collect($value)->filter(fn ($item) => ! $this->answerIsBlank($item))->isEmpty();
        }

        return false;
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            MeDataSubmission::STATUS_DRAFT => 'Draft saved',
            MeDataSubmission::STATUS_SUBMITTED => 'Submitted for review',
            MeDataSubmission::STATUS_RETURNED => 'Returned for correction',
            MeDataSubmission::STATUS_VALIDATED => 'Validated',
            MeDataSubmission::STATUS_APPROVED => 'Approved',
            default => 'Not started',
        };
    }

    private function actionLabel(
        string $state,
        ?MeDataSubmission $submission,
        bool $canEdit
    ): string {
        if ($canEdit) {
            return match ($submission?->status) {
                MeDataSubmission::STATUS_RETURNED => 'Correct and resubmit',
                MeDataSubmission::STATUS_DRAFT => 'Continue update',
                default => 'Start update',
            };
        }

        return match ($state) {
            'submitted' => 'View submission',
            'closed' => 'View record',
            default => 'View details',
        };
    }
}
