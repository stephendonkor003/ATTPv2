<?php

namespace App\Jobs;

use App\Mail\VendorPurchaseOrderEvidenceResubmissionRequestedMail;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ProcurementPurchaseOrderItemEvidence;
use App\Notifications\VendorPurchaseOrderEvidenceResubmissionRequestedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyVendorOfPurchaseOrderEvidenceResubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $purchaseOrderId,
        public string $evidenceId
    ) {
    }

    public function handle(): void
    {
        $purchaseOrder = ProcurementPurchaseOrder::with(['vendor'])->find($this->purchaseOrderId);
        $evidence = ProcurementPurchaseOrderItemEvidence::with('purchaseRequestItem')->find($this->evidenceId);

        if (! $purchaseOrder || ! $evidence) {
            Log::warning('PO evidence resubmission notification skipped; record missing.', [
                'purchase_order_id' => $this->purchaseOrderId,
                'evidence_id' => $this->evidenceId,
            ]);

            return;
        }

        $vendor = $purchaseOrder->vendor;
        if (! $vendor || empty($vendor->email)) {
            Log::warning('PO evidence resubmission notification skipped; vendor email missing.', [
                'purchase_order_id' => $purchaseOrder->id,
                'evidence_id' => $evidence->id,
                'vendor_id' => $purchaseOrder->vendor_id,
            ]);

            return;
        }

        if ((bool) ($vendor->is_disabled ?? false) || (bool) ($vendor->is_blacklisted ?? false)) {
            return;
        }

        $portalUrl = route('vendor.purchase-orders.show', $purchaseOrder);

        try {
            $vendor->notify(new VendorPurchaseOrderEvidenceResubmissionRequestedNotification($purchaseOrder, $evidence));
        } catch (Throwable $exception) {
            Log::warning('PO evidence resubmission database notification failed.', [
                'purchase_order_id' => $purchaseOrder->id,
                'evidence_id' => $evidence->id,
                'vendor_id' => $vendor->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            Mail::to($vendor->email, $vendor->name)
                ->send(new VendorPurchaseOrderEvidenceResubmissionRequestedMail($purchaseOrder, $evidence, $vendor, $portalUrl));
        } catch (Throwable $exception) {
            Log::warning('PO evidence resubmission email failed.', [
                'purchase_order_id' => $purchaseOrder->id,
                'evidence_id' => $evidence->id,
                'vendor_id' => $vendor->id,
                'vendor_email' => $vendor->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
