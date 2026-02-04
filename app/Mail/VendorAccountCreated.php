<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorAccountCreated extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $vendor;
    public string $plainPassword;
    public string $portalUrl;

    public function __construct(User $vendor, string $plainPassword)
    {
        $this->vendor = $vendor;
        $this->plainPassword = $plainPassword;
        $this->portalUrl = route('login');
    }

    public function build()
    {
        return $this->subject('Your Vendor Portal Credentials')
            ->view('emails.vendor.account-created')
            ->with([
                'vendor' => $this->vendor,
                'plainPassword' => $this->plainPassword,
                'portalUrl' => $this->portalUrl,
            ]);
    }
}
