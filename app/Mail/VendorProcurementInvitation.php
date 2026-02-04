<?php

namespace App\Mail;

use App\Models\Procurement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorProcurementInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public Procurement $procurement;
    public User $vendor;
    public ?string $message;
    public string $link;

    public function __construct(Procurement $procurement, User $vendor, ?string $message = null)
    {
        $this->procurement = $procurement;
        $this->vendor = $vendor;
        $this->message = $message;
        $this->link = route('public.procurement.show', $procurement->slug);
    }

    public function build()
    {
        return $this->subject('New Procurement Opportunity')
            ->view('emails.vendor.procurement-invite')
            ->with([
                'procurement' => $this->procurement,
                'vendor' => $this->vendor,
                'message' => $this->message,
                'link' => $this->link,
            ]);
    }
}
