<?php

namespace App\Services;

use App\Exceptions\TransientThreepapScreeningException;
use App\Jobs\ScreenProcurementSubmission;
use App\Models\FormSubmission;
use App\Models\ProcurementSubmissionScreening;
use App\Models\User;
use Illuminate\Bus\UniqueLock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ProcurementSubmissionScreeningAutomation
{
    private const MAX_PROVIDER_ATTEMPTS = 5;

    public const QUEUED = 'queued';

    public const ALREADY_ACTIVE = 'already_active';

    public const UP_TO_DATE = 'up_to_date';

    public const NOT_CONFIGURED = 'not_configured';

    public const AUTOMATION_DISABLED = 'automation_disabled';

    public const INELIGIBLE = 'ineligible';

    public const NOT_FOUND = 'not_found';

    public function __construct(
        private readonly ProcurementSubmissionScreeningService $screeningService,
    ) {}

    public function queueSubmission(
        FormSubmission|string $submission,
        ?string $actorId = null,
        string $checkedVia = 'auto',
        bool $force = false,
    ): string {
        if ($checkedVia === 'auto' && ! $this->automaticEnabled()) {
            $this->stageUntilReady(
                $submission,
                $actorId,
                'Automatic 3PAP screening is disabled. This applicant will remain pending until it is enabled.',
                'automatic_disabled',
            );

            return self::AUTOMATION_DISABLED;
        }

        if (! $this->screeningService->isConfigured()) {
            if ($checkedVia === 'auto') {
                $this->stageUntilReady(
                    $submission,
                    $actorId,
                    '3PAP screening is not configured. This applicant will be recovered automatically after configuration is restored.',
                    'waiting_for_configuration',
                );
            }

            return self::NOT_CONFIGURED;
        }

        $submissionId = $submission instanceof FormSubmission
            ? (string) $submission->getKey()
            : $submission;

        return DB::transaction(function () use ($submissionId, $actorId, $checkedVia, $force): string {
            $submission = FormSubmission::query()
                ->with(['values', 'submitter'])
                ->lockForUpdate()
                ->find($submissionId);

            if (! $submission) {
                return self::NOT_FOUND;
            }

            if (! $this->eligible($submission)) {
                return self::INELIGIBLE;
            }

            $fingerprint = $this->screeningService->submissionFingerprint($submission);
            $screening = ProcurementSubmissionScreening::query()
                ->where('submission_id', $submissionId)
                ->first();

            if ($screening?->isActive()
                && is_string($screening->submission_fingerprint)
                && hash_equals($screening->submission_fingerprint, $fingerprint)) {
                return self::ALREADY_ACTIVE;
            }

            if (! $force
                && $screening?->completedSuccessfully()
                && is_string($screening->submission_fingerprint)
                && hash_equals($screening->submission_fingerprint, $fingerprint)) {
                return self::UP_TO_DATE;
            }

            $runToken = (string) Str::uuid();
            $queuedAt = now();
            $attributes = [
                'run_token' => $runToken,
                'submission_fingerprint' => $fingerprint,
                'provider' => '3pap',
                'checked_by' => $actorId,
                'checked_via' => $checkedVia,
                'request_status' => ProcurementSubmissionScreening::STATUS_QUEUED,
                'attempt_count' => 0,
                'retryable' => false,
                'queued_at' => $queuedAt,
                'processing_started_at' => null,
                'request_started_at' => null,
                'next_retry_at' => $queuedAt->copy()->addMinutes($this->staleAfterMinutes()),
                'entity_name' => null,
                'entity_country' => null,
                'risk_level' => null,
                'total_matches' => 0,
                'is_flagged' => false,
                'error_message' => null,
                'last_checked_at' => null,
                'response_payload' => [
                    'success' => false,
                    'automation' => [
                        'state' => ProcurementSubmissionScreening::STATUS_QUEUED,
                        'queued_at' => $queuedAt->toIso8601String(),
                    ],
                ],
                'review_decision' => null,
                'review_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ];

            if ($screening) {
                $screening->fill($attributes)->save();
            } else {
                ProcurementSubmissionScreening::query()->create([
                    'submission_id' => $submissionId,
                    ...$attributes,
                ]);
            }

            DB::afterCommit(function () use ($submissionId, $runToken): void {
                $this->dispatchJob($submissionId, $runToken);
            });

            return self::QUEUED;
        });
    }

    private function stageUntilReady(
        FormSubmission|string $submission,
        ?string $actorId,
        string $message,
        string $state,
    ): void {
        $submissionId = $submission instanceof FormSubmission
            ? (string) $submission->getKey()
            : $submission;

        DB::transaction(function () use ($submissionId, $actorId, $message, $state): void {
            $submission = FormSubmission::query()
                ->with(['values', 'submitter'])
                ->lockForUpdate()
                ->find($submissionId);

            if (! $submission || ! $this->eligible($submission)) {
                return;
            }

            $now = now();
            ProcurementSubmissionScreening::query()->updateOrCreate(
                ['submission_id' => $submissionId],
                [
                    'run_token' => (string) Str::uuid(),
                    'submission_fingerprint' => $this->screeningService->submissionFingerprint($submission),
                    'provider' => '3pap',
                    'checked_by' => $actorId,
                    'checked_via' => 'auto',
                    'request_status' => ProcurementSubmissionScreening::STATUS_WAITING,
                    'attempt_count' => 0,
                    'retryable' => true,
                    'queued_at' => $now,
                    'processing_started_at' => null,
                    'request_started_at' => null,
                    'next_retry_at' => $now,
                    'entity_name' => null,
                    'entity_country' => null,
                    'risk_level' => null,
                    'total_matches' => 0,
                    'is_flagged' => false,
                    'error_message' => $message,
                    'last_checked_at' => null,
                    'response_payload' => [
                        'success' => false,
                        'automation' => [
                            'state' => $state,
                            'staged_at' => $now->toIso8601String(),
                        ],
                    ],
                    'review_decision' => null,
                    'review_notes' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ],
            );
        });
    }

    /**
     * @return array{queued:int, active:int, current:int, skipped:int}
     */
    public function queueMany(
        iterable $submissions,
        ?string $actorId = null,
        string $checkedVia = 'bulk',
        bool $force = false,
    ): array {
        $summary = ['queued' => 0, 'active' => 0, 'current' => 0, 'skipped' => 0];

        foreach ($submissions as $submission) {
            $result = $this->queueSubmission($submission, $actorId, $checkedVia, $force);

            match ($result) {
                self::QUEUED => $summary['queued']++,
                self::ALREADY_ACTIVE => $summary['active']++,
                self::UP_TO_DATE => $summary['current']++,
                default => $summary['skipped']++,
            };
        }

        return $summary;
    }

    /**
     * Recover a missed queue publication or a worker that stopped mid-run.
     * Terminal provider/authentication errors are deliberately not requeued.
     *
     * @return array{queued:int, redispatched:int, skipped:int}
     */
    public function recoverPending(int $limit = 25): array
    {
        $summary = ['queued' => 0, 'redispatched' => 0, 'skipped' => 0];
        if (! $this->automaticEnabled() || ! $this->screeningService->isConfigured()) {
            return $summary;
        }

        $limit = max(1, min($limit, 500));
        $lookback = now()->subDays($this->recoveryLookbackDays());

        $missing = $this->eligibleQuery(FormSubmission::query())
            ->where('submitted_at', '>=', $lookback)
            ->whereDoesntHave('screening')
            ->oldest('submitted_at')
            ->limit($limit)
            ->get();

        foreach ($missing as $submission) {
            if ($this->queueSubmission($submission) === self::QUEUED) {
                $summary['queued']++;
            } else {
                $summary['skipped']++;
            }
        }

        $remaining = $limit - $missing->count();
        if ($remaining <= 0) {
            return $summary;
        }

        $now = now();
        $staleBefore = $now->copy()->subMinutes($this->staleAfterMinutes());
        $recoverable = ProcurementSubmissionScreening::query()
            ->whereHas('submission', fn (Builder $query) => $this->eligibleQuery($query))
            ->where(function (Builder $query) use ($now, $staleBefore): void {
                $query->where(function (Builder $queued) use ($now): void {
                    $queued->where(function (Builder $status): void {
                        $status->whereIn('request_status', [
                            ProcurementSubmissionScreening::STATUS_QUEUED,
                            ProcurementSubmissionScreening::STATUS_RETRYING,
                            ProcurementSubmissionScreening::STATUS_WAITING,
                        ])->orWhere(function (Builder $retryableError): void {
                            $retryableError
                                ->where('request_status', ProcurementSubmissionScreening::STATUS_ERROR)
                                ->where('retryable', true);
                        });
                    })->where(function (Builder $due) use ($now): void {
                        $due->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', $now);
                    });
                })->orWhere(function (Builder $processing) use ($staleBefore): void {
                    $processing->where('request_status', ProcurementSubmissionScreening::STATUS_PROCESSING)
                        ->where('processing_started_at', '<=', $staleBefore);
                });
            })
            ->oldest('updated_at')
            ->limit($remaining)
            ->get();

        foreach ($recoverable as $screening) {
            if (! is_string($screening->run_token) || $screening->run_token === '') {
                $result = $this->queueSubmission((string) $screening->submission_id, null, 'auto', true);
                $summary[$result === self::QUEUED ? 'queued' : 'skipped']++;

                continue;
            }

            if ($screening->request_status === ProcurementSubmissionScreening::STATUS_PROCESSING
                && $screening->request_started_at !== null) {
                $this->markOutcomeUnknown($screening, $screening->run_token);
                $summary['skipped']++;

                continue;
            }

            if (! $this->leaseRecoveryRun($screening, $now, $staleBefore)) {
                $summary['skipped']++;

                continue;
            }

            if ($this->dispatchJob((string) $screening->submission_id, $screening->run_token)) {
                $summary['redispatched']++;
            } else {
                $summary['skipped']++;
            }
        }

        return $summary;
    }

    public function process(string $submissionId, string $runToken): void
    {
        try {
            $submission = FormSubmission::query()
                ->with(['values', 'submitter', 'screening'])
                ->find($submissionId);

            if (! $submission || ! $this->eligible($submission)) {
                $this->cancelActiveRun($submissionId, $runToken);

                return;
            }

            $screening = $submission->screening;
            if (! $screening || ! hash_equals((string) $screening->run_token, $runToken)) {
                return;
            }

            $fingerprint = $this->screeningService->submissionFingerprint($submission);
            if (! hash_equals((string) $screening->submission_fingerprint, $fingerprint)) {
                $this->queueSubmission($submission);

                return;
            }

            if ($screening->completedSuccessfully()) {
                return;
            }

            if ($screening->attempt_count >= self::MAX_PROVIDER_ATTEMPTS) {
                $this->markExhausted($submissionId, $runToken);

                return;
            }

            $claimed = ProcurementSubmissionScreening::query()
                ->whereKey($screening->getKey())
                ->where('run_token', $runToken)
                ->where('attempt_count', '<', self::MAX_PROVIDER_ATTEMPTS)
                ->where(function (Builder $status): void {
                    $status->whereIn('request_status', [
                        ProcurementSubmissionScreening::STATUS_QUEUED,
                        ProcurementSubmissionScreening::STATUS_WAITING,
                    ])->orWhere(function (Builder $dueRetry): void {
                        $dueRetry
                            ->where(function (Builder $retryable): void {
                                $retryable
                                    ->where('request_status', ProcurementSubmissionScreening::STATUS_RETRYING)
                                    ->orWhere(function (Builder $retryableError): void {
                                        $retryableError
                                            ->where('request_status', ProcurementSubmissionScreening::STATUS_ERROR)
                                            ->where('retryable', true);
                                    });
                            })
                            ->where(function (Builder $due): void {
                                $due->whereNull('next_retry_at')
                                    ->orWhere('next_retry_at', '<=', now());
                            });
                    });
                })
                ->update([
                    'request_status' => ProcurementSubmissionScreening::STATUS_PROCESSING,
                    'retryable' => false,
                    'processing_started_at' => now(),
                    'request_started_at' => null,
                    'next_retry_at' => null,
                    'error_message' => null,
                ]);

            if ($claimed !== 1) {
                return;
            }

            $screening = ProcurementSubmissionScreening::query()
                ->whereKey($screening->getKey())
                ->where('run_token', $runToken)
                ->first();

            if (! $screening) {
                return;
            }

            $actor = filled($screening->checked_by)
                ? User::query()->find($screening->checked_by)
                : null;
            $result = $this->screeningService->screenSubmission(
                $submission,
                $actor,
                $screening->checked_via ?: 'auto',
                $runToken,
            );

            if (! $result) {
                return;
            }

            $retryable = $this->isRetryableFailure($result);
            if ($result->request_status === ProcurementSubmissionScreening::STATUS_ERROR && $retryable) {
                $providerAttempt = max(1, (int) $result->attempt_count);
                if ($providerAttempt >= self::MAX_PROVIDER_ATTEMPTS) {
                    $this->markExhausted($submissionId, $runToken);

                    return;
                }

                $this->markRetrying($result, $runToken, $providerAttempt);

                throw new TransientThreepapScreeningException;
            }

            ProcurementSubmissionScreening::query()
                ->whereKey($result->getKey())
                ->where('run_token', $runToken)
                ->update([
                    'retryable' => false,
                    'processing_started_at' => null,
                    'request_started_at' => null,
                    'next_retry_at' => null,
                ]);
        } catch (TransientThreepapScreeningException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->markUnexpectedFailure($submissionId, $runToken);

            throw $exception;
        }
    }

    public function markExhausted(
        string $submissionId,
        string $runToken,
        bool $timedOut = false,
    ): void {
        $screening = ProcurementSubmissionScreening::query()
            ->where('submission_id', $submissionId)
            ->where('run_token', $runToken)
            ->first();

        if (! $screening || $screening->completedSuccessfully()) {
            return;
        }

        $payload = (array) $screening->response_payload;
        $outcomeUnknown = $timedOut && $screening->request_started_at !== null;
        data_set($payload, 'automation.state', 'exhausted');
        data_set($payload, 'automation.exhausted_at', now()->toIso8601String());
        data_set($payload, 'automation.outcome_unknown', $outcomeUnknown);

        ProcurementSubmissionScreening::query()
            ->whereKey($screening->getKey())
            ->where('run_token', $runToken)
            ->update([
                'request_status' => ProcurementSubmissionScreening::STATUS_ERROR,
                'attempt_count' => (int) $screening->attempt_count,
                'retryable' => false,
                'processing_started_at' => null,
                'request_started_at' => null,
                'next_retry_at' => null,
                'error_message' => $outcomeUnknown
                    ? 'The 3PAP request timed out with an unknown outcome. Verify provider usage before manually re-running it.'
                    : ($screening->error_message
                        ?: 'Automatic 3PAP screening could not be completed within its retry limit.'),
                'last_checked_at' => $screening->last_checked_at ?: now(),
                'response_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
    }

    private function dispatchJob(string $submissionId, string $runToken): bool
    {
        $job = (new ScreenProcurementSubmission($submissionId, $runToken))
            ->onConnection((string) config(
                'services.threepap_checker.automatic.queue_connection',
                'database'
            ))
            ->onQueue((string) config('services.threepap_checker.automatic.queue', 'threepap'));
        $uniqueLock = null;
        $lockAcquired = false;

        try {
            $uniqueLock = new UniqueLock($job->uniqueVia());
            $lockAcquired = $uniqueLock->acquire($job);
            if (! $lockAcquired) {
                // An existing delivery for this exact run still owns the lock.
                return true;
            }

            // The lock is acquired explicitly so a suppressed duplicate and a
            // queue-push failure are observable. Laravel releases this same
            // ShouldBeUnique lock when the queued job finishes.
            Bus::dispatch($job);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            if ($lockAcquired && $uniqueLock instanceof UniqueLock) {
                try {
                    $uniqueLock->release($job);
                } catch (Throwable $releaseException) {
                    report($releaseException);
                }
            }

            try {
                ProcurementSubmissionScreening::query()
                    ->where('submission_id', $submissionId)
                    ->where('run_token', $runToken)
                    ->whereIn('request_status', [
                        ProcurementSubmissionScreening::STATUS_QUEUED,
                        ProcurementSubmissionScreening::STATUS_RETRYING,
                    ])
                    ->update(['next_retry_at' => now()->addMinute()]);
            } catch (Throwable $recoveryException) {
                report($recoveryException);
            }

            return false;
        }
    }

    private function markRetrying(
        ProcurementSubmissionScreening $screening,
        string $runToken,
        int $attempt,
    ): void {
        $payload = (array) $screening->response_payload;
        data_set($payload, 'automation.state', ProcurementSubmissionScreening::STATUS_RETRYING);
        data_set($payload, 'automation.attempt', max(1, $attempt));

        ProcurementSubmissionScreening::query()
            ->whereKey($screening->getKey())
            ->where('run_token', $runToken)
            ->update([
                'request_status' => ProcurementSubmissionScreening::STATUS_RETRYING,
                'attempt_count' => max(1, $attempt),
                'retryable' => true,
                'processing_started_at' => null,
                'request_started_at' => null,
                'next_retry_at' => now()->addSeconds(ScreenProcurementSubmission::backoffForAttempt($attempt)),
                'response_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
    }

    private function markUnexpectedFailure(string $submissionId, string $runToken): void
    {
        $screening = ProcurementSubmissionScreening::query()
            ->where('submission_id', $submissionId)
            ->where('run_token', $runToken)
            ->first();

        if (! $screening || $screening->completedSuccessfully()) {
            return;
        }

        if ($screening->request_started_at !== null) {
            $this->markOutcomeUnknown($screening, $runToken);

            return;
        }

        $providerAttempts = (int) $screening->attempt_count;
        $backoffAttempt = max(1, $providerAttempts);

        ProcurementSubmissionScreening::query()
            ->whereKey($screening->getKey())
            ->where('run_token', $runToken)
            ->whereIn('request_status', [
                ProcurementSubmissionScreening::STATUS_QUEUED,
                ProcurementSubmissionScreening::STATUS_PROCESSING,
                ProcurementSubmissionScreening::STATUS_RETRYING,
            ])
            ->update([
                'request_status' => ProcurementSubmissionScreening::STATUS_RETRYING,
                'attempt_count' => $providerAttempts,
                'retryable' => true,
                'processing_started_at' => null,
                'request_started_at' => null,
                'next_retry_at' => now()->addSeconds(ScreenProcurementSubmission::backoffForAttempt($backoffAttempt)),
                'error_message' => 'Automatic 3PAP screening encountered a temporary internal error and will retry.',
            ]);
    }

    private function cancelActiveRun(string $submissionId, string $runToken): void
    {
        ProcurementSubmissionScreening::query()
            ->where('submission_id', $submissionId)
            ->where('run_token', $runToken)
            ->whereIn('request_status', ProcurementSubmissionScreening::ACTIVE_STATUSES)
            ->update([
                'request_status' => ProcurementSubmissionScreening::STATUS_ERROR,
                'retryable' => false,
                'processing_started_at' => null,
                'request_started_at' => null,
                'next_retry_at' => null,
                'error_message' => 'Automatic screening was cancelled because this submission is no longer eligible.',
                'last_checked_at' => now(),
            ]);
    }

    private function leaseRecoveryRun(
        ProcurementSubmissionScreening $screening,
        mixed $now,
        mixed $staleBefore,
    ): bool {
        $query = ProcurementSubmissionScreening::query()
            ->whereKey($screening->getKey())
            ->where('run_token', $screening->run_token);

        if ($screening->request_status === ProcurementSubmissionScreening::STATUS_PROCESSING) {
            $query->where('request_status', ProcurementSubmissionScreening::STATUS_PROCESSING)
                ->whereNull('request_started_at')
                ->where('processing_started_at', '<=', $staleBefore);
        } else {
            $query->where(function (Builder $status): void {
                $status->whereIn('request_status', [
                    ProcurementSubmissionScreening::STATUS_QUEUED,
                    ProcurementSubmissionScreening::STATUS_RETRYING,
                    ProcurementSubmissionScreening::STATUS_WAITING,
                ])->orWhere(function (Builder $retryableError): void {
                    $retryableError
                        ->where('request_status', ProcurementSubmissionScreening::STATUS_ERROR)
                        ->where('retryable', true);
                });
            })->where(function (Builder $due) use ($now): void {
                $due->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', $now);
            });
        }

        return $query->update([
            // A recovery delivery must be immediately claimable. RETRYING is
            // reserved for a provider backoff that process() must respect.
            'request_status' => ProcurementSubmissionScreening::STATUS_QUEUED,
            'retryable' => true,
            'processing_started_at' => null,
            'request_started_at' => null,
            'next_retry_at' => $now->copy()->addMinutes($this->staleAfterMinutes()),
        ]) === 1;
    }

    private function markOutcomeUnknown(
        ProcurementSubmissionScreening $screening,
        string $runToken,
    ): void {
        $payload = (array) $screening->response_payload;
        data_set($payload, 'automation.state', 'outcome_unknown');
        data_set($payload, 'automation.stopped_at', now()->toIso8601String());

        ProcurementSubmissionScreening::query()
            ->whereKey($screening->getKey())
            ->where('run_token', $runToken)
            ->where('request_status', ProcurementSubmissionScreening::STATUS_PROCESSING)
            ->update([
                'request_status' => ProcurementSubmissionScreening::STATUS_ERROR,
                'retryable' => false,
                'processing_started_at' => null,
                'request_started_at' => null,
                'next_retry_at' => null,
                'error_message' => 'The worker stopped after contacting 3PAP, so the request outcome is unknown. Verify provider usage before manually re-running it.',
                'last_checked_at' => now(),
                'response_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            ]);
    }

    private function isRetryableFailure(ProcurementSubmissionScreening $screening): bool
    {
        if ($screening->request_status !== ProcurementSubmissionScreening::STATUS_ERROR) {
            return false;
        }

        $payload = (array) $screening->response_payload;
        $code = strtoupper(trim((string) data_get($payload, 'raw.code')));
        $curlCode = data_get($payload, 'raw.curl_code');

        // Only transport errors known to happen before an HTTP response are
        // safe to repeat. HTTP failures can be returned after the provider has
        // consumed a sanctions credit and are therefore terminal for this run.
        return $code === 'CONNECTION_FAILED'
            && data_get($payload, 'raw.retryable') === true
            && is_numeric($curlCode)
            && in_array((int) $curlCode, [5, 6, 7, 35, 60], true);
    }

    private function eligible(FormSubmission $submission): bool
    {
        return filled($submission->procurement_id)
            && $submission->submitted_at !== null
            && ! in_array($submission->status, [
                'draft',
                FormSubmission::STATUS_REVISION_REQUESTED,
                FormSubmission::STATUS_WITHDRAWN,
            ], true);
    }

    private function eligibleQuery(Builder $query): Builder
    {
        return $query
            ->whereNotNull('procurement_id')
            ->whereNotNull('submitted_at')
            ->where(function (Builder $status): void {
                $status->whereNull('status')->orWhereNotIn('status', [
                    'draft',
                    FormSubmission::STATUS_REVISION_REQUESTED,
                    FormSubmission::STATUS_WITHDRAWN,
                ]);
            });
    }

    private function automaticEnabled(): bool
    {
        return (bool) config('services.threepap_checker.automatic.enabled', true);
    }

    private function recoveryLookbackDays(): int
    {
        return max(1, (int) config(
            'services.threepap_checker.automatic.recovery_lookback_days',
            7
        ));
    }

    private function staleAfterMinutes(): int
    {
        return max(2, (int) config(
            'services.threepap_checker.automatic.stale_after_minutes',
            10
        ));
    }
}
