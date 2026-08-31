<?php

namespace App\Jobs;

use App\Services\ProcurementSubmissionScreeningAutomation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class ScreenProcurementSubmission implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Queue deliveries include harmless cache-lock releases. The automation
    // service separately enforces a maximum of five actual provider calls.
    public int $tries = 25;

    public int $timeout = 60;

    // Longer than the complete bounded retry window. Database state remains
    // the final guard if a worker outage outlives this cache lock.
    public int $uniqueFor = 21_600;

    public bool $failOnTimeout = true;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3_600];

    public function __construct(
        public readonly string $submissionId,
        public readonly string $runToken,
    ) {}

    public function uniqueId(): string
    {
        return 'threepap-screen:'.$this->submissionId.':'.$this->runToken;
    }

    public function uniqueVia(): CacheRepository
    {
        return Cache::store((string) config(
            'services.threepap_checker.automatic.cache_store',
            'database',
        ));
    }

    public function handle(ProcurementSubmissionScreeningAutomation $automation): void
    {
        $lock = $this->uniqueVia()->lock('threepap-screening:'.$this->submissionId, 75);
        if (! $lock->get()) {
            $this->release(20);

            return;
        }

        try {
            $automation->process($this->submissionId, $this->runToken);
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(ProcurementSubmissionScreeningAutomation::class)->markExhausted(
            $this->submissionId,
            $this->runToken,
            $exception instanceof TimeoutExceededException,
        );
    }

    public static function backoffForAttempt(int $attempt): int
    {
        $delays = [60, 300, 900, 3_600];

        return $delays[min(max(1, $attempt) - 1, count($delays) - 1)];
    }
}
