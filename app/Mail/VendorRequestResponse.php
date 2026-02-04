<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorRequestResponse extends Mailable
{
    use Queueable, SerializesModels;

    public string $type;
    public $request;
    public string $subjectLine;

    public function __construct(string $type, $request)
    {
        $this->type = $type;
        $this->request = $request;
        $this->subjectLine = $type === 'message'
            ? 'Response to Your Clarification Request'
            : 'Response to Your Information Request';
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view('emails.vendor.request-response')
            ->with([
                'type' => $this->type,
                'request' => $this->request,
            ]);
    }
}
