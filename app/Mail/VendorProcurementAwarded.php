<?php

namespace App\Mail;

use App\Models\Procurement;
use App\Models\ProcurementContractNegotiation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorProcurementAwarded extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Procurement $procurement;
    public ProcurementContractNegotiation $negotiation;
    public User $vendor;
    public string $portalUrl;

    public function __construct(
        Procurement $procurement,
        ProcurementContractNegotiation $negotiation,
        User $vendor
    ) {
        $this->procurement = $procurement;
        $this->negotiation = $negotiation;
        $this->vendor = $vendor;
        $this->portalUrl = route('login');
    }

    public function build()
    {
        return $this->subject('Procurement Awarded - ' . $this->procurement->title)
            ->view('emails.vendor.procurement-awarded');
    }
}
