<?php

namespace App\Mail;

use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorProcurementLifecycleMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $portalUrl;

    public function __construct(
        public Procurement $procurement,
        public User $vendor,
        public FormSubmission $submission,
        public string $event,
        public ?string $reason = null,
    ) {
        $this->portalUrl = route('vendor.submissions');
        $this->afterCommit();
    }

    public function build(): self
    {
        $subject = $this->event === 'recalled'
            ? 'Procurement opportunity recalled - '.$this->procurement->title
            : 'Procurement opportunity republished - '.$this->procurement->title;

        return $this->subject($subject)
            ->view('emails.vendor.procurement-lifecycle');
    }
}
