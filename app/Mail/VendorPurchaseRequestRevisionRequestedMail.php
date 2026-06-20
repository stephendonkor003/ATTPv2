<?php

namespace App\Mail;

use App\Models\VendorPurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorPurchaseRequestRevisionRequestedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public VendorPurchaseRequest $purchaseRequest,
        public string $editUrl
    ) {
        $this->onQueue('mail');
    }

    public function build()
    {
        return $this->subject('Action Required: Update Purchase Request ' . $this->purchaseRequest->reference_no)
            ->view('emails.vendor.purchase-request-revision-requested')
            ->with([
                'purchaseRequest' => $this->purchaseRequest,
                'editUrl' => $this->editUrl,
            ]);
    }
}
