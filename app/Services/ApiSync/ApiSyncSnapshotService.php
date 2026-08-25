<?php

namespace App\Services\ApiSync;

use App\Exceptions\ApiSyncException;
use App\Jobs\BuildApiSyncSnapshot;
use App\Models\ApiSyncPairing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use JsonException;

class ApiSyncSnapshotService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_BUILDING = 'building';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PURGE_PENDING = 'purge_pending';

    public const STATUS_PURGED = 'purged';

    public function __construct(
        private readonly ApiSyncDatasetService $source,
        private readonly ApiSyncCursor $cursor,
        private readonly ApiSyncAuditService $audit,
    ) {}

    public function initialize(ApiSyncPairing $pairing): void
    {
        if (! $pairing->exists || blank($pairing->snapshot_id)) {
            throw new \LogicException('A persisted pairing and snapshot ID are required before materialization.');
        }

        $createdAt = now();
        $rows = collect(ApiSyncDatasetService::DATASETS)
            ->values()
            ->map(fn (string $dataset, int $index): array => [
                'pairing_id' => $pairing->id,
                'snapshot_id' => $pairing->snapshot_id,
                'dataset' => $dataset,
                'sort_order' => $index + 1,
                'status' => self::STATUS_PENDING,
                'record_count' => 0,
                'payload_bytes' => 0,
                'created_at' => $createdAt,
            ])
            ->all();

        DB::table('api_sync_snapshot_datasets')->insert($rows);
        $pairing->forceFill([
            'snapshot_status' => self::STATUS_PENDING,
            'snapshot_started_at' => null,
            'snapshot_materialized_at' => null,
            'snapshot_failed_at' => null,
            'snapshot_purged_at' => null,
            'snapshot_failure_reason' => null,
            'snapshot_record_count' => 0,
            'snapshot_bytes' => 0,
        ])->save();
    }

    public function dispatch(ApiSyncPairing $pairing): void
    {
        BuildApiSyncSnapshot::dispatch((string) $pairing->id)
            ->onConnection((string) config('api_sync.snapshot.connection', 'api_sync_database'))
            ->onQueue((string) config('api_sync.snapshot.queue', 'api-sync'));
    }

    /**
     * @return array<string, mixed>
     */
    public function claimDescriptor(ApiSyncPairing $pairing): array
    {
        return [
            'instance' => $this->source->instance(),
            'snapshot' => [
                'id' => (string) $pairing->snapshot_id,
                'created_at' => $this->dateTime($pairing->snapshot_at),
                'requested_at' => $this->dateTime($pairing->claimed_at),
                'captured_at' => null,
                'expires_at' => $this->dateTime($pairing->token_expires_at),
                'status' => self::STATUS_PENDING,
            ],
            'datasets' => collect(ApiSyncDatasetService::DATASETS)
                ->map(fn (string $name): array => [
                    'name' => $name,
                    'count' => null,
                    'schema_version' => 1,
                    'status' => self::STATUS_PENDING,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function manifest(ApiSyncPairing $pairing): array
    {
        return DB::transaction(function () use ($pairing): array {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pairing = ApiSyncPairing::query()->sharedLock()->find($pairing->id) ?? throw $this->unavailable();
            $this->assertReady($pairing);
            $datasets = DB::table('api_sync_snapshot_datasets')
                ->where('pairing_id', $pairing->id)
                ->where('snapshot_id', $pairing->snapshot_id)
                ->orderBy('sort_order')
                ->get();

            if ($datasets->count() !== count(ApiSyncDatasetService::DATASETS)
                || $datasets->contains(fn (object $dataset): bool => $dataset->status !== self::STATUS_READY)) {
                throw $this->unavailable();
            }

            return [
                'instance' => $this->source->instance(),
                'snapshot' => [
                    'id' => (string) $pairing->snapshot_id,
                    'created_at' => $this->dateTime($pairing->snapshot_at),
                    'requested_at' => $this->dateTime($pairing->claimed_at),
                    'captured_at' => $this->dateTime($pairing->snapshot_started_at),
                    'expires_at' => $this->dateTime($pairing->token_expires_at),
                    'status' => self::STATUS_READY,
                    'materialized_at' => $this->dateTime($pairing->snapshot_materialized_at),
                    'record_count' => (int) $pairing->snapshot_record_count,
                    'payload_bytes' => (int) $pairing->snapshot_bytes,
                ],
                'datasets' => $datasets->map(fn (object $dataset): array => [
                    'name' => $dataset->dataset,
                    'count' => (int) $dataset->record_count,
                    'schema_version' => 1,
                    'status' => self::STATUS_READY,
                ])->all(),
            ];
        }, 3);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function page(
        ApiSyncPairing $pairing,
        string $dataset,
        string $snapshotId,
        ?string $encodedCursor,
        int $requestedLimit,
    ): array {
        return DB::transaction(function () use ($pairing, $dataset, $snapshotId, $encodedCursor, $requestedLimit): array {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            if (! in_array($dataset, ApiSyncDatasetService::DATASETS, true)) {
                throw new ApiSyncException('unknown_dataset', 'The requested synchronization dataset is not available.', 404);
            }

            $pairing = ApiSyncPairing::query()->sharedLock()->find($pairing->id) ?? throw $this->unavailable();
            if (! hash_equals((string) $pairing->snapshot_id, $snapshotId)) {
                throw new ApiSyncException('snapshot_mismatch', 'The snapshot does not belong to this synchronization session.', 409);
            }

            $this->assertReady($pairing);
            $datasetState = DB::table('api_sync_snapshot_datasets')
                ->where('pairing_id', $pairing->id)
                ->where('snapshot_id', $snapshotId)
                ->where('dataset', $dataset)
                ->first();
            if (! $datasetState || $datasetState->status !== self::STATUS_READY) {
                throw $this->unavailable();
            }

            $maximum = min(1_000, max(1, (int) config('api_sync.pagination.maximum_limit', 250)));
            $limit = max(1, min($requestedLimit, $maximum));
            $position = $encodedCursor
                ? $this->cursor->decode($encodedCursor, $dataset, $snapshotId, (string) $pairing->consumer_instance)
                : [];
            $lastSequence = filter_var($position['sequence'] ?? 0, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0],
            ]);
            if ($lastSequence === false) {
                throw new ApiSyncException('invalid_cursor', 'The synchronization cursor contains an invalid position.', 422);
            }

            $rows = DB::table('api_sync_snapshot_records')
                ->where('pairing_id', $pairing->id)
                ->where('snapshot_id', $snapshotId)
                ->where('dataset', $dataset)
                ->where('sequence', '>', $lastSequence)
                ->orderBy('sequence')
                ->limit($limit + 1)
                ->get();
            $hasMore = $rows->count() > $limit;
            $rows = $rows->take($limit)->values();
            $data = $rows->map(fn (object $row): array => $this->decodePayload($row))->all();
            $last = $rows->last();
            $nextCursor = $hasMore && $last
                ? $this->cursor->encode(
                    $dataset,
                    $snapshotId,
                    (string) $pairing->consumer_instance,
                    ['sequence' => (int) $last->sequence],
                )
                : null;

            return [
                'data' => $data,
                'meta' => [
                    'dataset' => $dataset,
                    'schema_version' => 1,
                    'snapshot_id' => $snapshotId,
                    'limit' => $limit,
                    'returned' => count($data),
                    'total' => (int) $datasetState->record_count,
                    'next_cursor' => $nextCursor,
                    'has_more' => $hasMore,
                ],
            ];
        }, 3);
    }

    public function build(string $pairingId): bool
    {
        $started = hrtime(true);
        $maximumBuildSeconds = min(1_800, max(60, (int) config('api_sync.snapshot.maximum_build_seconds', 900)));
        $builtPairing = DB::transaction(function () use ($pairingId, $started, $maximumBuildSeconds): ?ApiSyncPairing {
            DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $clock = DB::selectOne(
                "SELECT clock_timestamp() AS captured_at, set_config('statement_timeout', ?, true)",
                [($maximumBuildSeconds * 1_000).'ms'],
            );
            $capturedAt = CarbonImmutable::parse($clock->captured_at);
            $pairing = ApiSyncPairing::query()->find($pairingId);
            if (! $pairing
                || $pairing->status !== ApiSyncPairing::STATUS_CLAIMED
                || in_array($pairing->snapshot_status, [self::STATUS_READY, self::STATUS_FAILED, self::STATUS_PURGE_PENDING, self::STATUS_PURGED], true)) {
                return null;
            }
            if ($pairing->token_expires_at?->isPast()) {
                throw new ApiSyncException('snapshot_session_expired', 'The synchronization session expired before its snapshot was ready.', 409);
            }
            if (! $pairing->token_expires_at
                || $pairing->token_expires_at->lessThan($capturedAt->addSeconds($maximumBuildSeconds + 900))) {
                throw new ApiSyncException(
                    'snapshot_session_window_unavailable',
                    'The session no longer has enough time to build and transfer a safe immutable snapshot.',
                    409,
                );
            }

            // Source filters must use the real repeatable-read capture point,
            // not the earlier HTTP claim time. Keep this change in memory until
            // the final short pairing lock and commit it atomically with rows.
            $pairing->setAttribute('snapshot_at', $capturedAt);
            $buildStartedAt = $capturedAt;
            DB::table('api_sync_snapshot_records')->where('pairing_id', $pairing->id)->delete();
            DB::table('api_sync_snapshot_datasets')->where('pairing_id', $pairing->id)->update([
                'status' => self::STATUS_PENDING,
                'record_count' => 0,
                'payload_bytes' => 0,
                'completed_at' => null,
            ]);

            // Defend the hard limits again at point of use. Runtime config can
            // be replaced by tests or service providers after config loading.
            $maximumRecords = min(1_000_000, max(1_000, (int) config('api_sync.snapshot.maximum_records', 250_000)));
            $maximumBytes = min(2_147_483_648, max(10_485_760, (int) config('api_sync.snapshot.maximum_bytes', 536_870_912)));
            $maximumRecordBytes = min(1_048_576, max(65_536, (int) config('api_sync.snapshot.maximum_record_bytes', 262_144)));
            $chunk = min(1_000, max(50, (int) config('api_sync.snapshot.insert_chunk', 500)));
            $totalRecords = 0;
            $totalBytes = 0;

            foreach (ApiSyncDatasetService::DATASETS as $dataset) {
                $position = [];
                $datasetRecords = 0;
                $datasetBytes = 0;

                do {
                    $this->guardBuildTime($started, $maximumBuildSeconds);
                    $page = $this->source->materializationPage($pairing, $dataset, $position, $chunk);
                    $this->guardBuildTime($started, $maximumBuildSeconds);
                    $insert = [];

                    foreach ($page['data'] as $record) {
                        $payload = json_encode(
                            $record,
                            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                        );
                        $bytes = strlen($payload);
                        $totalRecords++;
                        $datasetRecords++;
                        $totalBytes += $bytes;
                        $datasetBytes += $bytes;

                        if ($bytes > $maximumRecordBytes
                            || $totalRecords > $maximumRecords
                            || $totalBytes > $maximumBytes) {
                            throw new ApiSyncException(
                                'snapshot_capacity_exceeded',
                                'The source exceeds this provider\'s bounded immutable snapshot capacity.',
                                413,
                            );
                        }

                        $sourceId = (string) ($record['id'] ?? '');
                        if ($sourceId === '' || mb_strlen($sourceId) > 255) {
                            throw new ApiSyncException('snapshot_invalid_source_id', 'A source record has an invalid synchronization identifier.', 500);
                        }
                        $checksum = (string) ($record['checksum'] ?? '');
                        if (! preg_match('/^[a-f0-9]{64}$/D', $checksum)) {
                            throw new ApiSyncException('snapshot_invalid_checksum', 'A source record has an invalid synchronization checksum.', 500);
                        }

                        $insert[] = [
                            'pairing_id' => $pairing->id,
                            'snapshot_id' => $pairing->snapshot_id,
                            'dataset' => $dataset,
                            'sequence' => $datasetRecords,
                            'source_id' => $sourceId,
                            'checksum' => $checksum,
                            'payload_hash' => hash('sha256', $payload),
                            'payload' => $payload,
                            'payload_bytes' => $bytes,
                            'created_at' => $buildStartedAt,
                        ];
                    }

                    if ($insert !== []) {
                        DB::table('api_sync_snapshot_records')->insert($insert);
                        $this->guardBuildTime($started, $maximumBuildSeconds);
                    }
                    $position = $page['next_position'] ?? [];
                } while ($page['has_more']);

                DB::table('api_sync_snapshot_datasets')
                    ->where('pairing_id', $pairing->id)
                    ->where('dataset', $dataset)
                    ->update([
                        'status' => self::STATUS_READY,
                        'record_count' => $datasetRecords,
                        'payload_bytes' => $datasetBytes,
                        'completed_at' => now(),
                    ]);
            }

            $this->guardBuildTime($started, $maximumBuildSeconds);
            $locked = ApiSyncPairing::query()->lockForUpdate()->find($pairing->id);
            if (! $locked
                || $locked->status !== ApiSyncPairing::STATUS_CLAIMED
                || $locked->snapshot_status !== self::STATUS_PENDING
                || ! hash_equals((string) $locked->snapshot_id, (string) $pairing->snapshot_id)
                || $locked->token_expires_at?->isPast()) {
                throw new ApiSyncException(
                    'snapshot_build_cancelled',
                    'The synchronization session closed while its snapshot was being prepared.',
                    409,
                );
            }

            $locked->forceFill([
                'snapshot_status' => self::STATUS_READY,
                'snapshot_started_at' => $buildStartedAt,
                'snapshot_materialized_at' => now(),
                'snapshot_failed_at' => null,
                'snapshot_failure_reason' => null,
                'snapshot_record_count' => $totalRecords,
                'snapshot_bytes' => $totalBytes,
            ])->save();

            $locked = $locked->fresh();
            $this->audit->record(
                $locked,
                'sync_snapshot_materialized',
                'ATTP completed an immutable synchronization snapshot for AU-PReMIS.',
                [
                    'record_count' => (int) $locked->snapshot_record_count,
                    'payload_bytes' => (int) $locked->snapshot_bytes,
                ],
            );

            return $locked;
        }, 3);

        if (! $builtPairing) {
            return false;
        }

        return true;
    }

    public function markFailed(string $pairingId, string $reason = 'snapshot_build_failed'): void
    {
        $pairing = DB::transaction(function () use ($pairingId, $reason): ?ApiSyncPairing {
            $pairing = ApiSyncPairing::query()->lockForUpdate()->find($pairingId);
            if (! $pairing || in_array($pairing->snapshot_status, [self::STATUS_READY, self::STATUS_PURGE_PENDING, self::STATUS_PURGED], true)) {
                return null;
            }

            if ($pairing->snapshot_status !== self::STATUS_FAILED) {
                $pairing->forceFill([
                    'snapshot_status' => self::STATUS_FAILED,
                    'snapshot_failed_at' => now(),
                    'snapshot_failure_reason' => mb_substr($reason, 0, 255),
                    'snapshot_record_count' => 0,
                    'snapshot_bytes' => 0,
                ])->save();
                $this->audit->record(
                    $pairing,
                    'sync_snapshot_failed',
                    'ATTP could not safely complete the immutable synchronization snapshot.',
                    ['failure_code' => $reason],
                );
            }

            return $pairing->fresh();
        }, 3);

        if ($pairing) {
            // Pairing locks are never held while waiting for materialized-row
            // locks. This keeps lifecycle operations deadlock-free with a
            // builder, whose lock order is snapshot rows then pairing.
            DB::transaction(function () use ($pairing): void {
                DB::table('api_sync_snapshot_records')->where('pairing_id', $pairing->id)->delete();
                DB::table('api_sync_snapshot_datasets')->where('pairing_id', $pairing->id)->update([
                    'status' => self::STATUS_FAILED,
                    'record_count' => 0,
                    'payload_bytes' => 0,
                    'completed_at' => null,
                ]);
            }, 3);
        }
    }

    public function requestPurge(ApiSyncPairing $pairing): void
    {
        if (blank($pairing->snapshot_id)) {
            return;
        }

        $pairing->forceFill([
            'snapshot_status' => self::STATUS_PURGE_PENDING,
            'snapshot_purged_at' => null,
        ])->save();
    }

    public function dispatchPending(int $limit = 100): int
    {
        $ids = ApiSyncPairing::query()
            ->where('status', ApiSyncPairing::STATUS_CLAIMED)
            ->where('snapshot_status', self::STATUS_PENDING)
            ->where('token_expires_at', '>', now())
            ->orderBy('created_at')
            ->limit(max(1, min($limit, 1_000)))
            ->pluck('id');

        foreach ($ids as $id) {
            BuildApiSyncSnapshot::dispatch((string) $id)
                ->onConnection((string) config('api_sync.snapshot.connection', 'api_sync_database'))
                ->onQueue((string) config('api_sync.snapshot.queue', 'api-sync'));
        }

        return $ids->count();
    }

    public function pruneExpired(int $limit = 100): int
    {
        $ids = ApiSyncPairing::query()
            ->whereNotNull('snapshot_id')
            ->whereNull('snapshot_purged_at')
            ->where(function ($closed): void {
                $closed->where('status', '!=', ApiSyncPairing::STATUS_CLAIMED)
                    ->orWhere('token_expires_at', '<=', now());
            })
            ->orderBy('created_at')
            ->limit(max(1, min($limit, 1_000)))
            ->pluck('id');
        $purged = 0;

        foreach ($ids as $id) {
            $pairing = DB::transaction(function () use ($id): ?ApiSyncPairing {
                $pairing = ApiSyncPairing::query()->find($id);
                if (! $pairing || $pairing->snapshot_purged_at || blank($pairing->snapshot_id)) {
                    return null;
                }
                $closed = $pairing->status !== ApiSyncPairing::STATUS_CLAIMED
                    || $pairing->token_expires_at?->isPast();
                if (! $closed) {
                    return null;
                }

                // Delete children first and lock the pairing only for the final
                // conditional update. This matches the builder's lock order.
                DB::table('api_sync_snapshot_records')->where('pairing_id', $pairing->id)->delete();
                DB::table('api_sync_snapshot_datasets')->where('pairing_id', $pairing->id)->delete();
                $updated = ApiSyncPairing::query()
                    ->whereKey($pairing->id)
                    ->whereNull('snapshot_purged_at')
                    ->where(function ($closed): void {
                        $closed->where('status', '!=', ApiSyncPairing::STATUS_CLAIMED)
                            ->orWhere('token_expires_at', '<=', now());
                    })
                    ->update([
                        'snapshot_status' => self::STATUS_PURGED,
                        'snapshot_purged_at' => now(),
                        'snapshot_record_count' => 0,
                        'snapshot_bytes' => 0,
                    ]);
                if ($updated !== 1) {
                    throw new \RuntimeException('Snapshot cleanup lost its closed-session precondition.');
                }

                $pairing = ApiSyncPairing::query()->find($pairing->id);
                $this->audit->record(
                    $pairing,
                    'sync_snapshot_purged',
                    'Expired or closed API synchronization snapshot storage was securely removed.',
                );

                return $pairing;
            }, 3);

            if ($pairing) {
                $purged++;
            }
        }

        return $purged;
    }

    private function assertReady(ApiSyncPairing $pairing): void
    {
        if (in_array($pairing->snapshot_status, [self::STATUS_PENDING, self::STATUS_BUILDING], true)) {
            throw new ApiSyncException(
                'snapshot_building',
                'The immutable synchronization snapshot is still being prepared. Retry the manifest shortly.',
                425,
                ['Retry-After' => '5'],
            );
        }
        if ($pairing->snapshot_status === self::STATUS_FAILED) {
            throw new ApiSyncException(
                'snapshot_failed',
                'The immutable synchronization snapshot could not be prepared safely. Abandon this claim and create a new pairing.',
                409,
            );
        }
        if ($pairing->snapshot_status !== self::STATUS_READY) {
            throw $this->unavailable();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(object $row): array
    {
        try {
            $decoded = is_array($row->payload)
                ? $row->payload
                : json_decode((string) $row->payload, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApiSyncException('snapshot_integrity_error', 'A materialized synchronization record failed integrity decoding.', 500);
        }
        if (! is_array($decoded)) {
            throw new ApiSyncException('snapshot_integrity_error', 'A materialized synchronization record is malformed.', 500);
        }

        try {
            $encoded = json_encode(
                $decoded,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
            $checksumPayload = [
                'attributes' => $decoded['attributes'] ?? null,
                'relationships' => $decoded['relationships'] ?? null,
            ];
            $recomputedChecksum = hash('sha256', json_encode(
                $this->sortRecursively($checksumPayload),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ));
        } catch (JsonException) {
            throw new ApiSyncException('snapshot_integrity_error', 'A materialized synchronization record failed integrity encoding.', 500);
        }

        $payloadId = is_scalar($decoded['id'] ?? null) ? (string) $decoded['id'] : '';
        $payloadChecksum = is_string($decoded['checksum'] ?? null) ? $decoded['checksum'] : '';
        $storedChecksum = (string) $row->checksum;
        $storedPayloadHash = (string) $row->payload_hash;
        if ($payloadId === ''
            || ! hash_equals((string) $row->source_id, $payloadId)
            || ! preg_match('/^[a-f0-9]{64}$/D', $payloadChecksum)
            || ! hash_equals($storedChecksum, $payloadChecksum)
            || ! hash_equals($recomputedChecksum, $payloadChecksum)
            || ! preg_match('/^[a-f0-9]{64}$/D', $storedPayloadHash)
            || ! hash_equals($storedPayloadHash, hash('sha256', $encoded))
            || strlen($encoded) !== (int) $row->payload_bytes) {
            throw new ApiSyncException('snapshot_integrity_error', 'A materialized synchronization record failed integrity verification.', 500);
        }

        return $decoded;
    }

    /**
     * @return array<mixed>
     */
    private function sortRecursively(array $value): array
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->sortRecursively($item);
            }
        }

        return $value;
    }

    private function guardBuildTime(int $started, int $maximumSeconds): void
    {
        if ((hrtime(true) - $started) / 1_000_000_000 > $maximumSeconds) {
            throw new ApiSyncException(
                'snapshot_build_timeout',
                'The source could not be materialized within the configured safety time limit.',
                503,
            );
        }
    }

    private function dateTime(mixed $value): ?string
    {
        return $value ? CarbonImmutable::parse($value)->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }

    private function unavailable(): ApiSyncException
    {
        return new ApiSyncException('snapshot_unavailable', 'The immutable synchronization snapshot is unavailable.', 409);
    }
}
