<?php

namespace App\Jobs;

use App\Services\ApiSync\ApiSyncDocumentSnapshotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class BuildApiSyncDocumentSnapshot implements ShouldBeEncrypted, ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const TIMEOUT_SECONDS = 360;

    public const MUTEX_SECONDS = 420;

    public int $tries = 3;

    public int $timeout = self::TIMEOUT_SECONDS;

    public int $uniqueFor = self::MUTEX_SECONDS;

    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff = [15, 60, 180];

    public function __construct(public readonly string $pairingId) {}

    public function uniqueId(): string
    {
        return 'api-sync-document-snapshot:'.$this->pairingId;
    }

    public function handle(ApiSyncDocumentSnapshotService $documents): void
    {
        $lock = Cache::lock('api-sync-document-snapshot-build:'.$this->pairingId, self::MUTEX_SECONDS);
        if (! $lock->get()) {
            $this->release(30);

            return;
        }
        $continue = false;
        try {
            $completed = $documents->build($this->pairingId);
            $continue = ! $completed && $documents->hasContinuation($this->pairingId);
        } finally {
            $lock->release();
        }
        if ($continue) {
            self::dispatch($this->pairingId)
                ->delay(now()->addSeconds(5))
                ->onConnection((string) config('api_sync_documents.queue.connection', 'api_sync_database'))
                ->onQueue((string) config('api_sync_documents.queue.name', 'api-sync'));
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(ApiSyncDocumentSnapshotService::class)->markFailed(
            $this->pairingId,
            'document_snapshot_build_failed',
        );
    }
}
