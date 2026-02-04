<?php

namespace App\Mail;

use App\Models\Procurement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorContractTerminated extends Mailable
{
    use Queueable, SerializesModels;

    public Procurement $procurement;
    public User $vendor;
    public string $reason;
    public string $portalUrl;

    public function __construct(Procurement $procurement, User $vendor, string $reason)
    {
        $this->procurement = $procurement;
        $this->vendor = $vendor;
        $this->reason = $reason;
        $this->portalUrl = route('login');
    }

    public function build()
    {
        return $this->subject('Contract Termination - ' . $this->procurement->title)
            ->view('emails.vendor.contract-terminated');
    }
}
