<?php

namespace App\Mail;

use App\Models\ConsortiumThinkTank;
use App\Models\ThinkTankResearchOutput;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ThinkTankResearchQascSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ThinkTankResearchOutput $output,
        public ConsortiumThinkTank $member,
        private string $pdfContent,
        public string $previewUrl,
        private string $filename
    ) {
    }

    public function build(): self
    {
        return $this->subject('Annex B QASC submitted - ' . $this->output->title)
            ->view('emails.think-tank.research-qasc-submitted')
            ->attachData($this->pdfContent, $this->filename, ['mime' => 'application/pdf']);
    }
}
