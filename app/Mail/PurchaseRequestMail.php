<?php

namespace App\Mail;

use App\Models\PurchaseRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PurchaseRequest $purchaseRequest,
        public string $recipientName
    ) {}

    public function build()
    {
        $purchaseRequest = $this->purchaseRequest->load([
            'programFunding.program',
            'governanceNode',
            'subActivity',
            'items.resourceCategory',
            'items.resource',
            'commitments',
            'creator',
        ]);

        $pdf = Pdf::loadView('finance.purchase-requests.pdf', [
            'purchaseRequest' => $purchaseRequest,
        ]);

        return $this->subject('Purchase Request: ' . $purchaseRequest->reference_no)
            ->view('emails.finance.purchase-request', [
                'purchaseRequest' => $purchaseRequest,
                'recipientName' => $this->recipientName,
            ])
            ->attachData(
                $pdf->output(),
                'purchase-request-' . $purchaseRequest->reference_no . '.pdf',
                ['mime' => 'application/pdf']
            );
    }
}

