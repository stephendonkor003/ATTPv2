<?php

namespace App\Jobs;

use App\Mail\AssistantSubmissionReviewMail;
use App\Models\AssistantSubmission;
use App\Services\AssistantSubmissionNotificationService;
use App\Services\AssistantSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class NotifyAssistantSubmissionReviewers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 60;

    public function __construct(public string $submissionId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('assistant-submission-review:'.$this->submissionId))
            ->releaseAfter(30)->expireAfter(75)];
    }

    public function handle(
        AssistantSubmissionService $submissions,
        AssistantSubmissionNotificationService $notifications
    ): void {
        $submission = AssistantSubmission::with('creator')->find($this->submissionId);
        if (! $submission) {
            return;
        }

        if ($submission->status !== 'pending') {
            if ($submission->notification_status !== 'sent') {
                $submission->forceFill(['notification_status' => 'cancelled'])->save();
            }

            return;
        }

        if (! $notifications->hasDeliveryTransport()) {
            $submission->forceFill(['notification_status' => 'mail_not_configured'])->save();

            return;
        }

        $recipients = $submissions->reviewers($submission)
            ->filter(fn ($user) => filter_var(trim((string) $user->email), FILTER_VALIDATE_EMAIL))
            ->unique(fn ($user) => strtolower(trim((string) $user->email)));

        if ($recipients->isEmpty()) {
            $submission->forceFill(['notification_status' => 'no_recipients'])->save();
            Log::warning('Assistant submission has no eligible review email recipient.', [
                'submission_id' => $this->submissionId,
            ]);

            return;
        }

        $submission->forceFill(['notification_status' => 'sending'])->save();
        $hadFailure = false;

        foreach ($recipients as $recipient) {
            $submission->refresh();
            if ($submission->status !== 'pending') {
                $submission->forceFill(['notification_status' => 'cancelled'])->save();

                return;
            }

            $email = strtolower(trim((string) $recipient->email));
            if (in_array($email, $submission->notification_recipients ?? [], true)) {
                continue;
            }

            try {
                Mail::to($email, $recipient->name)
                    ->send(new AssistantSubmissionReviewMail($submission, $recipient));

                DB::transaction(function () use ($email): void {
                    $locked = AssistantSubmission::query()->lockForUpdate()->findOrFail($this->submissionId);
                    $locked->forceFill([
                        'notification_recipients' => array_values(array_unique([
                            ...($locked->notification_recipients ?? []), $email,
                        ])),
                    ])->save();
                });
            } catch (Throwable $exception) {
                $hadFailure = true;
                Log::warning('Assistant submission review email failed for a recipient.', [
                    'submission_id' => $this->submissionId,
                    'recipient_id' => $recipient->getKey(),
                    'exception' => $exception::class,
                ]);
            }
        }

        $submission->refresh()->forceFill([
            'notification_status' => $hadFailure ? 'failed' : 'sent',
        ])->save();

        if ($hadFailure) {
            // Retrying skips every recipient whose successful delivery was saved.
            throw new RuntimeException('One or more assistant submission review alerts could not be delivered.');
        }
    }

    public function failed(?Throwable $exception): void
    {
        AssistantSubmission::query()->whereKey($this->submissionId)
            ->whereIn('notification_status', ['queued', 'sending', 'failed'])
            ->update(['notification_status' => 'failed']);

        Log::error('Assistant submission review email exhausted its delivery attempts.', [
            'submission_id' => $this->submissionId,
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}
