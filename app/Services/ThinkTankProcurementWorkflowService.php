<?php

namespace App\Services;

use App\Mail\ThinkTankProcurementStatusMail;
use App\Models\SystemAuditLog;
use App\Models\ThinkTankProcurementEvent;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
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
        abort_unless($plan->isEditable(), 422, 'This plan cannot be submitted in its current state.');

        $items = $plan->items()->with('documents')->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'plan' => 'Add at least one procurement item before submitting the annual plan.',
            ]);
        }

        $missingTor = $items->reject(fn (ThinkTankProcurementItem $item): bool => $item->hasTermsOfReference());
        if ($missingTor->isNotEmpty()) {
            throw ValidationException::withMessages([
                'documents' => 'A Terms of Reference document is required for every item. Missing: '.$missingTor->pluck('item_code')->implode(', ').'.',
            ]);
        }

        $blocked = $items->whereIn('status', [
            ThinkTankProcurementItem::STATUS_REJECTED,
            ThinkTankProcurementItem::STATUS_NO_OBJECTION,
            ThinkTankProcurementItem::STATUS_PUBLISHED,
        ]);
        if ($blocked->isNotEmpty()) {
            throw ValidationException::withMessages([
                'plan' => 'Correct or remove rejected items before resubmission. Items already in execution cannot be resubmitted.',
            ]);
        }

        $previousStatus = $plan->status;
        DB::transaction(function () use ($plan, $items, $actor, $previousStatus): void {
            $items
                ->whereIn('status', [
                    ThinkTankProcurementItem::STATUS_DRAFT,
                    ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                ])
                ->each(fn (ThinkTankProcurementItem $item) => $item->update([
                    'status' => ThinkTankProcurementItem::STATUS_SUBMITTED,
                    'source_activity_status' => ThinkTankProcurementItem::ACTIVITY_STATUS_SUBMITTED,
                    'review_reason' => null,
                    'updated_by' => $actor->id,
                ]));

            $plan->update([
                'status' => ThinkTankProcurementPlan::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'last_resubmitted_at' => $previousStatus === ThinkTankProcurementPlan::STATUS_DRAFT ? null : now(),
                'version' => $previousStatus === ThinkTankProcurementPlan::STATUS_DRAFT
                    ? max(1, (int) $plan->version)
                    : ((int) $plan->version + 1),
                'decision_reason' => null,
                'rejected_at' => null,
            ]);

            $this->event($plan, null, $actor, 'plan_submitted', $previousStatus, $plan->status, null, [
                'item_count' => $items->count(),
                'estimated_budget' => (float) $items->sum('estimated_amount'),
                'version' => $plan->version,
            ]);
        });

        return $plan->refresh();
    }

    public function decidePlan(ThinkTankProcurementPlan $plan, User $actor, string $decision, ?string $reason): ThinkTankProcurementPlan
    {
        abort_unless(in_array($plan->status, [
            ThinkTankProcurementPlan::STATUS_SUBMITTED,
            ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
        ], true), 422, 'Only submitted plans can be reviewed.');

        if (in_array($decision, ['revision_requested', 'rejected'], true) && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Give the Think Tank a clear reason for this decision.']);
        }

        if ($decision === 'approve' && $plan->items()->whereIn('status', [
            ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
            ThinkTankProcurementItem::STATUS_REJECTED,
        ])->exists()) {
            throw ValidationException::withMessages([
                'decision' => 'Resolve every returned or rejected item before approving the full plan.',
            ]);
        }

        $target = match ($decision) {
            'approve' => ThinkTankProcurementPlan::STATUS_APPROVED,
            'revision_requested' => ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
            'rejected' => ThinkTankProcurementPlan::STATUS_REJECTED,
            default => throw ValidationException::withMessages(['decision' => 'Invalid plan decision.']),
        };
        $previousStatus = $plan->status;

        DB::transaction(function () use ($plan, $actor, $decision, $reason, $target, $previousStatus): void {
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

            $plan->items()
                ->whereIn('status', $reviewableStatuses)
                ->update([
                    'status' => $itemTarget,
                    'source_activity_status' => ThinkTankProcurementItem::activityStatusFor($itemTarget),
                    'review_reason' => $reason,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                    'updated_at' => now(),
                ]);

            $plan->update([
                'status' => $target,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $reason,
                'decision_reason' => $reason,
                'approved_at' => $decision === 'approve' ? now() : null,
                'rejected_at' => $decision === 'rejected' ? now() : null,
            ]);

            $this->event($plan, null, $actor, 'plan_'.$decision, $previousStatus, $target, $reason);
        });

        $fresh = $plan->fresh(['member.portalUser', 'member.portalUsers']);
        $this->notifyMember(
            $fresh,
            $decision === 'approve' ? 'Procurement plan approved by ATTP Secretariat' : 'Procurement plan action required',
            match ($decision) {
                'approve' => 'The ATTP Secretariat approved your annual procurement plan. Approved items can now proceed to STEP and World Bank no-objection processing.',
                'revision_requested' => 'The ATTP Procurement Officer returned your annual procurement plan for correction. Review the action note, update the affected items and resubmit.',
                default => 'The ATTP Procurement Officer rejected the annual procurement plan. Review the reason before preparing a replacement submission.',
            },
            null,
            $reason
        );

        return $fresh;
    }

    public function reviewItem(ThinkTankProcurementItem $item, User $actor, string $decision, ?string $reason): ThinkTankProcurementItem
    {
        $plan = $item->plan;
        abort_unless(
            $plan && in_array($plan->status, [
                ThinkTankProcurementPlan::STATUS_SUBMITTED,
                ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
            ], true),
            422,
            'Items can only be reviewed while the plan is under review.'
        );
        abort_unless($item->status === ThinkTankProcurementItem::STATUS_SUBMITTED, 422, 'This item has already been reviewed.');

        if (in_array($decision, ['revision_requested', 'rejected'], true) && blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Give a reason for returning or rejecting this item.']);
        }

        $target = match ($decision) {
            'approve' => ThinkTankProcurementItem::STATUS_APPROVED,
            'revision_requested' => ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
            'rejected' => ThinkTankProcurementItem::STATUS_REJECTED,
            default => throw ValidationException::withMessages(['decision' => 'Invalid item decision.']),
        };
        $previousStatus = $item->status;

        DB::transaction(function () use ($item, $plan, $actor, $target, $decision, $reason, $previousStatus): void {
            $item->update([
                'status' => $target,
                'source_activity_status' => ThinkTankProcurementItem::activityStatusFor($target),
                'review_reason' => $reason,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            if ($decision !== 'approve') {
                $plan->update([
                    'status' => ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
                    'decision_reason' => 'One or more procurement items require action.',
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => now(),
                ]);
            }

            $this->event($plan, $item, $actor, 'item_'.$decision, $previousStatus, $target, $reason);
        });

        $freshPlan = $plan->fresh(['member.portalUser', 'member.portalUsers']);
        $this->notifyMember(
            $freshPlan,
            $decision === 'approve' ? 'Procurement item approved by ATTP Secretariat' : 'Procurement item action required',
            $decision === 'approve'
                ? 'An item in your annual procurement plan has been approved by the ATTP Secretariat.'
                : 'An item in your annual procurement plan needs your attention before the plan can be resubmitted.',
            $item->fresh(),
            $reason
        );

        return $item->fresh();
    }

    public function recordNoObjection(ThinkTankProcurementItem $item, User $actor, array $data): ThinkTankProcurementItem
    {
        abort_unless($item->status === ThinkTankProcurementItem::STATUS_APPROVED, 422, 'Only approved items can receive a no-objection decision.');
        abort_unless($item->plan?->status === ThinkTankProcurementPlan::STATUS_APPROVED, 422, 'Approve the full annual plan before recording World Bank no-objection.');
        $previousStatus = $item->status;

        $item->update([
            'status' => ThinkTankProcurementItem::STATUS_NO_OBJECTION,
            'source_activity_status' => ThinkTankProcurementItem::ACTIVITY_STATUS_WORLD_BANK_APPROVED,
            'step_reference' => $data['step_reference'] ?? $item->step_reference,
            'no_objection_reference' => $data['no_objection_reference'] ?? null,
            'no_objection_date' => $data['no_objection_date'],
            'no_objection_notes' => $data['no_objection_notes'] ?? null,
            'no_objection_by' => $actor->id,
            'no_objection_recorded_at' => now(),
        ]);

        $plan = $item->plan;
        $this->event($plan, $item, $actor, 'world_bank_no_objection_recorded', $previousStatus, $item->status, null, [
            'step_reference' => $item->step_reference,
            'no_objection_reference' => $item->no_objection_reference,
            'no_objection_date' => $item->no_objection_date?->toDateString(),
        ]);

        $freshPlan = $plan->fresh(['member.portalUser', 'member.portalUsers']);
        $this->notifyMember(
            $freshPlan,
            'Approved by World Bank — No Objection',
            'The ATTP Procurement Officer recorded the World Bank no-objection for this item. Its activity status is now Approved by World Bank — No Objection, and your procurement officer may configure the application form and publish the opportunity.',
            $item->fresh(),
            $item->no_objection_notes
        );

        return $item->fresh();
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

        return $event;
    }

    public function notifyMember(
        ThinkTankProcurementPlan $plan,
        string $heading,
        string $message,
        ?ThinkTankProcurementItem $item = null,
        ?string $reason = null
    ): void {
        $member = $plan->member;
        if (! $member) {
            return;
        }

        $emails = collect([$member->email, $member->portalUser?->email])
            ->merge($member->portalUsers?->pluck('email') ?? [])
            ->filter(fn ($email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            return;
        }

        $url = Route::has('think-tank.procurement-plans.show')
            ? route('think-tank.procurement-plans.show', $plan)
            : url('/think-tank/procurement-plans');

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new ThinkTankProcurementStatusMail(
                    $plan,
                    $heading,
                    $message,
                    $url,
                    $item,
                    $reason
                ));
            } catch (Throwable $exception) {
                logger()->warning('Think Tank procurement workflow email could not be sent.', [
                    'plan_id' => $plan->id,
                    'item_id' => $item?->id,
                    'recipient' => $email,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
