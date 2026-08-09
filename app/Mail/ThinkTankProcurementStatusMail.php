<?php

namespace App\Mail;

use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ThinkTankProcurementStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ThinkTankProcurementPlan $plan,
        public string $heading,
        public string $messageText,
        public string $actionUrl,
        public ?ThinkTankProcurementItem $item = null,
        public ?string $reason = null,
    ) {}

    public function build(): self
    {
        return $this->subject($this->heading.' - '.$this->plan->plan_code)
            ->view('emails.think-tank.procurement-status');
    }
}
