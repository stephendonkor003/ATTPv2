<?php

namespace App\Mail;

use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorApplicationReceived extends Mailable
{
    use Queueable, SerializesModels;

    public Procurement $procurement;
    public FormSubmission $submission;
    public User $vendor;
    public ?string $temporaryPassword;
    public string $portalUrl;

    public function __construct(
        Procurement $procurement,
        FormSubmission $submission,
        User $vendor,
        ?string $temporaryPassword
    ) {
        $this->procurement = $procurement;
        $this->submission = $submission;
        $this->vendor = $vendor;
        $this->temporaryPassword = $temporaryPassword;
        $this->portalUrl = route('login');
    }

    public function build()
    {
        return $this->subject('Application Received - ' . $this->procurement->title)
            ->view('emails.vendor.application-received');
    }
}
