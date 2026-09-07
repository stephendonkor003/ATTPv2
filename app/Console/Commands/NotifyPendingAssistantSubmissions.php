<?php

namespace App\Console\Commands;

use App\Models\AssistantSubmission;
use App\Services\AssistantSubmissionNotificationService;
use Illuminate\Console\Command;

class NotifyPendingAssistantSubmissions extends Command
{
    protected $signature = 'assistant-submissions:notify-pending';
    protected $description = 'Queue undelivered assistant approval emails after configuring mail or resolving delivery failures';

    public function handle(AssistantSubmissionNotificationService $notifications): int
    {
        if (! $notifications->hasDeliveryTransport()) {
            $this->error('Email delivery is not configured. Set a delivery mailer before retrying.');
            return self::FAILURE;
        }
        $count = 0;
        AssistantSubmission::where('status', 'pending')->whereIn('notification_status', ['failed', 'no_recipients', 'mail_not_configured'])
            ->eachById(function ($submission) use ($notifications, &$count) {
                $notifications->notify($submission);
                $count++;
            });
        $this->info("Queued {$count} pending submission(s) on the assistant-approvals queue.");
        return self::SUCCESS;
    }
}
