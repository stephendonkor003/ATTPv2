<?php

namespace App\Notifications;

use App\Models\ProcurementPurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VendorPurchaseOrderCreatedNotification extends Notification
{
    use Queueable;

    public function __construct(public ProcurementPurchaseOrder $purchaseOrder)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'purchase_order_created',
            'purchase_order_id' => $this->purchaseOrder->id,
            'purchase_order_reference' => $this->purchaseOrder->reference_no,
            'purchase_order_title' => $this->purchaseOrder->po_title,
            'amount' => $this->purchaseOrder->amount,
            'currency' => $this->purchaseOrder->resolved_currency,
            'status' => $this->purchaseOrder->status,
            'issued_at' => optional($this->purchaseOrder->issued_at)->toDateTimeString(),
        ];
    }
}
