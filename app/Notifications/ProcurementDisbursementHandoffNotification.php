<?php

namespace App\Notifications;

use App\Models\ProcurementDisbursement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProcurementDisbursementHandoffNotification extends Notification
{
    use Queueable;

    public function __construct(public ProcurementDisbursement $disbursement)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'procurement_disbursement_handoff',
            'disbursement_id' => $this->disbursement->id,
            'receipt_reference' => $this->disbursement->reference_no,
            'purchase_order_id' => $this->disbursement->purchase_order_id,
            'purchase_order_reference' => $this->disbursement->purchaseOrder?->reference_no,
            'vendor_name' => $this->disbursement->vendor?->name,
            'amount' => $this->disbursement->amount,
            'currency' => $this->disbursement->resolved_currency,
            'paid_at' => optional($this->disbursement->paid_at)->toDateTimeString(),
            'url' => route('procurement.disbursements.show', $this->disbursement),
        ];
    }
}
