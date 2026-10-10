<?php

namespace App\Services;

use App\Exceptions\ThinkTankApiException;
use App\Models\ConsortiumThinkTank;
use App\Models\ProcurementDisbursement;
use App\Models\User;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ThinkTankFundingReceiptService
{
    public function __construct(
        private readonly ThinkTankFinanceApiService $finance,
        private readonly ThinkTankApiAuditService $audit,
    ) {}

    /** @return array{transfer: ProcurementDisbursement, idempotent: bool} */
    public function confirm(
        Request $request,
        ConsortiumThinkTank $member,
        User $actor,
        string $disbursementId,
        string $lockToken,
        ?string $notes = null,
    ): array {
        return DB::transaction(function () use (
            $request,
            $member,
            $actor,
            $disbursementId,
            $lockToken,
            $notes,
        ): array {
            ConsortiumThinkTank::query()
                ->whereKey($member->id)
                ->where('consortium_id', $member->consortium_id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->firstOrFail();

            $identity = ProcurementDisbursement::query()
                ->select(['id', 'purchase_order_id'])
                ->whereKey($disbursementId)
                ->where('think_tank_member_id', $member->id)
                ->where('consortium_id', $member->consortium_id)
                ->firstOrFail();

            // Match the monetary writers' member -> purchase order -> payment
            // lock order. The unlocked identity lookup is fully revalidated
            // after both authoritative rows are locked.
            $purchaseOrder = $this->finance->incomingFundingPurchaseOrdersQuery($member)
                ->whereKey($identity->purchase_order_id)
                ->lockForUpdate()
                ->firstOrFail();
            $transfer = $this->finance->incomingTransfersQuery($member)
                ->whereKey($identity->id)
                ->where('purchase_order_id', $purchaseOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Revalidate the accounting date while the authoritative payment
            // row is locked. The incoming-transfer scope already excludes
            // null and future dates; this guard keeps that invariant explicit
            // if the shared scope changes later.
            if ($transfer->paid_at === null || $transfer->paid_at->isFuture()) {
                throw new ThinkTankApiException(
                    'TRANSFER_NOT_POSTED',
                    'Receipt can be confirmed only after the Secretariat transfer date has been reached.',
                    409,
                );
            }

            if ($transfer->recipient_confirmation_status === 'confirmed') {
                return [
                    'transfer' => $transfer->load(['purchaseOrder', 'recipientConfirmer:id,name']),
                    'idempotent' => true,
                ];
            }

            if (filled($transfer->recipient_confirmation_status)
                && $transfer->recipient_confirmation_status !== 'pending') {
                throw new ThinkTankApiException(
                    'INVALID_RECEIPT_STATE',
                    'This transfer is not in a state that permits receipt confirmation.',
                    409,
                );
            }

            $this->finance->assertLockToken($transfer, $lockToken);

            $transfer->update([
                'recipient_confirmation_status' => 'confirmed',
                'recipient_confirmed_by' => $actor->id,
                'recipient_confirmed_at' => now(),
                'recipient_confirmation_notes' => filled($notes) ? trim((string) $notes) : null,
            ]);

            // Recipient acknowledgement must not create, approve, or mark an
            // invoice paid and must not reclassify the purchase order. Those
            // are separate Secretariat-controlled accounting transitions.
            $this->audit->required(
                $request,
                'think_tank.finance.transfer.receipt_confirmed',
                'Think tank funding transfer receipt confirmed.',
                [
                    'think_tank_member_id' => (string) $member->id,
                    'purchase_order_id' => (string) $purchaseOrder->id,
                    'disbursement_id' => (string) $transfer->id,
                    'amount' => (string) $transfer->amount,
                    'currency' => (string) ($transfer->currency ?: $purchaseOrder->currency),
                ],
                $actor,
            );

            return [
                'transfer' => $transfer->refresh()->load(['purchaseOrder', 'recipientConfirmer:id,name']),
                'idempotent' => false,
            ];
        }, 3);
    }
}
