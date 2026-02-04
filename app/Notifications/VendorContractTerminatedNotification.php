<?php

namespace App\Notifications;

use App\Models\Procurement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class VendorContractTerminatedNotification extends Notification
{
    use Queueable;

    public Procurement $procurement;
    public string $reason;

    public function __construct(Procurement $procurement, string $reason)
    {
        $this->procurement = $procurement;
        $this->reason = $reason;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'contract_terminated',
            'procurement_id' => $this->procurement->id,
            'procurement_title' => $this->procurement->title,
            'procurement_reference' => $this->procurement->reference_no,
            'reason' => $this->reason,
        ];
    }
}
