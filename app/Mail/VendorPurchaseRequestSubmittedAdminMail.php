<?php

namespace App\Mail;

use App\Models\User;
use App\Models\VendorPurchaseRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorPurchaseRequestSubmittedAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VendorPurchaseRequest $purchaseRequest,
        public User $admin,
        public string $reviewUrl
    ) {
    }

    public function build()
    {
        return $this->subject('New Vendor Purchase Request: ' . $this->purchaseRequest->reference_no)
            ->view('emails.vendor.purchase-request-submitted-admin')
            ->with([
                'purchaseRequest' => $this->purchaseRequest,
                'admin' => $this->admin,
                'reviewUrl' => $this->reviewUrl,
            ]);
    }
}
