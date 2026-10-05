<?php

namespace App\Services;

use App\Models\SystemAuditLog;
use App\Models\ThinkTankProcurementEvent;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ThinkTankProcurementWorkflowService
{
    public function nextPlanCode(string $fiscalYear): string
    {
        $year = preg_replace('/[^0-9]/', '', $fiscalYear) ?: now()->format('Y');
        $prefix = 'TT-PP-'.$year.'-';
        $next = ThinkTankProcurementPlan::query()->where('plan_code', 'like', $prefix.'%')->count() + 1;

        do {
            $code = $prefix.str_pad((string) $next++, 4, '0', STR_PAD_LEFT);
        } while (ThinkTankProcurementPlan::query()->where('plan_code', $code)->exists());

        return $code;
    }

    public function nextItemCode(ThinkTankProcurementPlan $plan): string
    {
        $next = $plan->items()->count() + 1;

        do {
            $code = $plan->plan_code.'-'.str_pad((string) $next++, 3, '0', STR_PAD_LEFT);
        } while (ThinkTankProcurementItem::query()->where('item_code', $code)->exists());

        return $code;
    }

    public function syncPlanBudget(ThinkTankProcurementPlan $plan): void
    {
        $plan->forceFill([
            'estimated_budget' => $plan->items()->sum('estimated_amount'),
        ])->saveQuietly();
    }

    public function submit(ThinkTankProcurementPlan $plan, User $actor): ThinkTankProcurementPlan
    {
        $planId = (string) $plan->id;

        DB::transaction(function () use ($planId, $actor): void {
            $lockedPlan = ThinkTankProcurementPlan::query()->whereKey($planId)->lockForUpdate()->firstOrFail();
            abort_unless($lockedPlan->isEditable(), 422, 'This plan cannot be submitted in its current state.');

            $items = ThinkTankProcurementItem::query()
                ->where('plan_id', $lockedPlan->id)
                ->with('documents')
                ->lockForUpdate()
                ->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'plan' => 'Add at least one procurement item before submitting the annual plan.',
                ]);
            }

            $terminalStatuses = [
                ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                ThinkTankProcurementItem::STATUS_PUBLISHED,
            ];
            $activeItems = $items->whereNotIn('status', $terminalStatuses);
            $missingTor = $activeItems->reject(fn (ThinkTankProcurementItem $item): bool => $item->hasTermsOfReference());
            if ($missingTor->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'documents' => 'A Terms of Reference document is required for every item still entering review. Missing: '.$missingTor->pluck('item_code')->implode(', ').'.',
                ]);
            }

            $blocked = $items->where('status', ThinkTankProcurementItem::STATUS_REJECTED);
            if ($blocked->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'plan' => 'Correct or remove rejected items before resubmission. Imported items already Cleared in STEP remain at their recorded terminal status.',
                ]);
            }

            $previousStatus = $lockedPlan->status;
            $items
                ->whereIn('status', [
                    ThinkTankProcurementItem::STATUS_DRAFT,
                    ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                ])
                ->each(function (ThinkTankProcurementItem $item) use ($lockedPlan, $actor): void {
                    $itemPreviousStatus = $item->status;
                    $item->update([
                        'status' => ThinkTankProcurementItem::STATUS_SUBMITTED,
                        'source_activity_status' => ThinkTankProcurementItem::ACTIVITY_STATUS_SUBMITTED,
                        'review_reason' => null,
                        'updated_by' => $actor->id,
                        'portal_lock_version' => $item->nextPortalLockVersion(),
                    ]);
                    $this->event(
                        $lockedPlan,
                        $item,
                        $actor,
                        'item_submitted_to_secretariat',
                        $itemPreviousStatus,
                        $item->status,
                        null,
                        ['notification_suppressed' => true, 'notification_covered_by' => 'plan_submitted'],
                    );
                });

            $lockedPlan->update([
                'status' => ThinkTankProcurementPlan::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'last_resubmitted_at' => $previousStatus === ThinkTankProcurementPlan::STATUS_DRAFT ? null : now(),
                'version' => $previousStatus === ThinkTankProcurementPlan::STATUS_DRAFT
                    ? max(1, (int) $lockedPlan->version)
                    : ((int) $lockedPlan->version + 1),
                'decision_reason' => null,
                'rejected_at' => null,
            ]);

            $this->event($lockedPlan, null, $actor, 'plan_submitted', $previousStatus, $lockedPlan->status, null, [
                'item_count' => $items->count(),
                'estimated_budget' => (float) $items->sum('estimated_amount'),
                'version' => $lockedPlan->version,
            ]);
        });

        return ThinkTankProcurementPlan::query()->findOrFail($planId);
    }

    public function decidePlan(ThinkTankProcurementPlan $plan, User $actor, string $decision, ?string $reason): ThinkTankProcurementPlan
    {
        if (in_array($decision, ['revision_requested', 'rejected'], true) && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Give the Think Tank a clear reason for this decision.']);
        }

        $target = match ($decision) {
            'approve' => ThinkTankProcurementPlan::STATUS_APPROVED,
            'revision_requested' => ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
            'rejected' => ThinkTankProcurementPlan::STATUS_REJECTED,
            default => throw ValidationException::withMessages(['decision' => 'Invalid plan decision.']),
        };
        $planId = (string) $plan->id;

        DB::transaction(function () use ($planId, $actor, $decision, $reason, $target): void {
            $lockedPlan = ThinkTankProcurementPlan::query()->whereKey($planId)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedPlan->status, [
                ThinkTankProcurementPlan::STATUS_SUBMITTED,
                ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
            ], true), 422, 'Only submitted plans can be reviewed.');

            $items = ThinkTankProcurementItem::query()
                ->where('plan_id', $lockedPlan->id)
                ->with('documents')
                ->lockForUpdate()
                ->get();
            if ($decision === 'approve' && $items->whereIn('status', [
                ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                ThinkTankProcurementItem::STATUS_REJECTED,
            ])->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'decision' => 'Resolve every returned or rejected item before approving the full plan.',
                ]);
            }

            $previousStatus = $lockedPlan->status;
            $itemTarget = match ($decision) {
                'approve' => ThinkTankProcurementItem::STATUS_APPROVED,
                'revision_requested' => ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                default => ThinkTankProcurementItem::STATUS_REJECTED,
            };

            $reviewableStatuses = $decision === 'approve'
                ? [ThinkTankProcurementItem::STATUS_SUBMITTED, ThinkTankProcurementItem::STATUS_DRAFT]
                : [
                    ThinkTankProcurementItem::STATUS_SUBMITTED,
                    ThinkTankProcurementItem::STATUS_DRAFT,
                    ThinkTankProcurementItem::STATUS_APPROVED,
                    ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                    ThinkTankProcurementItem::STATUS_REJECTED,
                ];

            $items->whereIn('status', $reviewableStatuses)
                ->each(function (ThinkTankProcurementItem $item) use ($lockedPlan, $actor, $decision, $itemTarget, $reason): void {
                    $itemPreviousStatus = $item->status;
                    $item->update([
                        'status' => $itemTarget,
                        'source_activity_status' => ThinkTankProcurementItem::activityStatusFor($itemTarget),
                        'review_reason' => $reason,
                        'reviewed_by' => $actor->id,
                        'reviewed_at' => now(),
                        'portal_lock_version' => $item->nextPortalLockVersion(),
                    ]);
                    $this->event(
                        $lockedPlan,
                        $item,
                        $actor,
                        'item_'.$decision,
                        $itemPreviousStatus,
                        $itemTarget,
                        $reason,
                        ['notification_suppressed' => true, 'notification_covered_by' => 'plan_'.$decision],
                    );
                });

            $lockedPlan->update([
                'status' => $target,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $reason,
                'decision_reason' => $reason,
                'approved_at' => $decision === 'approve' ? now() : null,
                'rejected_at' => $decision === 'rejected' ? now() : null,
            ]);

            $this->event($lockedPlan, null, $actor, 'plan_'.$decision, $previousStatus, $target, $reason);
        });

        return ThinkTankProcurementPlan::query()->findOrFail($planId);
    }

    public function reviewItem(ThinkTankProcurementItem $item, User $actor, string $decision, ?string $reason): ThinkTankProcurementItem
    {
        if (in_array($decision, ['revision_requested', 'rejected'], true) && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Give a reason for returning or rejecting this item.']);
        }

        $target = match ($decision) {
            'approve' => ThinkTankProcurementItem::STATUS_APPROVED,
            'revision_requested' => ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
            'rejected' => ThinkTankProcurementItem::STATUS_REJECTED,
            default => throw ValidationException::withMessages(['decision' => 'Invalid item decision.']),
        };
        $itemId = (string) $item->id;
        $planId = (string) $item->plan_id;

        DB::transaction(function () use ($itemId, $planId, $actor, $target, $decision, $reason): void {
            $lockedPlan = ThinkTankProcurementPlan::query()->whereKey($planId)->lockForUpdate()->firstOrFail();
            $lockedItem = ThinkTankProcurementItem::query()
                ->where('plan_id', $lockedPlan->id)
                ->whereKey($itemId)
                ->with('documents')
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(in_array($lockedPlan->status, [
                ThinkTankProcurementPlan::STATUS_SUBMITTED,
                ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
            ], true), 422, 'Items can only be reviewed while the plan is under review.');
            abort_unless($lockedItem->status === ThinkTankProcurementItem::STATUS_SUBMITTED, 422, 'This item has already been reviewed.');

            $previousItemStatus = $lockedItem->status;
            $lockedItem->update([
                'status' => $target,
                'source_activity_status' => ThinkTankProcurementItem::activityStatusFor($target),
                'review_reason' => $reason,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'portal_lock_version' => $lockedItem->nextPortalLockVersion(),
            ]);

            if ($decision !== 'approve') {
                $previousPlanStatus = $lockedPlan->status;
                $lockedPlan->update([
                    'status' => ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
                    'decision_reason' => 'One or more procurement items require action.',
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                ]);
                $this->event(
                    $lockedPlan,
                    null,
                    $actor,
                    'plan_revision_requested',
                    $previousPlanStatus,
                    $lockedPlan->status,
                    'One or more procurement items require action.',
                    [
                        'notification_suppressed' => true,
                        'notification_covered_by' => 'item_'.$decision,
                    ],
                );
            }

            $this->event($lockedPlan, $lockedItem, $actor, 'item_'.$decision, $previousItemStatus, $target, $reason);
        });

        return ThinkTankProcurementItem::query()->findOrFail($itemId);
    }

    public function recordNoObjection(ThinkTankProcurementItem $item, User $actor, array $data): ThinkTankProcurementItem
    {
        $itemId = (string) $item->id;
        $planId = (string) $item->plan_id;

        DB::transaction(function () use ($itemId, $planId, $actor, $data): void {
            $lockedPlan = ThinkTankProcurementPlan::query()->whereKey($planId)->lockForUpdate()->firstOrFail();
            $lockedItem = ThinkTankProcurementItem::query()
                ->where('plan_id', $lockedPlan->id)
                ->whereKey($itemId)
                ->with('documents')
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless(in_array($lockedItem->status, [
                ThinkTankProcurementItem::STATUS_APPROVED,
                ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                ThinkTankProcurementItem::STATUS_PUBLISHED,
            ], true), 422, 'Only an approved or already STEP-cleared item can receive no-objection evidence.');
            if ($lockedItem->status === ThinkTankProcurementItem::STATUS_APPROVED) {
                abort_unless($lockedPlan->status === ThinkTankProcurementPlan::STATUS_APPROVED, 422, 'Approve the full annual plan before recording World Bank no-objection.');
            }
            if (blank($data['step_reference'] ?? null)) {
                throw ValidationException::withMessages(['step_reference' => 'Enter the STEP reference before recording no-objection.']);
            }
            if (blank($data['no_objection_reference'] ?? null)
                && blank($lockedItem->no_objection_reference)
                && ! $lockedItem->documents->contains('document_type', 'no_objection')) {
                throw ValidationException::withMessages([
                    'no_objection_reference' => 'Provide the World Bank reference or attach the no-objection decision document.',
                ]);
            }
            try {
                $decisionDate = Carbon::parse((string) ($data['no_objection_date'] ?? ''))->startOfDay();
            } catch (Throwable) {
                throw ValidationException::withMessages(['no_objection_date' => 'Enter a valid no-objection decision date.']);
            }
            if ($decisionDate->isFuture()) {
                throw ValidationException::withMessages(['no_objection_date' => 'The no-objection decision date cannot be in the future.']);
            }
            $previousStatus = $lockedItem->status;
            $targetStatus = $previousStatus === ThinkTankProcurementItem::STATUS_PUBLISHED
                ? ThinkTankProcurementItem::STATUS_PUBLISHED
                : ThinkTankProcurementItem::STATUS_NO_OBJECTION;

            $updates = [
                'status' => $targetStatus,
                'step_reference' => $data['step_reference'] ?? $lockedItem->step_reference,
                'no_objection_reference' => $data['no_objection_reference'] ?? $lockedItem->no_objection_reference,
                'no_objection_date' => $decisionDate->toDateString(),
                'no_objection_notes' => $data['no_objection_notes'] ?? $lockedItem->no_objection_notes,
                'no_objection_by' => $actor->id,
                'no_objection_recorded_at' => now(),
                'step_activity_status' => ThinkTankProcurementItem::STEP_STATUS_CLEARED,
                'step_status_updated_at' => now(),
                'step_status_updated_by' => $actor->id,
                'updated_by' => $actor->id,
                'portal_lock_version' => $lockedItem->nextPortalLockVersion(),
            ];
            if (blank(data_get($lockedItem->source_payload, 'source_statuses.activity_status'))) {
                $updates['source_activity_status'] = ThinkTankProcurementItem::ACTIVITY_STATUS_WORLD_BANK_APPROVED;
            }
            $lockedItem->update($updates);

            $this->event(
                $lockedPlan,
                $lockedItem,
                $actor,
                $previousStatus === ThinkTankProcurementItem::STATUS_APPROVED
                    ? 'world_bank_no_objection_recorded'
                    : 'world_bank_no_objection_evidence_updated',
                $previousStatus,
                $lockedItem->status,
                $lockedItem->no_objection_notes,
                [
                    'step_reference' => $lockedItem->step_reference,
                    'no_objection_reference' => $lockedItem->no_objection_reference,
                    'no_objection_date' => $lockedItem->no_objection_date?->toDateString(),
                ],
            );
        });

        return ThinkTankProcurementItem::query()->findOrFail($itemId);
    }

    /**
     * Mirror the externally managed STEP activity position without rewriting
     * the immutable Excel source fields or fabricating World Bank evidence.
     *
     * A null STEP status records an append-only comment against the item's
     * current system position. Every call advances the optimistic lock so a
     * worksheet opened before this update cannot silently overwrite it.
     */
    public function syncExternalStepStatus(
        ThinkTankProcurementItem $item,
        User $actor,
        ?string $stepActivityStatus,
        string $comment,
        int $expectedLockVersion,
    ): ThinkTankProcurementItem {
        $stepActivityStatus = filled($stepActivityStatus)
            ? Str::lower(trim((string) $stepActivityStatus))
            : null;
        $comment = trim($comment);

        if ($comment === '') {
            throw ValidationException::withMessages([
                'comment' => 'Add a comment explaining this STEP update.',
            ]);
        }
        if (mb_strlen($comment) < 3) {
            throw ValidationException::withMessages([
                'comment' => 'The STEP update comment must contain at least 3 characters.',
            ]);
        }
        if (mb_strlen($comment) > 5000) {
            throw ValidationException::withMessages([
                'comment' => 'The STEP update comment may not exceed 5,000 characters.',
            ]);
        }
        if ($stepActivityStatus !== null && ! in_array($stepActivityStatus, ['new', 'returned', 'cleared'], true)) {
            throw ValidationException::withMessages([
                'step_activity_status' => 'Select New, Returned, Cleared, or keep the current status.',
            ]);
        }

        $itemId = (string) $item->id;
        $planId = (string) $item->plan_id;

        DB::transaction(function () use (
            $itemId,
            $planId,
            $actor,
            $stepActivityStatus,
            $comment,
            $expectedLockVersion,
        ): void {
            $lockedPlan = ThinkTankProcurementPlan::query()
                ->whereKey($planId)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedItem = ThinkTankProcurementItem::query()
                ->where('plan_id', $lockedPlan->id)
                ->whereKey($itemId)
                ->with('documents')
                ->lockForUpdate()
                ->firstOrFail();

            $currentLockVersion = max(1, (int) ($lockedItem->portal_lock_version ?: 1));
            if ($expectedLockVersion !== $currentLockVersion) {
                throw ValidationException::withMessages([
                    'lock_version' => 'This procurement item changed after you opened it. Reload the worksheet before saving your STEP update.',
                ]);
            }

            $previousStatus = (string) $lockedItem->status;
            $previousPlanStatus = (string) $lockedPlan->status;
            $previousStepStatus = $lockedItem->currentStepActivityStatus();
            $targetStatus = $previousStatus;

            if ($stepActivityStatus !== null) {
                $targetStatus = match ($stepActivityStatus) {
                    'new' => ThinkTankProcurementItem::STATUS_APPROVED,
                    'returned' => ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                    'cleared' => $previousStatus === ThinkTankProcurementItem::STATUS_PUBLISHED
                        ? ThinkTankProcurementItem::STATUS_PUBLISHED
                        : ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                };

                $hasExecution = filled($lockedItem->procurement_id)
                    || $previousStatus === ThinkTankProcurementItem::STATUS_PUBLISHED;
                if ($hasExecution && $stepActivityStatus !== 'cleared') {
                    throw ValidationException::withMessages([
                        'step_activity_status' => 'An item already in procurement execution cannot be moved back to New or Returned.',
                    ]);
                }
            }

            $stepLabel = match ($stepActivityStatus) {
                'new' => ThinkTankProcurementItem::STEP_STATUS_NEW,
                'returned' => ThinkTankProcurementItem::STEP_STATUS_RETURNED,
                'cleared' => ThinkTankProcurementItem::STEP_STATUS_CLEARED,
                default => null,
            };
            $nextLockVersion = $currentLockVersion + 1;
            $updates = [
                'status' => $targetStatus,
                'updated_by' => $actor->id,
                'portal_lock_version' => $nextLockVersion,
            ];
            if ($stepLabel !== null) {
                $updates = [
                    ...$updates,
                    'step_activity_status' => $stepLabel,
                    'step_status_updated_at' => now(),
                    'step_status_updated_by' => $actor->id,
                    'review_reason' => $stepActivityStatus === 'returned' ? $comment : null,
                ];
            }
            $lockedItem->forceFill($updates)->save();

            if ($stepActivityStatus === 'returned'
                && ! in_array($lockedPlan->status, [
                    ThinkTankProcurementPlan::STATUS_DRAFT,
                    ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
                    ThinkTankProcurementPlan::STATUS_REJECTED,
                ], true)) {
                $lockedPlan->forceFill([
                    'status' => ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
                    'review_notes' => $comment,
                    'decision_reason' => $comment,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                    'approved_at' => null,
                    'rejected_at' => null,
                    'portal_lock_version' => max(1, (int) ($lockedPlan->portal_lock_version ?: 1)) + 1,
                ])->save();
                $this->event(
                    $lockedPlan,
                    null,
                    $actor,
                    'external_step_plan_revision_requested',
                    $previousPlanStatus,
                    $lockedPlan->status,
                    'Plan reopened because STEP returned item '.$lockedItem->item_code.'.',
                    [
                        'notification_suppressed' => true,
                        'notification_covered_by' => 'external_step_activity_status_synced',
                        'item_id' => $lockedItem->id,
                    ],
                );
            }
            $hasFormalEvidence = $lockedItem->hasCompleteNoObjectionEvidence();

            $this->event(
                $lockedPlan,
                $lockedItem,
                $actor,
                $stepActivityStatus === null
                    ? 'external_step_comment_added'
                    : 'external_step_activity_status_synced',
                $previousStatus,
                $targetStatus,
                $comment,
                [
                    'external_system' => 'STEP',
                    'external_activity_status' => $stepLabel,
                    'previous_external_activity_status' => $previousStepStatus,
                    'imported_excel_activity_status' => $lockedItem->importedActivityStatus(),
                    'plan_status_from' => $previousPlanStatus,
                    'plan_status_to' => $lockedPlan->status,
                    'source_reference' => $lockedItem->source_reference,
                    'source_file' => $lockedItem->source_file,
                    'source_sheet' => $lockedItem->source_sheet,
                    'source_row' => $lockedItem->source_row,
                    'formal_no_objection_evidence_recorded' => $hasFormalEvidence,
                    'portal_lock_version_from' => $currentLockVersion,
                    'portal_lock_version_to' => $nextLockVersion,
                ],
            );
        });

        return ThinkTankProcurementItem::query()->findOrFail($itemId);
    }

    public function event(
        ThinkTankProcurementPlan $plan,
        ?ThinkTankProcurementItem $item,
        ?User $actor,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $reason = null,
        array $metadata = []
    ): ThinkTankProcurementEvent {
        $plan->loadMissing('member:id,name');
        $item?->loadMissing('documents:id,item_id');
        $metadata = array_merge($metadata, [
            'plan_code' => (string) $plan->plan_code,
            'plan_title' => (string) $plan->title,
            'fiscal_year' => (string) $plan->fiscal_year,
            'think_tank_name' => $plan->member?->name,
            'item_code' => $item?->item_code,
            'item_title' => $item?->title,
            'estimated_amount' => $item?->estimated_amount !== null ? (float) $item->estimated_amount : null,
            'currency' => $item?->currency,
            'document_count' => $item?->documents?->count() ?? 0,
            'actor_name' => $actor?->name,
        ]);
        $event = ThinkTankProcurementEvent::create([
            'plan_id' => $plan->id,
            'item_id' => $item?->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'metadata' => $metadata,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);

        try {
            SystemAuditLog::create([
                'user_id' => $actor?->id,
                'module' => 'think_tank_procurement',
                'action' => $action,
                'action_message' => Str::headline($action),
                'description' => $reason ?: Str::headline($action),
                'method' => request()?->method(),
                'url' => request()?->fullUrl(),
                'route_name' => request()?->route()?->getName(),
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'status_code' => 200,
                'payload' => array_merge($metadata, [
                    'plan_id' => $plan->id,
                    'item_id' => $item?->id,
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                ]),
            ]);
        } catch (Throwable) {
            // The dedicated immutable event remains authoritative if global audit logging is unavailable.
        }

        if ($fromStatus !== null
            && $toStatus !== null
            && $fromStatus !== $toStatus
            && ! (bool) ($metadata['notification_suppressed'] ?? false)) {
            app(ThinkTankProcurementStatusNotificationService::class)->stage($event);
        }

        return $event;
    }
}
