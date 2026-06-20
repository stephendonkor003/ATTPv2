<?php

namespace App\Services;

use App\Jobs\NotifyVendorOfPurchaseRequestRevision;
use App\Models\VendorPurchaseRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

class VendorPurchaseRequestRevisionNotificationService
{
    public function notify(VendorPurchaseRequest $purchaseRequest): void
    {
        try {
            NotifyVendorOfPurchaseRequestRevision::dispatch($purchaseRequest->id);
            return;
        } catch (Throwable $exception) {
            Log::warning('Vendor revision notification queue dispatch failed; falling back to immediate send.', [
                'purchase_request_id' => $purchaseRequest->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            NotifyVendorOfPurchaseRequestRevision::dispatchSync($purchaseRequest->id);
        } catch (Throwable $exception) {
            Log::warning('Vendor revision notification fallback failed.', [
                'purchase_request_id' => $purchaseRequest->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
