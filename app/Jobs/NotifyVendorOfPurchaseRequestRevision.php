<?php

namespace App\Jobs;

use App\Mail\VendorPurchaseRequestRevisionRequestedMail;
use App\Models\VendorPurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyVendorOfPurchaseRequestRevision implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $purchaseRequestId)
    {
    }

    public function handle(): void
    {
        $purchaseRequest = VendorPurchaseRequest::with([
            'user',
            'subActivity.activity.project.program',
            'items',
            'documents',
        ])->find($this->purchaseRequestId);

        if (! $purchaseRequest) {
            Log::warning('Vendor revision notification skipped; purchase request not found.', [
                'purchase_request_id' => $this->purchaseRequestId,
            ]);

            return;
        }

        $vendor = $purchaseRequest->user;
        if (! $vendor || ! filter_var($vendor->email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Vendor revision notification skipped; vendor email is missing or invalid.', [
                'purchase_request_id' => $purchaseRequest->id,
                'vendor_id' => $vendor?->id,
                'vendor_email' => $vendor?->email,
            ]);

            return;
        }

        try {
            Mail::to($vendor->email, $vendor->name)
                ->send(new VendorPurchaseRequestRevisionRequestedMail(
                    $purchaseRequest,
                    route('vendor.purchase-requests.edit', $purchaseRequest)
                ));
        } catch (Throwable $exception) {
            Log::warning('Vendor revision notification email failed.', [
                'purchase_request_id' => $purchaseRequest->id,
                'vendor_id' => $vendor->id,
                'vendor_email' => $vendor->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
