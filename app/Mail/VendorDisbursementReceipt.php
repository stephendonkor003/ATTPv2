<?php

namespace App\Mail;

use App\Models\ProcurementDisbursement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorDisbursementReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public ProcurementDisbursement $disbursement;
    public string $portalUrl;
    private string $pdfContent;

    public function __construct(ProcurementDisbursement $disbursement, string $pdfContent)
    {
        $this->disbursement = $disbursement;
        $this->pdfContent = $pdfContent;
        $this->portalUrl = route('login');
    }

    public function build()
    {
        $filename = 'receipt-' . ($this->disbursement->reference_no ?? 'payment') . '.pdf';

        return $this->subject('Payment Receipt - ' . ($this->disbursement->reference_no ?? ''))
            ->view('emails.vendor.disbursement-receipt')
            ->attachData($this->pdfContent, $filename, ['mime' => 'application/pdf']);
    }
}
