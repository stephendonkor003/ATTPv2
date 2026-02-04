<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VendorRequestCreatedNotification extends Notification
{
    use Queueable;

    public string $type;
    public $request;

    public function __construct(string $type, $request)
    {
        $this->type = $type;
        $this->request = $request;
    }

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $subject = $this->type === 'message'
            ? 'New Vendor Clarification Message'
            : 'New Vendor Information Request';

        $url = $this->type === 'message'
            ? route('vendors.requests.messages.show', $this->request->id)
            : route('vendors.requests.information.show', $this->request->id);

        $title = $this->type === 'message'
            ? ($this->request->subject ?? 'Vendor Message')
            : ($this->request->request_topic ?? 'Vendor Information Request');

        return (new MailMessage)
            ->subject($subject)
            ->line('A new vendor request has been submitted.')
            ->line('Vendor: ' . ($this->request->user->name ?? 'Vendor'))
            ->line('Subject: ' . $title)
            ->action('View Request', $url)
            ->line('Please review and respond as soon as possible.');
    }

    public function toArray($notifiable): array
    {
        return [
            'request_id' => $this->request->id,
            'vendor_id' => $this->request->user_id,
            'vendor_name' => $this->request->user->name ?? null,
            'type' => $this->type,
        ];
    }
}
