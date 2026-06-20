<?php

namespace App\Services;

use App\Jobs\NotifyAdminsOfVendorPurchaseRequest;
use App\Models\VendorPurchaseRequest;

class VendorPurchaseRequestAdminNotificationService
{
    public function notify(VendorPurchaseRequest $purchaseRequest): void
    {
        NotifyAdminsOfVendorPurchaseRequest::dispatch($purchaseRequest->id)
            ->onQueue('mail');
    }
}
