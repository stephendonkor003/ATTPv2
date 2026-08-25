<?php

namespace App\Jobs;

use App\Exceptions\ApiSyncException;
use App\Services\ApiSync\ApiSyncSnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BuildApiSyncSnapshot implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TIMEOUT_SECONDS = 1_860;

    public const MUTEX_SECONDS = 2_000;

    public int $tries = 3;

    public int $timeout = self::TIMEOUT_SECONDS;

    public int $uniqueFor = 2_100;

    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public function __construct(public readonly string $pairingId) {}

    public function uniqueId(): string
    {
        return 'api-sync-snapshot:'.$this->pairingId;
    }

    public function handle(ApiSyncSnapshotService $snapshots): void
    {
        $lock = Cache::lock('api-sync-snapshot-build:'.$this->pairingId, self::MUTEX_SECONDS);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }

        try {
            $snapshots->build($this->pairingId);
        } catch (ApiSyncException $exception) {
            if (in_array($exception->errorCode, [
                'snapshot_capacity_exceeded',
                'snapshot_invalid_source_id',
                'snapshot_invalid_checksum',
                'snapshot_build_timeout',
                'snapshot_session_expired',
                'snapshot_session_window_unavailable',
            ], true)) {
                $snapshots->markFailed($this->pairingId, $exception->errorCode);

                return;
            }

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(ApiSyncSnapshotService::class)->markFailed(
            $this->pairingId,
            $exception instanceof ApiSyncException ? $exception->errorCode : 'snapshot_build_failed',
        );
    }
}
