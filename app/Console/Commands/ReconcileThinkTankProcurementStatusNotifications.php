<?php

namespace App\Console\Commands;

use App\Jobs\SendThinkTankProcurementStatusNotification;
use App\Models\ThinkTankProcurementStatusNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class ReconcileThinkTankProcurementStatusNotifications extends Command
{
    protected $signature = 'think-tank:procurement-notifications:reconcile
        {--limit=25 : Maximum delivery rows to inspect}
        {--include-ambiguous : Explicitly retry transport failures that may already have been accepted}
        {--dry-run : Report eligible rows without dispatching them}';

    protected $description = 'Recover procurement status emails left pending, stale or safely retryable.';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $includeAmbiguous = (bool) $this->option('include-ambiguous');

        $candidates = ThinkTankProcurementStatusNotification::query()
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->where('status', ThinkTankProcurementStatusNotification::STATUS_PENDING)
                        ->where('updated_at', '<=', now()->subMinute());
                })->orWhere(function ($processing): void {
                    $processing->where('status', ThinkTankProcurementStatusNotification::STATUS_PROCESSING)
                        ->where('updated_at', '<=', now()->subMinutes(15));
                })->orWhere(function ($sending): void {
                    $sending->where('status', ThinkTankProcurementStatusNotification::STATUS_SENDING)
                        ->where('updated_at', '<=', now()->subMinutes(15));
                })->orWhere(function ($failed): void {
                    $failed->where('status', ThinkTankProcurementStatusNotification::STATUS_FAILED)
                        ->where('attempts', '<', 3)
                        ->where('failed_at', '<=', now()->subMinutes(5));
                });
            })
            ->oldest('updated_at')
            ->limit($limit * 3)
            ->get()
            ->filter(fn (ThinkTankProcurementStatusNotification $notification): bool =>
                $this->isRetryable($notification, $includeAmbiguous))
            ->take($limit)
            ->values();

        if ((bool) $this->option('dry-run')) {
            $this->info("{$candidates->count()} procurement status notification(s) are eligible for reconciliation.");

            return self::SUCCESS;
        }

        $dispatched = $this->dispatch($candidates);
        $this->info("Dispatched {$dispatched} procurement status notification reconciliation job(s).");

        return self::SUCCESS;
    }

    /** @param Collection<int, ThinkTankProcurementStatusNotification> $notifications */
    private function dispatch(Collection $notifications): int
    {
        $dispatched = 0;

        foreach ($notifications as $notification) {
            if ($notification->status !== ThinkTankProcurementStatusNotification::STATUS_PENDING) {
                $notification->forceFill(['status' => ThinkTankProcurementStatusNotification::STATUS_PENDING])->save();
            }

            try {
                SendThinkTankProcurementStatusNotification::dispatch((string) $notification->id)
                    ->onQueue('mail')
                    ->afterCommit();
                $dispatched++;
            } catch (Throwable $exception) {
                // With the sync driver the job executes here. Keep processing
                // other durable rows and store only safe identifiers.
                Log::warning('Procurement notification reconciliation dispatch failed.', [
                    'notification_id' => $notification->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        return $dispatched;
    }

    private function isRetryable(
        ThinkTankProcurementStatusNotification $notification,
        bool $includeAmbiguous,
    ): bool {
        if ($notification->status === ThinkTankProcurementStatusNotification::STATUS_SENDING) {
            return $includeAmbiguous;
        }

        if ($notification->status !== ThinkTankProcurementStatusNotification::STATUS_FAILED) {
            return true;
        }

        if (in_array($notification->failure_code, [
            'recipient_no_longer_eligible',
            'workflow_event_context_missing',
        ], true)) {
            return false;
        }

        $failureClass = trim((string) $notification->failure_code);
        $ambiguousTransportOutcome = $failureClass !== ''
            && (is_a($failureClass, TransportExceptionInterface::class, true)
                || str_contains($failureClass, 'TransportException'));

        return $includeAmbiguous || ! $ambiguousTransportOutcome;
    }
}
