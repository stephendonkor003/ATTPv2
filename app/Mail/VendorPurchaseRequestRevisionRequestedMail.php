<?php

namespace App\Mail;

use App\Models\VendorPurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorPurchaseRequestRevisionRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VendorPurchaseRequest $purchaseRequest,
        public string $editUrl
    ) {
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
