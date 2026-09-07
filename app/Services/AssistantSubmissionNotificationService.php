<?php

namespace App\Services;

use App\Jobs\NotifyAssistantSubmissionReviewers;
use App\Models\AssistantSubmission;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AssistantSubmissionNotificationService
{
    public function notify(AssistantSubmission $submission): void
    {
        if ($submission->status !== 'pending') {
            return;
        }

        if (! $this->hasDeliveryTransport()) {
            $submission->forceFill(['notification_status' => 'mail_not_configured'])->save();

            return;
        }

        $submission->forceFill(['notification_status' => 'queued'])->save();
        $submissionId = (string) $submission->getKey();

        // Never send inside the request or before the submission is committed.
        DB::afterCommit(function () use ($submissionId): void {
            try {
                Bus::dispatch((new NotifyAssistantSubmissionReviewers($submissionId))
                    ->onConnection('database')
                    ->onQueue('assistant-approvals'));
            } catch (Throwable $exception) {
                AssistantSubmission::query()->whereKey($submissionId)
                    ->where('notification_status', 'queued')
                    ->update(['notification_status' => 'failed']);

                Log::error('Assistant submission review email could not be queued.', [
                    'submission_id' => $submissionId,
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    public function hasDeliveryTransport(?string $mailer = null, array $visited = []): bool
    {
        $mailer ??= (string) (config('assistant_submissions.mailer') ?: config('mail.default'));
        if ($mailer === '' || in_array($mailer, $visited, true)) {
            return false;
        }

        $configuration = config("mail.mailers.{$mailer}", []);
        $transport = $configuration['transport'] ?? null;
        if (! $transport || in_array($transport, ['log', 'array'], true)) {
            return false;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $configuration['mailers'] ?? [];
            if ($children === []) {
                return false;
            }

            foreach ($children as $child) {
                // A log fallback must never be reported as actual delivery.
                if (! $this->hasDeliveryTransport((string) $child, [...$visited, $mailer])) {
                    return false;
                }
            }
        }

        return true;
    }
}
