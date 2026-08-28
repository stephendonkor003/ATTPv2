<?php

namespace App\Jobs;

use App\Services\EoiReportCommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendQualifiedProposalInvitation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public string $recipientId) {}

    public function handle(EoiReportCommunicationService $communications): void
    {
        $communications->deliverProposalInvitationRecipient($this->recipientId);
    }
}
