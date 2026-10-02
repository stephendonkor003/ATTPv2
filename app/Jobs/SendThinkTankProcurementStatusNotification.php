<?php

namespace App\Jobs;

use App\Mail\ThinkTankProcurementStatusMail;
use App\Models\ThinkTankProcurementStatusNotification;
use App\Services\ThinkTankProcurementStatusNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class SendThinkTankProcurementStatusNotification implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 3600;

    public int $timeout = 90;

    public bool $failOnTimeout = true;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly string $notificationId) {}

    public function uniqueId(): string
    {
        return $this->notificationId;
    }

    public function handle(ThinkTankProcurementStatusNotificationService $recipients): void
    {
        // Claim the durable delivery under a row lock. ShouldBeUnique prevents
        // ordinary duplicate dispatches; this database guard also protects
        // against a retry, reconciler and worker reaching the row together.
        $notification = DB::transaction(function (): ?ThinkTankProcurementStatusNotification {
            $candidate = ThinkTankProcurementStatusNotification::query()
                ->whereKey($this->notificationId)
                ->lockForUpdate()
                ->first();

            if (! $candidate
                || in_array($candidate->status, [
                    ThinkTankProcurementStatusNotification::STATUS_SENT,
                    ThinkTankProcurementStatusNotification::STATUS_PROCESSING,
                    ThinkTankProcurementStatusNotification::STATUS_SENDING,
                ], true)
                || (int) $candidate->attempts >= $this->tries) {
                return null;
            }

            $candidate->forceFill([
                'status' => ThinkTankProcurementStatusNotification::STATUS_PROCESSING,
                'attempts' => (int) $candidate->attempts + 1,
                'failed_at' => null,
                'failure_code' => null,
            ])->save();

            return $candidate;
        });

        if (! $notification) {
            return;
        }

        $notification->load([
            'recipient.role.permissions',
            'recipient.permissions',
            'event.plan.member:id,name,portal_user_id',
            'event.item:id,plan_id,item_code,title',
            'event.actor:id,name',
        ]);

        if (! $notification->event?->plan) {
            $notification->forceFill([
                'status' => ThinkTankProcurementStatusNotification::STATUS_FAILED,
                'attempts' => max(3, (int) $notification->attempts),
                'failed_at' => now(),
                'failure_code' => 'workflow_event_context_missing',
            ])->save();

            return;
        }

        if (! $recipients->recipientIsStillEligible($notification)) {
            $notification->forceFill([
                'status' => ThinkTankProcurementStatusNotification::STATUS_FAILED,
                'attempts' => max(3, (int) $notification->attempts),
                'failed_at' => now(),
                'failure_code' => 'recipient_no_longer_eligible',
            ])->save();

            Log::info('Procurement status email suppressed after recipient revalidation.', [
                'notification_id' => $notification->id,
                'event_id' => $notification->event_id,
            ]);

            return;
        }

        try {
            // Once this state is durable, an interrupted worker has an
            // ambiguous provider outcome. The automatic reconciler will not
            // resend it without an operator explicitly opting in.
            $notification->forceFill([
                'status' => ThinkTankProcurementStatusNotification::STATUS_SENDING,
            ])->save();

            Mail::to($notification->recipient_email, $notification->recipient_name ?: null)
                ->send(new ThinkTankProcurementStatusMail($notification));
        } catch (TransportExceptionInterface $exception) {
            // Mail transports are not generally idempotent. In particular, a
            // lost Graph response can mean the provider accepted the message.
            // Preserve a failed delivery for an operator decision rather than
            // automatically sending the same event to this recipient again.
            try {
                $this->markFailed($notification, $exception);
            } catch (Throwable $recordingException) {
                Log::error('Procurement email failure receipt could not be recorded.', [
                    'notification_id' => $notification->id,
                    'event_id' => $notification->event_id,
                    'transport_exception' => $exception::class,
                    'recording_exception' => $recordingException::class,
                ]);
            }

            return;
        } catch (Throwable $exception) {
            $this->markFailed($notification, $exception);

            throw $exception;
        }

        try {
            $notification->forceFill([
                'status' => ThinkTankProcurementStatusNotification::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'failure_code' => null,
            ])->save();
        } catch (Throwable $exception) {
            // The provider already accepted the message. Do not throw and let
            // the queue retry a non-idempotent send merely because recording
            // the receipt failed.
            Log::error('Procurement email sent but its delivery receipt could not be recorded.', [
                'notification_id' => $notification->id,
                'event_id' => $notification->event_id,
                'exception' => $exception::class,
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        ThinkTankProcurementStatusNotification::query()
            ->whereKey($this->notificationId)
            ->where('status', '<>', ThinkTankProcurementStatusNotification::STATUS_SENT)
            ->update([
                'status' => ThinkTankProcurementStatusNotification::STATUS_FAILED,
                'failed_at' => now(),
                'failure_code' => substr($exception ? $exception::class : 'queue_delivery_exhausted', 0, 160),
                'updated_at' => now(),
            ]);
    }

    private function markFailed(
        ThinkTankProcurementStatusNotification $notification,
        Throwable $exception,
    ): void {
        $notification->forceFill([
            'status' => ThinkTankProcurementStatusNotification::STATUS_FAILED,
            'failed_at' => now(),
            // Store only the exception class. Provider responses and credentials
            // must never be copied into a workflow delivery record.
            'failure_code' => substr($exception::class, 0, 160),
        ])->save();

        Log::warning('Queued procurement status email failed.', [
            'notification_id' => $notification->id,
            'event_id' => $notification->event_id,
            'exception' => $exception::class,
        ]);
    }
}
