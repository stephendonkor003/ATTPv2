<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const RESOURCE_MOBILISATION_SUB_ACTIVITY = '019fbc8a-c981-7373-b3d1-b3f34e39f559';

    private const RESOURCE_MOBILISATION_FROM_ACTIVITY = '019ea974-4c96-7322-884c-814f2a4416c6';

    private const RESOURCE_MOBILISATION_TO_ACTIVITY = '019ea974-4caa-7347-b164-64dc14e7fe37';

    /** @var array<string, int> */
    private const GRM_ITEM_CENTS = [
        '019ecfc6-c09f-724b-9a31-07c78517e6cd' => 610000,
        '019ecfc6-c0a1-7052-8f8a-eadfcf4fdf3a' => 610000,
        '019ecfc6-c0a2-7176-a04b-a123253c7db2' => 1220000,
        '019ecfc6-c0a3-739d-a32d-df0238657a7c' => 610000,
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->repairResourceMobilisationHierarchy();
            $this->repairRestoredGrmItemPrices();
            $this->repairPartiallyPaidPurchaseOrderStatus();
            $this->repairPaidInvoiceAmount();
            $this->repairImportedPurchaseRequestApproval();
            $this->repairInvoiceVendorLineage();
        });
    }

    /**
     * These corrections restore audited identity and lineage. Reversing them
     * would knowingly recreate corrupt financial state, so rollback is a no-op.
     */
    public function down(): void
    {
        // Intentionally irreversible.
    }

    private function repairResourceMobilisationHierarchy(): void
    {
        $subActivity = DB::table('myb_sub_activities')
            ->where('id', self::RESOURCE_MOBILISATION_SUB_ACTIVITY)
            ->first();

        if (! $subActivity) {
            throw new RuntimeException('The audited Resource Mobilisation Specialist sub-activity is missing.');
        }

        if ((string) $subActivity->activity_id === self::RESOURCE_MOBILISATION_TO_ACTIVITY) {
            return;
        }

        if ((string) $subActivity->activity_id !== self::RESOURCE_MOBILISATION_FROM_ACTIVITY
            || (string) $subActivity->name !== 'Hire Resource Mobilisation Specialist') {
            throw new RuntimeException('The Resource Mobilisation Specialist hierarchy fingerprint has changed.');
        }

        $activities = DB::table('myb_activities')
            ->whereIn('id', [self::RESOURCE_MOBILISATION_FROM_ACTIVITY, self::RESOURCE_MOBILISATION_TO_ACTIVITY])
            ->get()
            ->keyBy('id');

        if ($activities->count() !== 2
            || (string) $activities[self::RESOURCE_MOBILISATION_FROM_ACTIVITY]->project_id
                !== (string) $activities[self::RESOURCE_MOBILISATION_TO_ACTIVITY]->project_id) {
            throw new RuntimeException('The audited Resource Mobilisation activities no longer share one component.');
        }

        $allocations = DB::table('myb_sub_activity_allocations')
            ->where('sub_activity_id', self::RESOURCE_MOBILISATION_SUB_ACTIVITY)
            ->pluck('amount', 'year');

        if ($this->cents($allocations->get(2027)) !== 10000000
            || $this->cents($allocations->get(2028)) !== 10000000) {
            throw new RuntimeException('The Resource Mobilisation allocation fingerprint has changed.');
        }

        DB::table('myb_sub_activities')
            ->where('id', self::RESOURCE_MOBILISATION_SUB_ACTIVITY)
            ->update(['activity_id' => self::RESOURCE_MOBILISATION_TO_ACTIVITY]);
    }

    private function repairRestoredGrmItemPrices(): void
    {
        foreach (self::GRM_ITEM_CENTS as $id => $expectedCents) {
            $item = DB::table('myb_purchase_request_items')->where('id', $id)->first();

            if (! $item
                || $this->cents($item->amount) !== $expectedCents
                || $this->cents($item->quantity) !== 100) {
                throw new RuntimeException("The audited GRM purchase-request item {$id} fingerprint has changed.");
            }

            if ($this->cents($item->unit_price) === $expectedCents) {
                continue;
            }

            if ($this->cents($item->unit_price) !== 0) {
                throw new RuntimeException("The GRM purchase-request item {$id} has an unexpected unit price.");
            }

            DB::table('myb_purchase_request_items')
                ->where('id', $id)
                ->update(['unit_price' => $item->amount]);
        }
    }

    private function repairPartiallyPaidPurchaseOrderStatus(): void
    {
        $purchaseOrder = DB::table('procurement_purchase_orders')
            ->where('id', '019ec657-d4ca-72da-95ba-17764bf16593')
            ->first();

        if (! $purchaseOrder
            || (string) $purchaseOrder->reference_no !== 'PO-2026-KXTV2I'
            || $this->cents($purchaseOrder->amount) !== 187226680) {
            throw new RuntimeException('The audited partially paid purchase order fingerprint has changed.');
        }

        if ((string) $purchaseOrder->status === 'partial_paid') {
            return;
        }

        $paidCents = $this->cents(DB::table('procurement_disbursements')
            ->where('purchase_order_id', $purchaseOrder->id)
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', now())
            ->whereIn('status', ['completed', 'paid', 'fully_paid'])
            ->sum('amount'));

        if ((string) $purchaseOrder->status !== 'issued' || $paidCents !== 31534620) {
            throw new RuntimeException('The audited purchase-order payment status can no longer be repaired safely.');
        }

        DB::table('procurement_purchase_orders')
            ->where('id', $purchaseOrder->id)
            ->update(['status' => 'partial_paid']);
    }

    private function repairPaidInvoiceAmount(): void
    {
        $invoice = DB::table('procurement_invoices')
            ->where('id', '019ed551-0bbb-70d5-9444-9272ab635509')
            ->first();

        if (! $invoice
            || (string) $invoice->reference_no !== 'INV-2026-KQO9YH'
            || (string) $invoice->status !== 'paid') {
            throw new RuntimeException('The audited paid invoice fingerprint has changed.');
        }

        if ($this->cents($invoice->amount) === 2944257) {
            return;
        }

        $purchaseOrder = DB::table('procurement_purchase_orders')
            ->where('invoice_id', $invoice->id)
            ->first();
        $paidCents = $this->cents(DB::table('procurement_disbursements')
            ->where('purchase_order_id', $purchaseOrder?->id)
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', now())
            ->whereIn('status', ['completed', 'paid', 'fully_paid'])
            ->sum('amount'));

        if ($this->cents($invoice->amount) !== 2868686
            || ! $purchaseOrder
            || (string) $purchaseOrder->reference_no !== 'PO-2026-CHCGBQ'
            || $this->cents($purchaseOrder->amount) !== 2944257
            || $paidCents !== 2944257) {
            throw new RuntimeException('The audited invoice amount can no longer be repaired safely.');
        }

        DB::table('procurement_invoices')
            ->where('id', $invoice->id)
            ->update(['amount' => '29442.57']);
    }

    private function repairImportedPurchaseRequestApproval(): void
    {
        $purchaseRequest = DB::table('myb_purchase_requests')
            ->where('id', '01a10854-2c9e-70e2-893a-5155722a6c54')
            ->first();

        if (! $purchaseRequest
            || (string) $purchaseRequest->reference_no !== 'AWP-2025-001'
            || (string) $purchaseRequest->status !== 'approved'
            || $this->cents($purchaseRequest->total_amount) !== 6400000) {
            throw new RuntimeException('The imported approved purchase-request fingerprint has changed.');
        }

        $commitment = DB::table('myb_budget_commitments')
            ->where('id', '01a10854-2cb5-70f2-a873-9b90f545c26c')
            ->where('purchase_request_id', $purchaseRequest->id)
            ->first();

        if (! $commitment
            || (string) $commitment->status !== 'approved'
            || (string) $commitment->approved_by !== '019e478d-5dce-72eb-b620-8be20a381711'
            || (string) $commitment->approved_at !== '2026-10-04 19:11:36') {
            throw new RuntimeException('The imported purchase-request approval source has changed.');
        }

        if ((string) $purchaseRequest->approved_by === (string) $commitment->approved_by
            && (string) $purchaseRequest->approved_at === (string) $commitment->approved_at) {
            return;
        }

        if ($purchaseRequest->approved_by !== null || $purchaseRequest->approved_at !== null) {
            throw new RuntimeException('The imported purchase request has conflicting approval metadata.');
        }

        DB::table('myb_purchase_requests')
            ->where('id', $purchaseRequest->id)
            ->update([
                'approved_by' => $commitment->approved_by,
                'approved_at' => $commitment->approved_at,
            ]);
    }

    private function repairInvoiceVendorLineage(): void
    {
        $invoice = DB::table('procurement_invoices')
            ->where('id', '019ec67e-27e4-737c-9417-f5aa41f76da4')
            ->first();
        $canonicalVendorId = '019f7645-aa2c-731e-87b1-cb4ae82d9c58';

        if (! $invoice || (string) $invoice->reference_no !== 'INV-2026-8JQUSF') {
            throw new RuntimeException('The audited invoice-vendor fingerprint has changed.');
        }

        if ((string) $invoice->vendor_id === $canonicalVendorId) {
            return;
        }

        $purchaseOrder = DB::table('procurement_purchase_orders')
            ->where('invoice_id', $invoice->id)
            ->where('reference_no', 'PO-2026-IQ2HAY')
            ->first();
        $paymentCount = DB::table('procurement_disbursements')
            ->where('purchase_order_id', $purchaseOrder?->id)
            ->count();
        $canonicalPaymentCount = DB::table('procurement_disbursements')
            ->where('purchase_order_id', $purchaseOrder?->id)
            ->where('vendor_id', $canonicalVendorId)
            ->count();

        if ((string) $invoice->vendor_id !== '019ec59e-8a34-739b-a8ff-6fbc590a9ec4'
            || ! $purchaseOrder
            || (string) $purchaseOrder->vendor_id !== $canonicalVendorId
            || $paymentCount !== 12
            || $canonicalPaymentCount !== 12) {
            throw new RuntimeException('The audited invoice vendor can no longer be repaired safely.');
        }

        DB::table('procurement_invoices')
            ->where('id', $invoice->id)
            ->update(['vendor_id' => $canonicalVendorId]);
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
};
