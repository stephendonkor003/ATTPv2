<?php

namespace App\Notifications;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VendorProcurementAwardedNotification extends Notification
{
    use Queueable;

    public Procurement $procurement;

    public function __construct(Procurement $procurement)
    {
        $this->procurement = $procurement;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'contract_awarded',
            'procurement_id' => $this->procurement->id,
            'procurement_title' => $this->procurement->title,
            'procurement_reference' => $this->procurement->reference_no,
        ];
    }
}
