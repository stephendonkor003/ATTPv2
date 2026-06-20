<?php

namespace App\Mail;

use App\Models\ProcurementPurchaseOrder;
use App\Models\ProcurementPurchaseOrderItemEvidence;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorPurchaseOrderEvidenceResubmissionRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProcurementPurchaseOrder $purchaseOrder,
        public ProcurementPurchaseOrderItemEvidence $evidence,
        public User $vendor,
        public string $portalUrl
    ) {
    }

    public function build()
    {
        return $this->subject('Evidence Resubmission Requested: ' . ($this->purchaseOrder->reference_no ?? 'ATTP PO'))
            ->view('emails.vendor.purchase-order-evidence-resubmission-requested')
            ->with([
                'purchaseOrder' => $this->purchaseOrder,
                'evidence' => $this->evidence,
                'vendor' => $this->vendor,
                'portalUrl' => $this->portalUrl,
            ]);
    }
}
