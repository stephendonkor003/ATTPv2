<?php

namespace App\Mail;

use App\Models\ProcurementDisbursement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProcurementDisbursementHandoffMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProcurementDisbursement $disbursement,
        public User $recipient,
        public string $reviewUrl
    ) {
    }

    public function build()
    {
        return $this->subject('Payment Ready for Procurement Processing: ' . ($this->disbursement->reference_no ?? 'ATTP Receipt'))
            ->view('emails.procurement.disbursement-handoff')
            ->with([
                'disbursement' => $this->disbursement,
                'recipient' => $this->recipient,
                'reviewUrl' => $this->reviewUrl,
            ]);
    }
}
