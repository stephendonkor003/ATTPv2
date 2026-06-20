<?php

namespace App\Services;

use App\Jobs\NotifyVendorOfPurchaseOrderCreated;
use App\Models\ProcurementPurchaseOrder;
use Illuminate\Support\Facades\Log;
use Throwable;

class VendorPurchaseOrderNotificationService
{
    public function notifyCreated(ProcurementPurchaseOrder $purchaseOrder): void
    {
        if (! $purchaseOrder->vendor_id) {
            return;
        }

        try {
            NotifyVendorOfPurchaseOrderCreated::dispatch($purchaseOrder->id);
            return;
        } catch (Throwable $exception) {
            Log::warning('Purchase order vendor notification queue dispatch failed; falling back to immediate send.', [
                'purchase_order_id' => $purchaseOrder->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            NotifyVendorOfPurchaseOrderCreated::dispatchSync($purchaseOrder->id);
        } catch (Throwable $exception) {
            Log::warning('Purchase order vendor notification fallback failed.', [
                'purchase_order_id' => $purchaseOrder->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
