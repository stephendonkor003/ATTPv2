<?php

namespace App\Services;

use App\Models\ConsortiumFundAllocation;
use App\Models\ConsortiumThinkTank;
use App\Models\Procurement;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ThinkTankBudgetLine;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Support\ExactMoney;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ThinkTankProcurementBudgetGuard
{
    private const COMMITTED_PURCHASE_ORDER_STATUSES = [
        'issued',
        'pending',
        'partial_paid',
        'partially_paid',
        'paid',
        'fully_paid',
        'closed',
    ];

    /**
     * Lock and validate the finance allocation before a cleared planning item
     * is converted into a procurement execution.
     */
    public function lockPlanningItem(
        ConsortiumThinkTank $member,
        string $itemId,
    ): ThinkTankProcurementItem {
        [$lockedMember, $budgetLines] = $this->lockTenantBudgetContext($member);

        $item = ThinkTankProcurementItem::query()
            ->whereKey($itemId)
            ->whereHas('plan', fn ($query) => $query
                ->where('think_tank_member_id', $lockedMember->id)
                ->where('consortium_id', $lockedMember->consortium_id))
            ->with('documents')
            ->lockForUpdate()
            ->firstOrFail();
        $plan = $this->lockPlan($lockedMember, (string) $item->plan_id);
        $item->setRelation('plan', $plan);
        $line = $this->budgetLineForItem($budgetLines, $item);

        $this->assertCoverage(
            $lockedMember,
            $budgetLines,
            $line,
            $item,
            $plan,
            null,
            collect(),
            collect(),
        );

        return $item;
    }

    /**
     * @return array{procurement: Procurement, item: ThinkTankProcurementItem, budgetLine: ThinkTankBudgetLine, purchaseOrders: Collection, disbursements: Collection}
     */
    public function lockExecution(
        ConsortiumThinkTank $member,
        string $procurementId,
    ): array {
        [$lockedMember, $budgetLines] = $this->lockTenantBudgetContext($member);

        $procurement = Procurement::query()
            ->whereKey($procurementId)
            ->where('procurement_owner_type', 'think_tank')
            ->where('think_tank_member_id', $lockedMember->id)
            ->where('consortium_id', $lockedMember->consortium_id)
            ->whereNotNull('think_tank_procurement_plan_id')
            ->lockForUpdate()
            ->firstOrFail();

        return $this->lockedExecutionContext($lockedMember, $budgetLines, $procurement);
    }

    /**
     * Validate a proposed purchase-order amount while holding the same tenant
     * and budget-line locks used by the finance allocation editor.
     */
    public function assertPurchaseOrderBoundary(
        Procurement $procurement,
        mixed $amount,
        mixed $currency,
        string $status,
        ?ProcurementPurchaseOrder $existing = null,
        mixed $commitmentYear = null,
    ): Procurement {
        if (! $this->isThinkTankProcurement($procurement)) {
            return $procurement;
        }

        $member = $this->memberIdentityFor($procurement);
        [$lockedMember, $budgetLines] = $this->lockTenantBudgetContext($member);
        $lockedProcurement = Procurement::query()
            ->whereKey($procurement->id)
            ->where('procurement_owner_type', 'think_tank')
            ->where('think_tank_member_id', $lockedMember->id)
            ->where('consortium_id', $lockedMember->consortium_id)
            ->lockForUpdate()
            ->firstOrFail();
        $context = $this->lockedExecutionContext(
            $lockedMember,
            $budgetLines,
            $lockedProcurement,
            false,
            $existing ? (string) $existing->id : null,
        );
        $line = $context['budgetLine'];
        $lockedExisting = $existing
            ? $context['purchaseOrders']->first(
                fn (ProcurementPurchaseOrder $order): bool => (string) $order->id === (string) $existing->id
            )
            : null;
        if ($existing && ! $lockedExisting instanceof ProcurementPurchaseOrder) {
            abort(404);
        }
        if ($lockedExisting instanceof ProcurementPurchaseOrder) {
            $this->assertExistingPurchaseOrderIdentity(
                $lockedExisting,
                $lockedProcurement,
                $context['disbursements'],
            );
        }
        $proposedCents = $this->cents($amount);

        if ($proposedCents <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['A Think Tank purchase order must have a positive amount.'],
            ]);
        }

        $lineCurrency = $this->currency($line->currency);
        if ($this->currency($currency) !== $lineCurrency) {
            throw ValidationException::withMessages([
                'currency' => ['The purchase-order currency must match the linked internal budget line.'],
            ]);
        }
        if ($commitmentYear !== null) {
            $this->assertCommitmentFiscalYear($line, $commitmentYear);
        }

        $this->assertPurchaseOrderProjection(
            $line,
            $context['item'],
            $lockedProcurement,
            $context['purchaseOrders'],
            $context['disbursements'],
            $proposedCents,
            $status,
            $lockedExisting,
        );

        return $lockedProcurement;
    }

    /**
     * Lock and prove that a purchase order is still safe to hard-delete.
     * Tenant-governed orders, source awards, and recorded payments are
     * accounting records and must use cancellation/reversal workflows.
     */
    public function lockHardDeleteBoundary(
        ProcurementPurchaseOrder $purchaseOrder,
    ): ProcurementPurchaseOrder {
        $identity = ProcurementPurchaseOrder::query()
            ->whereKey($purchaseOrder->id)
            ->with('procurement')
            ->firstOrFail();
        $this->assertPurchaseOrderIsNotGovernedAccountingRecord($identity);

        $locked = ProcurementPurchaseOrder::query()
            ->whereKey($purchaseOrder->id)
            ->with('procurement')
            ->lockForUpdate()
            ->firstOrFail();
        $this->assertPurchaseOrderIsNotGovernedAccountingRecord($locked);

        $payments = ProcurementDisbursement::query()
            ->where('purchase_order_id', $locked->id)
            ->lockForUpdate()
            ->get();
        if ($payments->contains(
            fn (ProcurementDisbursement $payment): bool => $this->disbursementPreventsHardDelete($payment)
        )) {
            throw ValidationException::withMessages([
                'purchase_order' => ['A purchase order with recorded or reversed payments cannot be hard-deleted. Use the reversal workflow.'],
            ]);
        }

        $hasSourceAllocation = ConsortiumFundAllocation::query()
            ->where('source_purchase_order_id', $locked->id)
            ->lockForUpdate()
            ->exists();
        if ($hasSourceAllocation) {
            throw ValidationException::withMessages([
                'purchase_order' => ['A purchase order that is the source of a Think Tank fund allocation cannot be hard-deleted.'],
            ]);
        }

        return $locked;
    }

    /**
     * Lock a payment boundary, normalize legacy null tenant columns, and prove
     * that the projected recognized payments remain inside both the PO and its
     * linked internal budget line.
     *
     * @param  array<int, array<string, mixed>>  $paymentRows
     * @param  array<int, string>  $replacePaymentIds
     * @param  array<int, string>  $deletePaymentIds
     */
    public function lockPaymentBoundary(
        ProcurementPurchaseOrder $purchaseOrder,
        array $paymentRows,
        array $replacePaymentIds = [],
        array $deletePaymentIds = [],
    ): ProcurementPurchaseOrder {
        $identity = ProcurementPurchaseOrder::query()
            ->whereKey($purchaseOrder->id)
            ->with(['procurement', 'deliverables:id,procurement_id'])
            ->firstOrFail();
        $procurement = $identity->procurement;

        if (! $procurement) {
            $linkedProcurementIds = $identity->deliverables
                ->pluck('procurement_id')
                ->filter()
                ->map(fn ($id): string => (string) $id)
                ->unique()
                ->values();
            if ($linkedProcurementIds->count() > 1) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => ['This legacy purchase order combines deliverables from different procurements and must be corrected before payment.'],
                ]);
            }
            if ($linkedProcurementIds->count() === 1) {
                $candidate = Procurement::query()->find($linkedProcurementIds->first());
                if ($candidate && $this->isThinkTankProcurement($candidate)) {
                    $procurement = $candidate;
                }
            }
        }

        if (! $procurement || ! $this->isThinkTankProcurement($procurement)) {
            if (($identity->po_type ?? 'procurement') !== 'think_tank_transfer'
                && (filled($identity->think_tank_member_id) || filled($identity->consortium_id))) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => ['This Think Tank purchase order is missing its authoritative procurement link.'],
                ]);
            }

            return ProcurementPurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $member = $this->memberIdentityFor($procurement);
        [$lockedMember, $budgetLines] = $this->lockTenantBudgetContext($member);
        $lockedProcurement = Procurement::query()
            ->whereKey($procurement->id)
            ->where('procurement_owner_type', 'think_tank')
            ->where('think_tank_member_id', $lockedMember->id)
            ->where('consortium_id', $lockedMember->consortium_id)
            ->lockForUpdate()
            ->firstOrFail();
        $context = $this->lockedExecutionContext(
            $lockedMember,
            $budgetLines,
            $lockedProcurement,
            false,
            (string) $identity->id,
        );
        $lockedOrder = $context['purchaseOrders']->first(
            fn (ProcurementPurchaseOrder $order): bool => (string) $order->id === (string) $identity->id
        );
        abort_unless($lockedOrder instanceof ProcurementPurchaseOrder, 404);

        $this->normalizePurchaseOrderTenant($lockedOrder, $lockedProcurement);
        $line = $context['budgetLine'];
        $lineCurrency = $this->currency($line->currency);
        if ($this->currency($lockedOrder->currency) !== $lineCurrency) {
            throw ValidationException::withMessages([
                'payments' => ['The purchase-order currency does not match its linked internal budget line.'],
            ]);
        }

        $this->assertPaymentProjection(
            $line,
            $lockedOrder,
            $context['purchaseOrders'],
            $context['disbursements'],
            $paymentRows,
            $replacePaymentIds,
            $deletePaymentIds,
        );

        return $lockedOrder->refresh();
    }

    /**
     * @return array{0: ConsortiumThinkTank, 1: Collection}
     */
    private function lockTenantBudgetContext(ConsortiumThinkTank $member): array
    {
        $lockedMember = ConsortiumThinkTank::query()
            ->whereKey($member->id)
            ->where('consortium_id', $member->consortium_id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->firstOrFail();
        $budgetLines = ThinkTankBudgetLine::query()
            ->where('think_tank_member_id', $lockedMember->id)
            ->where('consortium_id', $lockedMember->consortium_id)
            ->lockForUpdate()
            ->get();

        return [$lockedMember, $budgetLines];
    }

    /**
     * @return array{procurement: Procurement, item: ThinkTankProcurementItem, budgetLine: ThinkTankBudgetLine, purchaseOrders: Collection, disbursements: Collection}
     */
    private function lockedExecutionContext(
        ConsortiumThinkTank $member,
        Collection $budgetLines,
        Procurement $procurement,
        bool $enforceCurrentUsage = true,
        ?string $includePurchaseOrderId = null,
    ): array {
        $item = ThinkTankProcurementItem::query()
            ->where('procurement_id', $procurement->id)
            ->where('plan_id', $procurement->think_tank_procurement_plan_id)
            ->whereHas('plan', fn ($query) => $query
                ->where('think_tank_member_id', $member->id)
                ->where('consortium_id', $member->consortium_id))
            ->with('documents')
            ->lockForUpdate()
            ->firstOrFail();
        $plan = $this->lockPlan($member, (string) $item->plan_id);
        $item->setRelation('plan', $plan);
        $line = $this->budgetLineForItem($budgetLines, $item);
        $orders = ProcurementPurchaseOrder::query()
            ->where(function ($query) use ($procurement, $includePurchaseOrderId): void {
                $query->where('procurement_id', $procurement->id);
                if ($includePurchaseOrderId !== null) {
                    $query->orWhere('id', $includePurchaseOrderId);
                }
            })
            ->lockForUpdate()
            ->get();
        $orderIds = $orders->pluck('id')->filter()->values();
        $payments = ProcurementDisbursement::query()
            ->where(function ($query) use ($procurement, $orderIds): void {
                $query->where('procurement_id', $procurement->id);
                if ($orderIds->isNotEmpty()) {
                    $query->orWhereIn('purchase_order_id', $orderIds);
                }
            })
            ->lockForUpdate()
            ->get();

        $this->assertCoverage(
            $member,
            $budgetLines,
            $line,
            $item,
            $plan,
            $procurement,
            $orders,
            $payments,
            $enforceCurrentUsage,
        );

        return [
            'procurement' => $procurement,
            'item' => $item,
            'budgetLine' => $line,
            'purchaseOrders' => $orders,
            'disbursements' => $payments,
        ];
    }

    private function lockPlan(ConsortiumThinkTank $member, string $planId): ThinkTankProcurementPlan
    {
        return ThinkTankProcurementPlan::query()
            ->whereKey($planId)
            ->where('think_tank_member_id', $member->id)
            ->where('consortium_id', $member->consortium_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function budgetLineForItem(Collection $budgetLines, ThinkTankProcurementItem $item): ThinkTankBudgetLine
    {
        $matches = $budgetLines->filter(
            fn (ThinkTankBudgetLine $line): bool => (string) $line->procurement_item_id === (string) $item->id
        );

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages([
                'budget_line' => ['This procurement item must have exactly one tenant-owned internal budget line before execution.'],
            ]);
        }

        return $matches->first();
    }

    private function assertCoverage(
        ConsortiumThinkTank $member,
        Collection $budgetLines,
        ThinkTankBudgetLine $line,
        ThinkTankProcurementItem $item,
        ThinkTankProcurementPlan $plan,
        ?Procurement $procurement,
        Collection $orders,
        Collection $payments,
        bool $enforceCurrentUsage = true,
    ): void {
        if ((string) $line->think_tank_member_id !== (string) $member->id
            || (string) $line->consortium_id !== (string) $member->consortium_id) {
            throw ValidationException::withMessages([
                'budget_line' => ['The linked internal budget line is outside this Think Tank tenant.'],
            ]);
        }
        if ($line->status !== ThinkTankBudgetLine::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'budget_line' => ['The linked internal budget line must be active before procurement execution.'],
            ]);
        }
        if ($budgetLines->contains(fn (ThinkTankBudgetLine $candidate): bool => (string) $candidate->parent_id === (string) $line->id
            && $candidate->status !== ThinkTankBudgetLine::STATUS_CLOSED
        )) {
            throw ValidationException::withMessages([
                'budget_line' => ['A procurement item must be linked to a leaf budget line without active suballocations.'],
            ]);
        }

        $lineCurrency = $this->currency($line->currency);
        $itemCurrency = $this->currency($item->currency ?: $plan->currency);
        if ($lineCurrency !== 'USD' || $itemCurrency !== $lineCurrency) {
            throw ValidationException::withMessages([
                'budget_line' => ['The procurement item currency must exactly match its active USD internal budget line.'],
            ]);
        }
        if (trim((string) $line->fiscal_year) !== trim((string) $plan->fiscal_year)) {
            throw ValidationException::withMessages([
                'budget_line' => ['The procurement plan fiscal year must exactly match its internal budget line.'],
            ]);
        }

        $this->assertActiveAncestorChain($line, $budgetLines);

        if ($orders->contains(fn (ProcurementPurchaseOrder $order): bool => filled($order->currency)
            && $this->currency($order->currency) !== $lineCurrency)) {
            throw ValidationException::withMessages([
                'budget_line' => ['An existing purchase order uses a currency different from the linked internal budget line.'],
            ]);
        }
        if ($payments->contains(function (ProcurementDisbursement $payment) use ($lineCurrency, $orders): bool {
            $fallback = $orders->first(
                fn (ProcurementPurchaseOrder $order): bool => (string) $order->id === (string) $payment->purchase_order_id
            )?->currency;

            return $this->currency($payment->currency ?: $fallback) !== $lineCurrency;
        })) {
            throw ValidationException::withMessages([
                'budget_line' => ['An existing payment uses a currency different from the linked internal budget line.'],
            ]);
        }

        $committed = $orders
            ->filter(fn (ProcurementPurchaseOrder $order): bool => $this->purchaseOrderCommits($order->status))
            ->sum(fn (ProcurementPurchaseOrder $order): int => $this->cents($order->amount));
        $spent = $this->recognizedDisbursementTotal($payments);
        $required = max(
            $this->cents($item->estimated_amount),
            $this->cents($procurement?->estimated_budget),
            $enforceCurrentUsage ? $committed : 0,
            $enforceCurrentUsage ? $spent : 0,
        );

        if ($required > $this->cents($line->amount)) {
            throw ValidationException::withMessages([
                'budget_line' => ['The procurement plan, commitments, or recognized expenditure exceeds its internal budget line.'],
            ]);
        }
    }

    private function assertActiveAncestorChain(ThinkTankBudgetLine $line, Collection $budgetLines): void
    {
        $cursor = $line;
        $seen = [];
        while ($cursor->parent_id) {
            $parentId = (string) $cursor->parent_id;
            if (isset($seen[$parentId])) {
                throw ValidationException::withMessages([
                    'budget_line' => ['The linked budget hierarchy contains a cycle.'],
                ]);
            }
            $seen[$parentId] = true;
            $parent = $budgetLines->first(
                fn (ThinkTankBudgetLine $candidate): bool => (string) $candidate->id === $parentId
            );
            if (! $parent instanceof ThinkTankBudgetLine
                || $parent->status !== ThinkTankBudgetLine::STATUS_ACTIVE
                || (string) $parent->fiscal_year !== (string) $line->fiscal_year
                || $this->currency($parent->currency) !== $this->currency($line->currency)) {
                throw ValidationException::withMessages([
                    'budget_line' => ['Every parent in the linked budget hierarchy must exist, be active, and use the same fiscal year and currency.'],
                ]);
            }
            if (filled($parent->procurement_item_id)) {
                throw ValidationException::withMessages([
                    'budget_line' => ['A parent budget line with suballocations cannot also fund a procurement item directly.'],
                ]);
            }
            $cursor = $parent;
        }
    }

    private function assertPurchaseOrderProjection(
        ThinkTankBudgetLine $line,
        ThinkTankProcurementItem $item,
        Procurement $procurement,
        Collection $orders,
        Collection $payments,
        int $proposedCents,
        string $status,
        ?ProcurementPurchaseOrder $existing = null,
    ): void {
        if ($proposedCents > $this->cents($line->amount)) {
            throw ValidationException::withMessages([
                'amount' => ['The purchase-order amount exceeds the linked internal budget line.'],
            ]);
        }

        $otherCommitted = $orders
            ->reject(fn (ProcurementPurchaseOrder $order): bool => (string) $order->id === (string) $existing?->id)
            ->filter(fn (ProcurementPurchaseOrder $order): bool => $this->purchaseOrderCommits($order->status))
            ->sum(fn (ProcurementPurchaseOrder $order): int => $this->cents($order->amount));
        $committedAfter = $otherCommitted + ($this->purchaseOrderCommits($status) ? $proposedCents : 0);
        $spent = $this->recognizedDisbursementTotal($payments);
        $existingPaid = $existing
            ? $this->recognizedDisbursementTotal($payments->filter(
                fn (ProcurementDisbursement $payment): bool => (string) $payment->purchase_order_id === (string) $existing->id
            ))
            : 0;

        if ($existingPaid > $proposedCents) {
            throw ValidationException::withMessages([
                'amount' => ['The purchase-order amount cannot be reduced below its recognized payments.'],
            ]);
        }
        if ($existingPaid > 0 && ! $this->purchaseOrderCommits($status)) {
            throw ValidationException::withMessages([
                'status' => ['A purchase order with recognized payments cannot be moved to a non-committing status.'],
            ]);
        }

        $required = max(
            $this->cents($item->estimated_amount),
            $this->cents($procurement->estimated_budget),
            $committedAfter,
            $spent,
        );
        if ($required > $this->cents($line->amount)) {
            throw ValidationException::withMessages([
                'amount' => ['This purchase order would make commitments exceed the linked internal budget line.'],
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $paymentRows
     * @param  array<int, string>  $replacePaymentIds
     * @param  array<int, string>  $deletePaymentIds
     */
    private function assertPaymentProjection(
        ThinkTankBudgetLine $line,
        ProcurementPurchaseOrder $targetOrder,
        Collection $orders,
        Collection $payments,
        array $paymentRows,
        array $replacePaymentIds = [],
        array $deletePaymentIds = [],
    ): void {
        $replaced = collect($replacePaymentIds)
            ->merge($deletePaymentIds)
            ->map(fn ($id): string => (string) $id)
            ->unique();
        $basePayments = $payments->reject(
            fn (ProcurementDisbursement $payment): bool => $replaced->contains((string) $payment->id)
        );
        $submittedPaid = collect($paymentRows)
            ->filter(fn (array $row): bool => $this->disbursementCountsAsPaid(
                $row['status'] ?? null,
                $row['paid_at'] ?? null,
            ))
            ->sum(fn (array $row): int => $this->cents($row['amount'] ?? 0));
        $currentPaid = $this->recognizedDisbursementTotal($payments);
        $projectedPaid = $this->recognizedDisbursementTotal($basePayments) + $submittedPaid;

        if ($projectedPaid > $this->cents($line->amount)) {
            throw ValidationException::withMessages([
                'payments' => ['Recognized payments would exceed the linked internal budget line.'],
            ]);
        }

        $targetCurrent = $this->recognizedDisbursementTotal($payments->filter(
            fn (ProcurementDisbursement $payment): bool => (string) $payment->purchase_order_id === (string) $targetOrder->id
        ));
        $targetBase = $basePayments->filter(
            fn (ProcurementDisbursement $payment): bool => (string) $payment->purchase_order_id === (string) $targetOrder->id
        );
        $targetProjected = $this->recognizedDisbursementTotal($targetBase) + $submittedPaid;

        if ($targetProjected > $this->cents($targetOrder->amount)) {
            throw ValidationException::withMessages([
                'payments' => ['Recognized payments would exceed the locked purchase-order amount.'],
            ]);
        }
        if ($targetProjected > $targetCurrent && ! $this->purchaseOrderCommits($targetOrder->status)) {
            throw ValidationException::withMessages([
                'payments' => ['A recognized payment requires an issued or otherwise committing purchase order.'],
            ]);
        }

        $committed = $orders
            ->filter(fn (ProcurementPurchaseOrder $order): bool => $this->purchaseOrderCommits($order->status))
            ->sum(fn (ProcurementPurchaseOrder $order): int => $this->cents($order->amount));
        if ($projectedPaid > $currentPaid && $committed > $this->cents($line->amount)) {
            throw ValidationException::withMessages([
                'payments' => ['Further recognized payments are blocked until purchase-order commitments fit the linked internal budget line.'],
            ]);
        }
    }

    private function memberIdentityFor(Procurement $procurement): ConsortiumThinkTank
    {
        if (! filled($procurement->think_tank_member_id) || ! filled($procurement->consortium_id)) {
            throw ValidationException::withMessages([
                'procurement' => ['This Think Tank procurement is missing its authoritative tenant identity.'],
            ]);
        }

        return (new ConsortiumThinkTank)->forceFill([
            'id' => $procurement->think_tank_member_id,
            'consortium_id' => $procurement->consortium_id,
        ]);
    }

    private function assertExistingPurchaseOrderIdentity(
        ProcurementPurchaseOrder $order,
        Procurement $procurement,
        Collection $payments,
    ): void {
        if ((filled($order->procurement_id)
                && (string) $order->procurement_id !== (string) $procurement->id)
            || (filled($order->think_tank_member_id)
                && (string) $order->think_tank_member_id !== (string) $procurement->think_tank_member_id)
            || (filled($order->consortium_id)
                && (string) $order->consortium_id !== (string) $procurement->consortium_id)) {
            throw ValidationException::withMessages([
                'procurement_id' => ['An existing purchase order cannot be moved into a different Think Tank procurement or tenant.'],
            ]);
        }

        $hasPayments = $payments->contains(
            fn (ProcurementDisbursement $payment): bool => (string) $payment->purchase_order_id === (string) $order->id
        );
        if ($hasPayments && (! filled($order->procurement_id)
            || ! filled($order->think_tank_member_id)
            || ! filled($order->consortium_id))) {
            throw ValidationException::withMessages([
                'procurement_id' => ['A purchase order with financial history cannot be reclassified into a Think Tank procurement.'],
            ]);
        }
    }

    private function assertPurchaseOrderIsNotGovernedAccountingRecord(
        ProcurementPurchaseOrder $order,
    ): void {
        if (filled($order->think_tank_member_id)
            || filled($order->consortium_id)
            || ($order->procurement && $this->isThinkTankProcurement($order->procurement))) {
            throw ValidationException::withMessages([
                'purchase_order' => ['A tenant-governed Think Tank purchase order cannot be hard-deleted. Use its cancellation or reversal workflow.'],
            ]);
        }
        if ($this->currency($order->currency) === 'USD'
            && filled($order->sub_activity_id)
            && app(ThinkTankFundingSourceService::class)
                ->historicalAwardCandidatesQuery()
                ->whereKey($order->id)
                ->exists()) {
            throw ValidationException::withMessages([
                'purchase_order' => ['An authoritative historical Funding-to-Think-Tanks award cannot be hard-deleted. Complete the guarded backfill and use its correction workflow.'],
            ]);
        }
    }

    private function normalizePurchaseOrderTenant(
        ProcurementPurchaseOrder $order,
        Procurement $procurement,
    ): void {
        if ((filled($order->procurement_id)
                && (string) $order->procurement_id !== (string) $procurement->id)
            || (filled($order->think_tank_member_id)
                && (string) $order->think_tank_member_id !== (string) $procurement->think_tank_member_id)
            || (filled($order->consortium_id)
                && (string) $order->consortium_id !== (string) $procurement->consortium_id)) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['The purchase order tenant identity conflicts with its procurement.'],
            ]);
        }

        if (! filled($order->procurement_id)
            || ! filled($order->think_tank_member_id)
            || ! filled($order->consortium_id)) {
            $order->forceFill([
                'procurement_id' => $procurement->id,
                'think_tank_member_id' => $procurement->think_tank_member_id,
                'consortium_id' => $procurement->consortium_id,
            ])->save();
        }
    }

    private function isThinkTankProcurement(Procurement $procurement): bool
    {
        return $procurement->procurement_owner_type === 'think_tank'
            || filled($procurement->think_tank_member_id)
            || filled($procurement->think_tank_procurement_plan_id);
    }

    private function fiscalCalendarYear(string $fiscalYear): ?int
    {
        return preg_match('/^(20\d{2})(?:\/\d{2})?$/', trim($fiscalYear), $matches) === 1
            ? (int) $matches[1]
            : null;
    }

    private function assertCommitmentFiscalYear(
        ThinkTankBudgetLine $line,
        mixed $commitmentYear,
    ): void {
        if ($this->fiscalCalendarYear((string) $line->fiscal_year) !== (int) $commitmentYear) {
            throw ValidationException::withMessages([
                'budget_commitment_id' => ['The commitment year must match the linked Think Tank budget-line fiscal year.'],
            ]);
        }
    }

    private function purchaseOrderCommits(mixed $status): bool
    {
        return in_array(strtolower(trim((string) $status)), self::COMMITTED_PURCHASE_ORDER_STATUSES, true);
    }

    private function disbursementCountsAsPaid(mixed $status, mixed $paidAt): bool
    {
        // Capacity is deliberately conservative: a legacy future-dated paid
        // row already reserves budget even though as-of reports recognize it
        // only when its date arrives. New future payment dates are rejected at
        // the controller boundary.
        return filled($paidAt)
            && in_array(strtolower(trim((string) $status)), ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES, true);
    }

    private function disbursementPreventsHardDelete(ProcurementDisbursement $payment): bool
    {
        return $this->disbursementCountsAsPaid($payment->status, $payment->paid_at)
            || strtolower(trim((string) $payment->status)) === 'reversed';
    }

    private function recognizedDisbursementTotal(Collection $payments): int
    {
        return $payments
            ->filter(fn (ProcurementDisbursement $payment): bool => $this->disbursementCountsAsPaid(
                $payment->status,
                $payment->paid_at,
            ))
            ->sum(fn (ProcurementDisbursement $payment): int => $this->cents($payment->amount));
    }

    private function currency(mixed $currency): string
    {
        $normalized = strtoupper(trim((string) $currency));

        return preg_match('/^[A-Z]{3}$/', $normalized) === 1 ? $normalized : 'UNKNOWN';
    }

    private function cents(mixed $amount): int
    {
        return ExactMoney::cents($amount ?? '0.00');
    }
}
