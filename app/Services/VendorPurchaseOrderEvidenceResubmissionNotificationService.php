<?php

namespace App\Services;

use App\Jobs\NotifyVendorOfPurchaseOrderEvidenceResubmission;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ProcurementPurchaseOrderItemEvidence;
use Illuminate\Support\Facades\Log;
use Throwable;

class VendorPurchaseOrderEvidenceResubmissionNotificationService
{
    public function notify(ProcurementPurchaseOrder $purchaseOrder, ProcurementPurchaseOrderItemEvidence $evidence): void
    {
        if (! $purchaseOrder->vendor_id) {
            return;
        }

        try {
            NotifyVendorOfPurchaseOrderEvidenceResubmission::dispatch($purchaseOrder->id, $evidence->id);
            return;
        } catch (Throwable $exception) {
            Log::warning('PO evidence resubmission queue dispatch failed; falling back to immediate send.', [
                'purchase_order_id' => $purchaseOrder->id,
                'evidence_id' => $evidence->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            NotifyVendorOfPurchaseOrderEvidenceResubmission::dispatchSync($purchaseOrder->id, $evidence->id);
        } catch (Throwable $exception) {
            Log::warning('PO evidence resubmission fallback failed.', [
                'purchase_order_id' => $purchaseOrder->id,
                'evidence_id' => $evidence->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
