<?php

namespace App\Services;

use App\Models\ConsortiumFundAllocation;
use App\Models\ConsortiumThinkTank;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\SystemAuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ThinkTankHistoricalFundingBackfillService
{
    public function __construct(private readonly ThinkTankFundingSourceService $sources) {}

    /** @return array<string, mixed> */
    public function preview(): array
    {
        return $this->reconcile(false);
    }

    /** @return array<string, mixed> */
    public function apply(int $expectedCount, string $expectedPlanHash): array
    {
        return DB::transaction(function () use ($expectedCount, $expectedPlanHash): array {
            $result = $this->reconcile(true, $expectedCount, $expectedPlanHash);
            if ($result['errors'] !== []) {
                throw new RuntimeException(
                    'Historical Think Tank funding backfill stopped before writes: '.implode(' | ', $result['errors'])
                );
            }

            return $result;
        }, 3);
    }

    /** @return array<string, mixed> */
    private function reconcile(
        bool $apply,
        ?int $expectedCount = null,
        ?string $expectedPlanHash = null,
    ): array {
        $source = $this->sources->resolve();
        $purchaseOrdersQuery = $this->sources->historicalAwardCandidatesQuery()
            ->orderBy('id');
        // Resolve vendor identities without locks, then take the shared
        // finance lock prefix (member before PO/payment) used by live writers.
        $purchaseOrderIdentities = (clone $purchaseOrdersQuery)->get();
        $identityIds = $purchaseOrderIdentities->pluck('id')->map(fn ($id): string => (string) $id);
        $vendorIds = $purchaseOrderIdentities->pluck('vendor_id')->filter()->unique()->values();
        $membersQuery = ConsortiumThinkTank::query()
            ->whereIn('vendor_user_id', $vendorIds)
            ->orderBy('id');
        if ($apply) {
            $membersQuery->lockForUpdate();
        }
        $membersByVendor = $membersQuery->get()->groupBy(
            fn (ConsortiumThinkTank $member): string => (string) $member->vendor_user_id
        );

        $purchaseOrders = $apply
            ? (clone $purchaseOrdersQuery)->whereKey($identityIds->all())->lockForUpdate()->get()
            : $purchaseOrderIdentities;
        $purchaseOrderIds = $purchaseOrders->pluck('id')->map(fn ($id): string => (string) $id);

        $paymentsQuery = ProcurementDisbursement::query()
            ->whereIn('purchase_order_id', $purchaseOrderIds)
            ->orderBy('id');
        if ($apply) {
            $paymentsQuery->lockForUpdate();
        }
        $payments = $paymentsQuery->get()->groupBy(
            fn (ProcurementDisbursement $payment): string => (string) $payment->purchase_order_id
        );

        $allocationsQuery = ConsortiumFundAllocation::query()
            ->whereIn('source_purchase_order_id', $purchaseOrderIds)
            ->orderBy('id');
        if ($apply) {
            $allocationsQuery->lockForUpdate();
        }
        $allocations = $allocationsQuery->get()->keyBy(
            fn (ConsortiumFundAllocation $allocation): string => (string) $allocation->source_purchase_order_id
        );

        $errors = [];
        if ($apply && $purchaseOrderIds->sort()->values()->all() !== $identityIds->sort()->values()->all()) {
            $errors[] = 'The authoritative award set changed while locks were being acquired; retry the dry run.';
        }
        $plans = collect();
        foreach ($purchaseOrders as $purchaseOrder) {
            $poId = (string) $purchaseOrder->id;
            $reference = trim((string) $purchaseOrder->reference_no) ?: $poId;
            $vendorId = filled($purchaseOrder->vendor_id) ? (string) $purchaseOrder->vendor_id : null;
            $matches = $vendorId ? ($membersByVendor->get($vendorId) ?? collect()) : collect();
            if ($matches->count() !== 1) {
                $errors[] = "Purchase order {$reference} requires exactly one vendor_user_id membership match; found {$matches->count()}.";

                continue;
            }
            /** @var ConsortiumThinkTank $member */
            $member = $matches->first();
            if ($member->status !== 'active') {
                $errors[] = "Purchase order {$reference} maps to an inactive think-tank membership.";

                continue;
            }
            if (! filled($member->consortium_id)) {
                $errors[] = "Purchase order {$reference} maps to a membership with no consortium.";

                continue;
            }
            if (! $this->isNullOrSame($purchaseOrder->think_tank_member_id, $member->id)
                || ! $this->isNullOrSame($purchaseOrder->consortium_id, $member->consortium_id)) {
                $errors[] = "Purchase order {$reference} already carries conflicting tenant ownership.";

                continue;
            }

            try {
                $poCents = $this->cents($purchaseOrder->amount);
            } catch (RuntimeException $exception) {
                $errors[] = "Purchase order {$reference}: {$exception->getMessage()}";

                continue;
            }
            if ($poCents <= 0) {
                $errors[] = "Purchase order {$reference} must have a positive USD amount.";

                continue;
            }

            /** @var Collection<int, ProcurementDisbursement> $poPayments */
            $poPayments = $payments->get($poId) ?? collect();
            $recognizedCents = 0;
            $paymentConflict = false;
            foreach ($poPayments as $payment) {
                if (Str::upper(trim((string) $payment->currency)) !== 'USD') {
                    $errors[] = "Payment {$payment->id} for {$reference} is not explicitly USD.";
                    $paymentConflict = true;

                    continue;
                }
                if (! $this->isNullOrSame($payment->think_tank_member_id, $member->id)
                    || ! $this->isNullOrSame($payment->consortium_id, $member->consortium_id)
                    || ! $this->isNullOrSame($payment->sub_activity_id, $source['subActivity']->id)) {
                    $errors[] = "Payment {$payment->id} for {$reference} carries conflicting tenant or source ownership.";
                    $paymentConflict = true;

                    continue;
                }
                if ($this->isRecognized($payment)) {
                    try {
                        $recognizedCents = $this->safeAdd($recognizedCents, $this->cents($payment->amount));
                    } catch (RuntimeException $exception) {
                        $errors[] = "Payment {$payment->id} for {$reference}: {$exception->getMessage()}";
                        $paymentConflict = true;
                    }
                }
            }
            if ($paymentConflict) {
                continue;
            }
            if ($recognizedCents > $poCents) {
                $errors[] = "Recognized payments for {$reference} exceed its purchase-order amount.";

                continue;
            }

            /** @var ConsortiumFundAllocation|null $allocation */
            $allocation = $allocations->get($poId);
            if ($allocation && (
                (string) $allocation->think_tank_member_id !== (string) $member->id
                || (string) $allocation->consortium_id !== (string) $member->consortium_id
                || (string) $allocation->program_funding_id !== (string) $source['programFunding']->id
                || Str::upper(trim((string) $allocation->currency)) !== 'USD'
            )) {
                $errors[] = "The source allocation for {$reference} carries conflicting ownership or currency.";

                continue;
            }
            if ($poPayments->contains(function (ProcurementDisbursement $payment) use ($allocation): bool {
                if (! filled($payment->fund_allocation_id)) {
                    return false;
                }

                return ! $allocation || (string) $payment->fund_allocation_id !== (string) $allocation->id;
            })) {
                $errors[] = "One or more payments for {$reference} are linked to a different allocation.";

                continue;
            }

            $allocationAttributes = [
                'consortium_id' => (string) $member->consortium_id,
                'think_tank_member_id' => (string) $member->id,
                'program_funding_id' => (string) $source['programFunding']->id,
                'source_purchase_order_id' => $poId,
                'currency' => 'USD',
                'amount_allocated' => $this->money($poCents),
                'amount_committed' => $this->money($poCents),
                'amount_disbursed' => $this->money($recognizedCents),
            ];
            $poNeedsUpdate = (string) $purchaseOrder->think_tank_member_id !== (string) $member->id
                || (string) $purchaseOrder->consortium_id !== (string) $member->consortium_id;
            $allocationNeedsUpdate = $allocation
                ? $this->attributesDiffer($allocation, $allocationAttributes)
                : false;
            $paymentsNeedingUpdate = $poPayments->filter(
                fn (ProcurementDisbursement $payment): bool => (string) $payment->think_tank_member_id !== (string) $member->id
                    || (string) $payment->consortium_id !== (string) $member->consortium_id
                    || (string) $payment->sub_activity_id !== (string) $source['subActivity']->id
                    || ! $allocation
                    || (string) $payment->fund_allocation_id !== (string) $allocation->id
                    || ($this->isRecognized($payment) && blank($payment->recipient_confirmation_status))
            );

            $plans->push([
                'purchaseOrder' => $purchaseOrder,
                'member' => $member,
                'payments' => $poPayments,
                'allocation' => $allocation,
                'allocationAttributes' => $allocationAttributes,
                'poNeedsUpdate' => $poNeedsUpdate,
                'allocationNeedsUpdate' => $allocationNeedsUpdate,
                'paymentsNeedingUpdate' => $paymentsNeedingUpdate->count(),
                'recognizedPayments' => $poPayments->filter(fn (ProcurementDisbursement $payment): bool => $this->isRecognized($payment))->count(),
                'recognizedCents' => $recognizedCents,
            ]);
        }

        $summary = [
            'dryRun' => ! $apply,
            'source' => [
                'programCode' => ThinkTankFundingSourceService::PROGRAM_CODE,
                'componentCode' => ThinkTankFundingSourceService::COMPONENT_CODE,
                'subActivityId' => (string) $source['subActivity']->id,
                'programFundingId' => (string) $source['programFunding']->id,
            ],
            'candidates' => $purchaseOrders->count(),
            'ready' => $plans->count(),
            'recognizedPayments' => $plans->sum('recognizedPayments'),
            'wouldCreateAllocations' => $plans->whereNull('allocation')->count(),
            'wouldUpdateAllocations' => $plans->where('allocationNeedsUpdate', true)->count(),
            'wouldUpdatePurchaseOrders' => $plans->where('poNeedsUpdate', true)->count(),
            'wouldUpdatePayments' => $plans->sum('paymentsNeedingUpdate'),
            'errors' => $errors,
            'rows' => $plans->map(fn (array $plan): array => [
                'purchaseOrderId' => (string) $plan['purchaseOrder']->id,
                'referenceNumber' => (string) $plan['purchaseOrder']->reference_no,
                'thinkTankMemberId' => (string) $plan['member']->id,
                'thinkTankName' => (string) $plan['member']->name,
                'consortiumId' => (string) $plan['member']->consortium_id,
                'amount' => (string) $plan['purchaseOrder']->amount,
                'recognizedPaid' => $this->money((int) $plan['recognizedCents']),
                'currentPurchaseOrder' => [
                    'vendorId' => $plan['purchaseOrder']->vendor_id
                        ? (string) $plan['purchaseOrder']->vendor_id
                        : null,
                    'thinkTankMemberId' => $plan['purchaseOrder']->think_tank_member_id
                        ? (string) $plan['purchaseOrder']->think_tank_member_id
                        : null,
                    'consortiumId' => $plan['purchaseOrder']->consortium_id
                        ? (string) $plan['purchaseOrder']->consortium_id
                        : null,
                    'poType' => $plan['purchaseOrder']->po_type ?: null,
                    'status' => (string) $plan['purchaseOrder']->status,
                    'currency' => Str::upper(trim((string) $plan['purchaseOrder']->currency)),
                    'issuedAt' => $plan['purchaseOrder']->issued_at?->toIso8601String(),
                    'subActivityId' => $plan['purchaseOrder']->sub_activity_id
                        ? (string) $plan['purchaseOrder']->sub_activity_id
                        : null,
                    'budgetCommitmentId' => $plan['purchaseOrder']->budget_commitment_id
                        ? (string) $plan['purchaseOrder']->budget_commitment_id
                        : null,
                ],
                'paymentCount' => $plan['payments']->count(),
                'paymentManifest' => $plan['payments']
                    ->sortBy(fn (ProcurementDisbursement $payment): string => (string) $payment->id)
                    ->map(fn (ProcurementDisbursement $payment): array => [
                        'id' => (string) $payment->id,
                        'referenceNumber' => (string) $payment->reference_no,
                        'amount' => (string) $payment->amount,
                        'currency' => Str::upper(trim((string) $payment->currency)),
                        'status' => (string) $payment->status,
                        'paidAt' => $payment->paid_at?->toIso8601String(),
                        'thinkTankMemberId' => $payment->think_tank_member_id
                            ? (string) $payment->think_tank_member_id
                            : null,
                        'consortiumId' => $payment->consortium_id ? (string) $payment->consortium_id : null,
                        'subActivityId' => $payment->sub_activity_id ? (string) $payment->sub_activity_id : null,
                        'fundAllocationId' => $payment->fund_allocation_id
                            ? (string) $payment->fund_allocation_id
                            : null,
                        'receiptStatus' => $payment->recipient_confirmation_status ?: null,
                    ])
                    ->values()
                    ->all(),
                'allocationId' => $plan['allocation']?->id ? (string) $plan['allocation']->id : null,
                'currentAllocation' => $plan['allocation'] ? [
                    'thinkTankMemberId' => (string) $plan['allocation']->think_tank_member_id,
                    'consortiumId' => (string) $plan['allocation']->consortium_id,
                    'programFundingId' => (string) $plan['allocation']->program_funding_id,
                    'sourcePurchaseOrderId' => (string) $plan['allocation']->source_purchase_order_id,
                    'currency' => Str::upper(trim((string) $plan['allocation']->currency)),
                    'amountAllocated' => (string) $plan['allocation']->amount_allocated,
                    'amountCommitted' => (string) $plan['allocation']->amount_committed,
                    'amountDisbursed' => (string) $plan['allocation']->amount_disbursed,
                    'amountSpent' => (string) $plan['allocation']->amount_spent,
                    'status' => (string) $plan['allocation']->status,
                ] : null,
                'willCreateAllocation' => $plan['allocation'] === null,
                'willUpdateAllocation' => (bool) $plan['allocationNeedsUpdate'],
                'willUpdatePurchaseOrder' => (bool) $plan['poNeedsUpdate'],
                'paymentsNeedingUpdate' => (int) $plan['paymentsNeedingUpdate'],
            ])->values()->all(),
        ];
        $summary['planHash'] = $this->planHash($summary);
        if ($apply && $expectedCount !== $summary['candidates']) {
            $summary['errors'][] = "Candidate count changed: expected {$expectedCount}, found {$summary['candidates']}. Run a new dry run.";
        }
        if ($apply && (
            ! is_string($expectedPlanHash)
            || preg_match('/^[a-f0-9]{64}$/', $expectedPlanHash) !== 1
            || ! hash_equals($summary['planHash'], $expectedPlanHash)
        )) {
            $summary['errors'][] = 'The dry-run plan hash does not match the current authoritative award plan. Run a new dry run.';
        }
        if (! $apply || $summary['errors'] !== []) {
            return $summary;
        }

        $changes = [
            'allocationsCreated' => 0,
            'allocationsUpdated' => 0,
            'purchaseOrdersUpdated' => 0,
            'paymentsUpdated' => 0,
        ];
        $appliedPurchaseOrderIds = [];
        foreach ($plans as $plan) {
            /** @var ConsortiumFundAllocation|null $allocation */
            $allocation = $plan['allocation'];
            if (! $allocation) {
                $allocation = ConsortiumFundAllocation::query()->create([
                    ...$plan['allocationAttributes'],
                    'budget_line' => Str::limit(
                        'Think Tank award '.((string) $plan['purchaseOrder']->reference_no ?: $plan['purchaseOrder']->id),
                        255,
                        ''
                    ),
                    'amount_spent' => '0.00',
                    'status' => 'active',
                    'notes' => 'Authoritative historical award linkage created by the dry-run-gated finance backfill.',
                ]);
                $changes['allocationsCreated']++;
            } elseif ($plan['allocationNeedsUpdate']) {
                $allocation->update($plan['allocationAttributes']);
                $changes['allocationsUpdated']++;
            }

            /** @var ProcurementPurchaseOrder $purchaseOrder */
            $purchaseOrder = $plan['purchaseOrder'];
            if ($plan['poNeedsUpdate']) {
                $purchaseOrder->update([
                    'consortium_id' => $plan['member']->consortium_id,
                    'think_tank_member_id' => $plan['member']->id,
                ]);
                $changes['purchaseOrdersUpdated']++;
            }

            /** @var ProcurementDisbursement $payment */
            foreach ($plan['payments'] as $payment) {
                $attributes = [
                    'consortium_id' => $plan['member']->consortium_id,
                    'think_tank_member_id' => $plan['member']->id,
                    'sub_activity_id' => $source['subActivity']->id,
                    'fund_allocation_id' => $allocation->id,
                ];
                if ($this->isRecognized($payment) && blank($payment->recipient_confirmation_status)) {
                    $attributes['recipient_confirmation_status'] = 'pending';
                }
                if ($this->attributesDiffer($payment, $attributes)) {
                    $payment->update($attributes);
                    $changes['paymentsUpdated']++;
                }
            }
            $appliedPurchaseOrderIds[] = (string) $purchaseOrder->id;
        }

        $changeCount = array_sum($changes);
        if ($changeCount > 0) {
            SystemAuditLog::query()->create([
                'module' => 'think_tank_finance',
                'action' => 'historical_award_funding_backfilled',
                'action_message' => 'Historical Funding-to-Think-Tanks awards linked to tenant finance.',
                'description' => 'Dry-run-gated, idempotent source ownership and allocation linkage for authoritative award payments.',
                'method' => 'CLI',
                'route_name' => 'think-tank:finance:backfill-awards',
                'status_code' => 200,
                'payload' => [
                    'source' => $summary['source'],
                    'changes' => $changes,
                    'purchase_order_ids' => $appliedPurchaseOrderIds,
                ],
            ]);
        }

        return [
            ...$summary,
            'dryRun' => false,
            ...$changes,
            'changed' => $changeCount > 0,
        ];
    }

    private function isRecognized(ProcurementDisbursement $payment): bool
    {
        return $payment->paid_at !== null
            && $payment->paid_at->lte(now())
            && in_array(
                Str::lower((string) $payment->status),
                ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES,
                true,
            );
    }

    private function isNullOrSame(mixed $actual, mixed $expected): bool
    {
        return blank($actual) || (string) $actual === (string) $expected;
    }

    /** @param array<string, mixed> $attributes */
    private function attributesDiffer(object $model, array $attributes): bool
    {
        foreach ($attributes as $key => $expected) {
            if (in_array($key, ['amount_allocated', 'amount_committed', 'amount_disbursed', 'amount_spent'], true)) {
                if ($this->cents($model->{$key}) !== $this->cents($expected)) {
                    return true;
                }

                continue;
            }
            if ((string) ($model->{$key} ?? '') !== (string) ($expected ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function cents(mixed $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        if (preg_match('/^(\d{1,16})(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
            throw new RuntimeException('amount is outside DECIMAL(18,2) or has more than two decimal places.');
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function safeAdd(int $left, int $right): int
    {
        if ($right > PHP_INT_MAX - $left) {
            throw new RuntimeException('aggregate amount exceeds safe integer-cents capacity.');
        }

        return $left + $right;
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $summary */
    private function planHash(array $summary): string
    {
        return hash('sha256', json_encode([
            'source' => $summary['source'],
            'candidates' => $summary['candidates'],
            'ready' => $summary['ready'],
            'recognizedPayments' => $summary['recognizedPayments'],
            'wouldCreateAllocations' => $summary['wouldCreateAllocations'],
            'wouldUpdateAllocations' => $summary['wouldUpdateAllocations'],
            'wouldUpdatePurchaseOrders' => $summary['wouldUpdatePurchaseOrders'],
            'wouldUpdatePayments' => $summary['wouldUpdatePayments'],
            'errors' => $summary['errors'],
            'rows' => $summary['rows'],
        ], JSON_THROW_ON_ERROR));
    }
}
