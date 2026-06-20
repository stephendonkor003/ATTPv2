<?php

namespace App\Notifications;

use App\Models\ProcurementPurchaseOrder;
use App\Models\ProcurementPurchaseOrderItemEvidence;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VendorPurchaseOrderEvidenceResubmissionRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ProcurementPurchaseOrder $purchaseOrder,
        public ProcurementPurchaseOrderItemEvidence $evidence
    ) {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'purchase_order_evidence_resubmission_requested',
            'purchase_order_id' => $this->purchaseOrder->id,
            'purchase_order_reference' => $this->purchaseOrder->reference_no,
            'evidence_id' => $this->evidence->id,
            'purchase_request_item_id' => $this->evidence->purchase_request_item_id,
            'note' => $this->evidence->vendor_resubmission_note,
            'requested_at' => optional($this->evidence->vendor_resubmission_requested_at)->toDateTimeString(),
        ];
    }
}
