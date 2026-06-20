<?php

namespace App\Jobs;

use App\Mail\VendorPurchaseOrderCreatedMail;
use App\Models\ProcurementPurchaseOrder;
use App\Notifications\VendorPurchaseOrderCreatedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyVendorOfPurchaseOrderCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $purchaseOrderId)
    {
    }

    public function handle(): void
    {
        $purchaseOrder = ProcurementPurchaseOrder::with([
            'vendor',
            'procurement',
            'purchaseRequest.items',
            'budgetCommitment.purchaseRequest.items',
        ])->find($this->purchaseOrderId);

        if (! $purchaseOrder) {
            Log::warning('Purchase order vendor notification skipped; PO not found.', [
                'purchase_order_id' => $this->purchaseOrderId,
            ]);

            return;
        }

        $vendor = $purchaseOrder->vendor;
        if (! $vendor || empty($vendor->email)) {
            Log::warning('Purchase order vendor notification skipped; vendor email missing.', [
                'purchase_order_id' => $purchaseOrder->id,
                'vendor_id' => $purchaseOrder->vendor_id,
            ]);

            return;
        }

        if ((bool) ($vendor->is_disabled ?? false) || (bool) ($vendor->is_blacklisted ?? false)) {
            Log::info('Purchase order vendor notification skipped; vendor account inactive.', [
                'purchase_order_id' => $purchaseOrder->id,
                'vendor_id' => $vendor->id,
            ]);

            return;
        }

        $portalUrl = route('vendor.purchase-orders.show', $purchaseOrder);

        try {
            $vendor->notify(new VendorPurchaseOrderCreatedNotification($purchaseOrder));
        } catch (Throwable $exception) {
            Log::warning('Purchase order database notification failed.', [
                'purchase_order_id' => $purchaseOrder->id,
                'vendor_id' => $vendor->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            Mail::to($vendor->email, $vendor->name)
                ->send(new VendorPurchaseOrderCreatedMail($purchaseOrder, $vendor, $portalUrl));
        } catch (Throwable $exception) {
            Log::warning('Purchase order vendor email failed.', [
                'purchase_order_id' => $purchaseOrder->id,
                'vendor_id' => $vendor->id,
                'vendor_email' => $vendor->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
