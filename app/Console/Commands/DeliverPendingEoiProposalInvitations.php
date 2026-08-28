<?php

namespace App\Console\Commands;

use App\Services\EoiReportCommunicationService;
use Illuminate\Console\Command;

class DeliverPendingEoiProposalInvitations extends Command
{
    protected $signature = 'eoi:communications:deliver {--limit=25 : Maximum recipients to process}';

    protected $description = 'Deliver pending qualified-applicant proposal invitations';

    public function handle(EoiReportCommunicationService $communications): int
    {
        $limit = max(1, min((int) $this->option('limit'), 100));
        $recipientIds = $communications->recoverableProposalInvitationRecipientIds($limit);
        $sent = 0;

        foreach ($recipientIds as $recipientId) {
            if ($communications->deliverProposalInvitationRecipient((string) $recipientId)) {
                $sent++;
            }
        }

        $this->info("Processed {$recipientIds->count()} proposal invitation(s); {$sent} sent.");

        return self::SUCCESS;
    }
}
