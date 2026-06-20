<?php

namespace App\Mail;

use App\Models\ProcurementPurchaseOrder;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorPurchaseOrderCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProcurementPurchaseOrder $purchaseOrder,
        public User $vendor,
        public string $portalUrl
    ) {
    }

    public function build()
    {
        return $this->subject('Purchase Order Assigned: ' . ($this->purchaseOrder->reference_no ?? 'ATTP PO'))
            ->view('emails.vendor.purchase-order-created')
            ->with([
                'purchaseOrder' => $this->purchaseOrder,
                'vendor' => $this->vendor,
                'portalUrl' => $this->portalUrl,
            ]);
    }
}
