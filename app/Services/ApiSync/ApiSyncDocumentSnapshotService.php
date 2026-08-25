<?php

namespace App\Services\ApiSync;

use App\Exceptions\ApiSyncDocumentHeldException;
use App\Exceptions\ApiSyncException;
use App\Jobs\BuildApiSyncDocumentSnapshot;
use App\Models\ApiSyncInvitation;
use App\Models\ApiSyncPairing;
use App\Models\ApiSyncSnapshotDocument;
use App\Models\ApiSyncSnapshotDocumentIssue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ApiSyncDocumentSnapshotService
{
    public const STATUS_NOT_REQUESTED = 'not_requested';

    public const STATUS_PENDING = 'pending';

    public const STATUS_BUILDING = 'building';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PURGE_PENDING = 'purge_pending';

    public const STATUS_PURGED = 'purged';

    public function __construct(
        private readonly ApiSyncDocumentSource $source,
        private readonly ApiSyncDocumentSecurityPolicy $policy,
        private readonly ApiSyncCursor $cursor,
        private readonly ApiSyncAuditService $audit,
    ) {}

    public function initialize(ApiSyncPairing $pairing, ApiSyncInvitation $invitation): void
    {
        $requested = $this->isRequested($invitation);
        $changed = DB::transaction(function () use ($pairing, $requested): ?ApiSyncPairing {
            $locked = ApiSyncPairing::query()->lockForUpdate()->findOrFail($pairing->id);
            if (! hash_equals((string) $locked->snapshot_id, (string) $pairing->snapshot_id)) {
                throw new RuntimeException('The document snapshot cannot be attached to a different record snapshot.');
            }
            $nextStatus = $requested ? self::STATUS_PENDING : self::STATUS_NOT_REQUESTED;
            if ($locked->document_snapshot_status === $nextStatus) {
                return null;
            }
            if (! in_array($locked->document_snapshot_status, [null, self::STATUS_NOT_REQUESTED], true)) {
                return null;
            }
            $locked->forceFill([
                'document_snapshot_status' => $nextStatus,
                'document_snapshot_started_at' => null,
                'document_snapshot_materialized_at' => null,
                'document_snapshot_purged_at' => null,
                'document_snapshot_failure_reason' => null,
                'document_discovered_count' => 0,
                'document_ready_count' => 0,
                'document_held_count' => 0,
                'document_snapshot_bytes' => 0,
            ])->save();

            return $locked->fresh();
        }, 3);

        if ($changed) {
            $this->audit->record(
                $changed,
                $requested ? 'sync_document_snapshot_requested' : 'sync_document_snapshot_not_requested',
                $requested
                    ? 'ATTP queued the locally approved project documents for immutable snapshot preparation.'
                    : 'The approved synchronization scope did not include project document transfer.',
            );
        }
    }

    public function dispatch(ApiSyncPairing $pairing): void
    {
        BuildApiSyncDocumentSnapshot::dispatch((string) $pairing->id)
            ->onConnection((string) config('api_sync_documents.queue.connection', 'api_sync_database'))
            ->onQueue((string) config('api_sync_documents.queue.name', 'api-sync'));
    }

    public function dispatchPending(int $limit = 100): int
    {
        $ids = ApiSyncPairing::query()
            ->whereNotNull('inbound_invitation_id')
            ->where('status', ApiSyncPairing::STATUS_CLAIMED)
            ->where('snapshot_status', ApiSyncSnapshotService::STATUS_READY)
            ->where('document_snapshot_status', self::STATUS_PENDING)
            ->where('token_expires_at', '>', now())
            ->orderBy('created_at')
            ->limit(max(1, min(1_000, $limit)))
            ->pluck('id');

        foreach ($ids as $id) {
            BuildApiSyncDocumentSnapshot::dispatch((string) $id)
                ->onConnection((string) config('api_sync_documents.queue.connection', 'api_sync_database'))
                ->onQueue((string) config('api_sync_documents.queue.name', 'api-sync'));
        }

        return $ids->count();
    }

    public function hasContinuation(string $pairingId): bool
    {
        return ApiSyncPairing::query()
            ->whereKey($pairingId)
            ->where('status', ApiSyncPairing::STATUS_CLAIMED)
            ->where('snapshot_status', ApiSyncSnapshotService::STATUS_READY)
            ->where('document_snapshot_status', self::STATUS_PENDING)
            ->where('token_expires_at', '>', now())
            ->whereHas('snapshotDocuments', fn ($documents) => $documents->where('state', ApiSyncSnapshotDocument::STATE_PREPARING))
            ->exists();
    }

    public function build(string $pairingId): bool
    {
        $started = hrtime(true);
        $pairing = DB::transaction(function () use ($pairingId): ?ApiSyncPairing {
            $locked = ApiSyncPairing::query()->with('inboundInvitation')->lockForUpdate()->find($pairingId);
            if (! $locked
                || $locked->status !== ApiSyncPairing::STATUS_CLAIMED
                || in_array($locked->document_snapshot_status, [self::STATUS_READY, self::STATUS_PURGE_PENDING, self::STATUS_PURGED], true)) {
                return null;
            }
            if ($locked->snapshot_status !== ApiSyncSnapshotService::STATUS_READY) {
                return null;
            }
            if (! $locked->inboundInvitation || ! $this->isRequested($locked->inboundInvitation)) {
                $locked->forceFill(['document_snapshot_status' => self::STATUS_NOT_REQUESTED])->save();

                return null;
            }
            if ($locked->token_expires_at?->isPast()) {
                $locked->forceFill(['document_snapshot_status' => self::STATUS_PURGE_PENDING])->save();

                return null;
            }

            $firstPass = ! ApiSyncSnapshotDocument::query()->where('pairing_id', $locked->id)->exists();
            $locked->forceFill([
                'document_snapshot_status' => self::STATUS_BUILDING,
                'document_snapshot_started_at' => $firstPass ? now() : $locked->document_snapshot_started_at,
                'document_snapshot_materialized_at' => null,
                'document_snapshot_purged_at' => null,
                'document_snapshot_failure_reason' => null,
            ])->save();

            return $locked->fresh(['inboundInvitation']);
        }, 3);
        if (! $pairing) {
            return false;
        }

        $maximumDocuments = min(1_000, max(1, (int) config('api_sync_documents.maximum_documents', 1_000)));
        $maximumBytes = min(2 * 1_024 * 1_024 * 1_024, max(1_024, (int) config('api_sync_documents.maximum_snapshot_bytes', 2 * 1_024 * 1_024 * 1_024)));
        /** @var array<string,array<string,mixed>> $capturedCandidates */
        $capturedCandidates = [];
        if (! ApiSyncSnapshotDocument::query()->where('pairing_id', $pairing->id)->exists()) {
            // This is the only whole-directory reset. Once the source manifest
            // exists, retries preserve every completed immutable file.
            $this->policy->purgeSnapshot((string) $pairing->snapshot_id);
            $manifest = DB::transaction(function () use ($pairing, $maximumDocuments): array {
                if (DB::connection()->getDriverName() === 'pgsql') {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                    $clock = DB::selectOne('SELECT clock_timestamp() AS captured_at');
                    $capturedAt = CarbonImmutable::parse($clock->captured_at);
                } else {
                    $capturedAt = CarbonImmutable::now();
                }
                $captured = $this->source->candidates($capturedAt, $maximumDocuments);
                $rows = [];
                $candidateMap = [];
                foreach ($captured['items'] as $index => $candidate) {
                    $documentId = (string) Str::uuid();
                    $rows[] = [
                        'id' => $documentId,
                        'pairing_id' => $pairing->id,
                        'snapshot_id' => $pairing->snapshot_id,
                        'sequence' => $index + 1,
                        'source_type' => $candidate['source_type'],
                        'source_document_id' => $candidate['source_document_id'],
                        'source_version_id' => $candidate['source_version_id'],
                        'source_key' => $candidate['source_key'],
                        'source_revision' => $candidate['revision'],
                        'category' => $candidate['category'],
                        'classification' => 'restricted',
                        'title' => $this->safeText((string) $candidate['title'], 255, 'Approved project document'),
                        'display_filename' => $this->policy->sanitizeFilename((string) $candidate['display_filename']),
                        'detected_mime' => null,
                        'byte_size' => null,
                        'sha256' => null,
                        'portfolio_external_id' => $candidate['portfolio_external_id'],
                        'project_external_ids' => json_encode(array_values($candidate['project_external_ids']), JSON_THROW_ON_ERROR),
                        'parent_type' => $candidate['parent_type'],
                        'parent_external_id' => $candidate['parent_external_id'],
                        'source_updated_at' => $candidate['source_updated_at'],
                        'storage_disk' => null,
                        'storage_path' => null,
                        'state' => ApiSyncSnapshotDocument::STATE_PREPARING,
                        'hold_code' => null,
                        'hold_message' => null,
                        'copied_at' => null,
                        'purged_at' => null,
                        'created_at' => $capturedAt,
                        'updated_at' => $capturedAt,
                    ];
                    $candidateMap[(string) $candidate['source_key']] = $candidate;
                }
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table('api_sync_snapshot_documents')->insert($chunk);
                }
                ApiSyncPairing::query()->whereKey($pairing->id)->update([
                    'document_snapshot_started_at' => $capturedAt,
                    'document_discovered_count' => (int) $captured['total'],
                    'document_ready_count' => 0,
                    'document_held_count' => (int) $captured['overflow'],
                    'document_snapshot_bytes' => 0,
                ]);

                return [
                    'candidate_map' => $candidateMap,
                    'overflow' => (int) $captured['overflow'],
                    'captured_at' => $capturedAt,
                ];
            }, 3);
            $capturedCandidates = $manifest['candidate_map'];
        }

        $manifestCount = ApiSyncSnapshotDocument::query()->where('pairing_id', $pairing->id)->count();
        $overflow = max(0, (int) $pairing->fresh()->document_discovered_count - $manifestCount);
        if ($overflow > 0
            && ! ApiSyncSnapshotDocumentIssue::query()
                ->where('pairing_id', $pairing->id)
                ->where('code', 'document_limit_exceeded')
                ->exists()) {
            $this->issue($pairing, null, null, 'document_limit_exceeded', 'Additional approved documents were held because this snapshot reached its 1,000-document safety limit.', [
                'held_count' => $overflow,
                'maximum_documents' => $maximumDocuments,
            ]);
        }
        $ready = ApiSyncSnapshotDocument::query()
            ->where('pairing_id', $pairing->id)
            ->where('state', ApiSyncSnapshotDocument::STATE_READY)
            ->count();
        $held = $overflow + ApiSyncSnapshotDocument::query()
            ->where('pairing_id', $pairing->id)
            ->where('state', ApiSyncSnapshotDocument::STATE_HELD)
            ->count();
        $readyBytes = (int) ApiSyncSnapshotDocument::query()
            ->where('pairing_id', $pairing->id)
            ->where('state', ApiSyncSnapshotDocument::STATE_READY)
            ->sum('byte_size');
        $batchSize = min(100, max(1, (int) config('api_sync_documents.documents_per_job', 25)));
        $maximumJobSeconds = min(300, max(30, (int) config('api_sync_documents.maximum_job_seconds', 240)));
        $pending = ApiSyncSnapshotDocument::query()
            ->where('pairing_id', $pairing->id)
            ->where('snapshot_id', $pairing->snapshot_id)
            ->where('state', ApiSyncSnapshotDocument::STATE_PREPARING)
            ->orderBy('sequence')
            ->limit($batchSize)
            ->get();

        foreach ($pending as $document) {
            if ((hrtime(true) - $started) / 1_000_000_000 >= $maximumJobSeconds) {
                break;
            }
            $candidate = $capturedCandidates[(string) $document->source_key]
                ?? $this->source->candidateFor(
                    (string) $document->source_type,
                    (string) $document->source_document_id,
                    $document->source_version_id ? (string) $document->source_version_id : null,
                );
            try {
                if (! is_array($candidate)
                    || ! hash_equals((string) $document->source_revision, (string) ($candidate['revision'] ?? ''))) {
                    throw new ApiSyncDocumentHeldException('source_changed_during_snapshot', 'The document approval, version, or project relationship changed after the source manifest was captured.');
                }
                if (isset($candidate['pre_hold_code'])) {
                    throw new ApiSyncDocumentHeldException(
                        (string) $candidate['pre_hold_code'],
                        (string) $candidate['pre_hold_message'],
                    );
                }
                if ($candidate['portfolio_external_id'] === null || $candidate['portfolio_external_id'] === '') {
                    throw new ApiSyncDocumentHeldException('portfolio_relationship_missing', 'The approved document is not linked to a transferable portfolio.');
                }
                if ($candidate['expected_size'] !== null && $readyBytes + (int) $candidate['expected_size'] > $maximumBytes) {
                    throw new ApiSyncDocumentHeldException('snapshot_capacity_exceeded', 'The approved document was held because this snapshot reached its 2 GiB safety limit.');
                }

                $destination = $this->policy->destinationPath((string) $pairing->snapshot_id, (string) $document->id);
                $this->policy->clearPreparingDestination($destination);
                $staged = $this->policy->stage(
                    (string) $candidate['source_path'],
                    $destination,
                    (string) $candidate['display_filename'],
                    $candidate['expected_size'],
                    $candidate['expected_checksum'],
                    fn (): bool => $this->source->stillApproved($candidate),
                );
                if ($readyBytes + $staged['byte_size'] > $maximumBytes) {
                    $this->policy->removeStaged($staged['storage_path']);
                    throw new ApiSyncDocumentHeldException('snapshot_capacity_exceeded', 'The approved document was held because this snapshot reached its 2 GiB safety limit.');
                }

                $commit = $this->commitStagedDocument($pairing, $document, $candidate, $staged);
                if ($commit !== 'committed') {
                    $this->policy->removeStaged($staged['storage_path']);
                    if ($commit === 'source_changed') {
                        throw new ApiSyncDocumentHeldException('source_changed_during_snapshot', 'The document approval or project relationship changed before its immutable snapshot was committed.');
                    }
                    throw new ApiSyncException('document_snapshot_cancelled', 'The synchronization session closed while an approved document was being committed.', 409);
                }
                $ready++;
                $readyBytes += $staged['byte_size'];
            } catch (ApiSyncDocumentHeldException $exception) {
                $held++;
                $this->hold($pairing, $document, $candidate, $exception->holdCode, $exception->getMessage());
            }

            ApiSyncPairing::query()->whereKey($pairing->id)->update([
                'document_ready_count' => $ready,
                'document_held_count' => $held,
                'document_snapshot_bytes' => $readyBytes,
            ]);
        }

        $remaining = ApiSyncSnapshotDocument::query()
            ->where('pairing_id', $pairing->id)
            ->where('state', ApiSyncSnapshotDocument::STATE_PREPARING)
            ->count();
        $completed = DB::transaction(function () use ($pairing, $ready, $held, $readyBytes, $remaining): ApiSyncPairing {
            $locked = ApiSyncPairing::query()->lockForUpdate()->findOrFail($pairing->id);
            if ($locked->status !== ApiSyncPairing::STATUS_CLAIMED
                || $locked->document_snapshot_status !== self::STATUS_BUILDING
                || ! hash_equals((string) $locked->snapshot_id, (string) $pairing->snapshot_id)
                || $locked->token_expires_at?->isPast()) {
                $locked->forceFill(['document_snapshot_status' => self::STATUS_PURGE_PENDING])->save();
                throw new ApiSyncException('document_snapshot_cancelled', 'The synchronization session closed while approved documents were being prepared.', 409);
            }
            $locked->forceFill([
                'document_snapshot_status' => $remaining === 0 ? self::STATUS_READY : self::STATUS_PENDING,
                'document_snapshot_materialized_at' => $remaining === 0 ? now() : null,
                'document_snapshot_failure_reason' => null,
                'document_ready_count' => $ready,
                'document_held_count' => $held,
                'document_snapshot_bytes' => $readyBytes,
            ])->save();

            return $locked->fresh();
        }, 3);

        if ($remaining > 0) {
            $this->audit->record(
                $completed,
                'sync_document_snapshot_checkpointed',
                'ATTP safely checkpointed part of the immutable approved-document snapshot for background continuation.',
                ['ready' => $ready, 'held' => $held, 'remaining' => $remaining, 'bytes' => $readyBytes],
                dataset: 'documents',
                recordCount: $ready,
            );

            return false;
        }

        $this->audit->record(
            $completed,
            'sync_document_snapshot_materialized',
            'ATTP completed the immutable approved-document snapshot for AU-PReMIS.',
            [
                'discovered' => (int) $completed->document_discovered_count,
                'ready' => $ready,
                'held' => $held,
                'bytes' => $readyBytes,
                'allowlist' => ['performance_reports', 'mission_reports', 'knowledge_repository'],
            ],
            dataset: 'documents',
            recordCount: $ready,
        );

        return true;
    }

    /** @return array<string,mixed> */
    public function summary(ApiSyncPairing $pairing): array
    {
        $pairing->refresh();

        return [
            'status' => (string) ($pairing->document_snapshot_status ?: self::STATUS_NOT_REQUESTED),
            'discovered' => (int) $pairing->document_discovered_count,
            'ready' => (int) $pairing->document_ready_count,
            'held' => (int) $pairing->document_held_count,
            'bytes' => (int) $pairing->document_snapshot_bytes,
            'captured_at' => $this->dateTime($pairing->document_snapshot_started_at),
            'materialized_at' => $this->dateTime($pairing->document_snapshot_materialized_at),
            'limits' => [
                'documents' => min(1_000, max(1, (int) config('api_sync_documents.maximum_documents', 1_000))),
                'file_bytes' => min(20 * 1_024 * 1_024, max(1_024, (int) config('api_sync_documents.maximum_file_bytes', 20 * 1_024 * 1_024))),
                'snapshot_bytes' => min(2 * 1_024 * 1_024 * 1_024, max(1_024, (int) config('api_sync_documents.maximum_snapshot_bytes', 2 * 1_024 * 1_024 * 1_024))),
                'chunk_bytes' => min(4 * 1_024 * 1_024, max(64 * 1_024, (int) config('api_sync_documents.maximum_chunk_bytes', 4 * 1_024 * 1_024))),
            ],
        ];
    }

    /** @return array{data:list<array<string,mixed>>,meta:array<string,mixed>} */
    public function inventory(
        ApiSyncPairing $pairing,
        string $snapshotId,
        ?string $encodedCursor,
        int $requestedLimit,
    ): array {
        $pairing = ApiSyncPairing::query()->find($pairing->id) ?? throw $this->unavailable();
        $this->assertSnapshot($pairing, $snapshotId);
        $this->assertReady($pairing);

        $limit = max(1, min(250, $requestedLimit));
        $position = $encodedCursor
            ? $this->cursor->decode(
                $encodedCursor,
                'documents.inventory',
                $snapshotId,
                (string) $pairing->consumer_instance,
            )
            : [];
        $sequence = filter_var($position['sequence'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($sequence === false) {
            throw new ApiSyncException('invalid_cursor', 'The document inventory cursor contains an invalid position.', 422);
        }

        $rows = ApiSyncSnapshotDocument::query()
            ->where('pairing_id', $pairing->id)
            ->where('snapshot_id', $snapshotId)
            ->where('sequence', '>', $sequence)
            ->orderBy('sequence')
            ->limit($limit + 1)
            ->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();
        $last = $rows->last();
        $next = $hasMore && $last
            ? $this->cursor->encode(
                'documents.inventory',
                $snapshotId,
                (string) $pairing->consumer_instance,
                ['sequence' => (int) $last->sequence],
            )
            : null;

        return [
            'data' => $rows->map(fn (ApiSyncSnapshotDocument $document): array => $this->inventoryRow($document))->all(),
            'meta' => [
                'snapshot_id' => $snapshotId,
                'status' => self::STATUS_READY,
                'limit' => $limit,
                'returned' => $rows->count(),
                'total' => (int) $pairing->document_discovered_count,
                'ready' => (int) $pairing->document_ready_count,
                'held' => (int) $pairing->document_held_count,
                'bytes' => (int) $pairing->document_snapshot_bytes,
                'next_cursor' => $next,
                'has_more' => $hasMore,
            ],
        ];
    }

    /** @return array{bytes:string,document:ApiSyncSnapshotDocument,start:int,end:int,total:int,etag:string,chunk_sha256:string} */
    public function content(
        ApiSyncPairing $pairing,
        string $snapshotId,
        string $transferId,
        ?string $rangeHeader,
        ?string $ifMatch,
    ): array {
        $pairing = ApiSyncPairing::query()->find($pairing->id) ?? throw $this->unavailable();
        $this->assertSnapshot($pairing, $snapshotId);
        $this->assertReady($pairing);
        $document = ApiSyncSnapshotDocument::query()
            ->where('id', $transferId)
            ->where('pairing_id', $pairing->id)
            ->where('snapshot_id', $snapshotId)
            ->first();
        if (! $document) {
            throw new ApiSyncException('document_not_found', 'The requested immutable document is not part of this synchronization snapshot.', 404);
        }
        if ($document->state !== ApiSyncSnapshotDocument::STATE_READY
            || ! $document->storage_path
            || ! $document->sha256
            || $document->byte_size === null) {
            throw new ApiSyncException('document_not_ready', 'The requested document was held or is not available for content transfer.', 409);
        }

        $etag = '"'.strtolower((string) $document->sha256).'"';
        if ($ifMatch === null || ! hash_equals($etag, trim($ifMatch))) {
            throw new ApiSyncException('document_precondition_failed', 'If-Match must contain the immutable document ETag from the inventory.', 412, ['ETag' => $etag]);
        }
        $total = (int) $document->byte_size;
        if (! is_string($rangeHeader) || ! preg_match('/^bytes=(\d+)-(\d+)$/D', trim($rangeHeader), $match)) {
            throw new ApiSyncException('document_range_required', 'A single explicit byte range is required for document transfer.', 416, ['Content-Range' => 'bytes */'.$total]);
        }
        $start = filter_var($match[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $end = filter_var($match[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $maximum = min(4 * 1_024 * 1_024, max(64 * 1_024, (int) config('api_sync_documents.maximum_chunk_bytes', 4 * 1_024 * 1_024)));
        if ($start === false || $end === false || $end < $start || $end >= $total || $end - $start + 1 > $maximum) {
            throw new ApiSyncException('document_range_invalid', 'The requested byte range is invalid or exceeds the 4 MiB chunk limit.', 416, ['Content-Range' => 'bytes */'.$total]);
        }

        try {
            $bytes = $this->policy->readStaged((string) $document->storage_path, $start, $end - $start + 1, $total);
        } catch (RuntimeException) {
            $this->audit->record($pairing, 'sync_document_integrity_failed', 'An immutable document chunk was withheld because private staging failed an integrity check.', [
                'transfer_id' => (string) $document->id,
                'snapshot_id' => $snapshotId,
            ], dataset: 'documents', recordCount: 0);
            throw new ApiSyncException('document_integrity_error', 'The immutable document failed a provider-side integrity check and was not served.', 409);
        }

        $this->audit->record($pairing, 'sync_document_chunk_exported', 'AU-PReMIS retrieved one authenticated immutable document chunk.', [
            'transfer_id' => (string) $document->id,
            'snapshot_id' => $snapshotId,
            'offset' => $start,
            'length' => strlen($bytes),
        ], dataset: 'documents', recordCount: 1);

        return [
            'bytes' => $bytes,
            'document' => $document,
            'start' => $start,
            'end' => $end,
            'total' => $total,
            'etag' => $etag,
            'chunk_sha256' => hash('sha256', $bytes),
        ];
    }

    public function requestPurge(ApiSyncPairing $pairing): void
    {
        if (! $pairing->snapshot_id || $pairing->document_snapshot_status === self::STATUS_NOT_REQUESTED) {
            return;
        }
        ApiSyncPairing::query()->whereKey($pairing->id)->update([
            'document_snapshot_status' => self::STATUS_PURGE_PENDING,
            'document_snapshot_purged_at' => null,
        ]);
    }

    public function assertCompletionReady(ApiSyncPairing $pairing): void
    {
        if (! $pairing->inbound_invitation_id) {
            return;
        }
        if (! in_array($pairing->document_snapshot_status, [self::STATUS_NOT_REQUESTED, self::STATUS_READY], true)) {
            throw new ApiSyncException(
                'document_transfer_incomplete',
                'The synchronization cannot be completed until the approved document phase reaches a terminal ready state.',
                409,
                ['Retry-After' => '5'],
            );
        }
    }

    public function pruneExpired(int $limit = 100): int
    {
        $ids = ApiSyncPairing::query()
            ->whereNotNull('snapshot_id')
            ->whereNotIn('document_snapshot_status', [self::STATUS_NOT_REQUESTED, self::STATUS_PURGED])
            ->where(function ($closed): void {
                $closed->where('document_snapshot_status', self::STATUS_PURGE_PENDING)
                    ->orWhere('document_snapshot_status', self::STATUS_FAILED)
                    ->orWhere('status', '!=', ApiSyncPairing::STATUS_CLAIMED)
                    ->orWhere('token_expires_at', '<=', now());
            })
            ->orderBy('created_at')
            ->limit(max(1, min(1_000, $limit)))
            ->pluck('id');
        $purged = 0;
        foreach ($ids as $id) {
            if ($this->purge((string) $id)) {
                $purged++;
            }
        }

        return $purged;
    }

    public function purge(string $pairingId): bool
    {
        $mutex = Cache::lock('api-sync-document-snapshot-build:'.$pairingId, 300);
        if (! $mutex->get()) {
            return false;
        }
        try {
            $pairing = DB::transaction(function () use ($pairingId): ?ApiSyncPairing {
                $locked = ApiSyncPairing::query()->lockForUpdate()->find($pairingId);
                if (! $locked || ! $locked->snapshot_id || $locked->document_snapshot_status === self::STATUS_PURGED) {
                    return null;
                }
                $closed = $locked->document_snapshot_status === self::STATUS_PURGE_PENDING
                    || $locked->document_snapshot_status === self::STATUS_FAILED
                    || $locked->status !== ApiSyncPairing::STATUS_CLAIMED
                    || $locked->token_expires_at?->isPast();
                if (! $closed) {
                    return null;
                }
                $locked->forceFill(['document_snapshot_status' => self::STATUS_PURGE_PENDING])->save();

                return $locked->fresh();
            }, 3);
            if (! $pairing) {
                return false;
            }

            $this->policy->purgeSnapshot((string) $pairing->snapshot_id);
            DB::transaction(function () use ($pairing): void {
                $locked = ApiSyncPairing::query()->lockForUpdate()->findOrFail($pairing->id);
                if ($locked->document_snapshot_status !== self::STATUS_PURGE_PENDING
                    || ! hash_equals((string) $locked->snapshot_id, (string) $pairing->snapshot_id)) {
                    throw new RuntimeException('Document snapshot cleanup lost its compare-and-set precondition.');
                }
                ApiSyncSnapshotDocument::query()
                    ->where('pairing_id', $locked->id)
                    ->where('snapshot_id', $locked->snapshot_id)
                    ->whereIn('state', [
                        ApiSyncSnapshotDocument::STATE_PREPARING,
                        ApiSyncSnapshotDocument::STATE_READY,
                        ApiSyncSnapshotDocument::STATE_HELD,
                    ])
                    ->update([
                        'state' => ApiSyncSnapshotDocument::STATE_PURGED,
                        'storage_disk' => null,
                        'storage_path' => null,
                        'purged_at' => now(),
                        'updated_at' => now(),
                    ]);
                $locked->forceFill([
                    'document_snapshot_status' => self::STATUS_PURGED,
                    'document_snapshot_purged_at' => now(),
                    'document_snapshot_bytes' => 0,
                ])->save();
            }, 3);
            $this->audit->record($pairing->fresh(), 'sync_document_snapshot_purged', 'Closed synchronization document bytes were removed from private snapshot storage.');

            return true;
        } finally {
            $mutex->release();
        }
    }

    public function markFailed(string $pairingId, string $reason = 'document_snapshot_failed'): void
    {
        $safeReason = $this->safeText($reason, 255, 'document_snapshot_failed');
        $mutex = Cache::lock('api-sync-document-snapshot-build:'.$pairingId, 300);
        if (! $mutex->get()) {
            // Make the failure visible even when lifecycle cleanup temporarily
            // owns the mutex. The one-minute maintainer will then purge it.
            ApiSyncPairing::query()
                ->whereKey($pairingId)
                ->whereIn('document_snapshot_status', [self::STATUS_PENDING, self::STATUS_BUILDING])
                ->update([
                    'document_snapshot_status' => self::STATUS_FAILED,
                    'document_snapshot_failure_reason' => $safeReason,
                ]);

            return;
        }

        try {
            $pairing = DB::transaction(function () use ($pairingId, $safeReason): ?ApiSyncPairing {
                $locked = ApiSyncPairing::query()->lockForUpdate()->find($pairingId);
                if (! $locked
                    || ! $locked->snapshot_id
                    || in_array($locked->document_snapshot_status, [
                        self::STATUS_NOT_REQUESTED,
                        self::STATUS_READY,
                        self::STATUS_PURGE_PENDING,
                        self::STATUS_PURGED,
                    ], true)) {
                    return null;
                }

                // Publish the failed parent state first so PostgreSQL's
                // immutable-row trigger permits ready -> held only here.
                $locked->forceFill([
                    'document_snapshot_status' => self::STATUS_FAILED,
                    'document_snapshot_failure_reason' => $safeReason,
                    'document_ready_count' => 0,
                    'document_held_count' => DB::raw('document_discovered_count'),
                    'document_snapshot_bytes' => 0,
                ])->save();
                ApiSyncSnapshotDocument::query()
                    ->where('pairing_id', $locked->id)
                    ->whereIn('state', [
                        ApiSyncSnapshotDocument::STATE_PREPARING,
                        ApiSyncSnapshotDocument::STATE_READY,
                    ])
                    ->update([
                        'state' => ApiSyncSnapshotDocument::STATE_HELD,
                        'storage_disk' => null,
                        'storage_path' => null,
                        'hold_code' => 'snapshot_build_failed',
                        'hold_message' => 'The document snapshot could not be completed safely.',
                        'updated_at' => now(),
                    ]);

                return $locked->fresh();
            }, 3);
            if (! $pairing) {
                return;
            }

            try {
                $this->policy->purgeSnapshot((string) $pairing->snapshot_id);
            } catch (Throwable) {
                // STATUS_FAILED is selected by the one-minute maintainer, so
                // private bytes are retried until the purge completes.
            }
            $this->audit->record($pairing, 'sync_document_snapshot_failed', 'ATTP could not safely complete the approved-document snapshot.', ['failure_code' => $safeReason]);
        } finally {
            $mutex->release();
        }
    }

    /** @param array<string,mixed>|null $candidate */
    private function hold(ApiSyncPairing $pairing, ApiSyncSnapshotDocument $document, ?array $candidate, string $code, string $message): void
    {
        $code = $this->safeCode($code);
        $message = $this->safeText($message, 500, 'The approved document was held for review.');
        DB::transaction(function () use ($pairing, $document, $candidate, $code, $message): void {
            $document->forceFill([
                'state' => ApiSyncSnapshotDocument::STATE_HELD,
                'storage_disk' => null,
                'storage_path' => null,
                'hold_code' => $code,
                'hold_message' => $message,
            ])->save();
            $this->issue($pairing, $document, $candidate, $code, $message);
        }, 3);
    }

    /**
     * Revalidate local session authority and the exact source approval under
     * row locks, then publish preparing -> ready in that same transaction.
     * Files staged on disk remain unreachable until this commit succeeds.
     *
     * @param  array<string,mixed>  $candidate
     * @param  array{storage_disk:string,storage_path:string,display_filename:string,detected_mime:string,byte_size:int,sha256:string}  $staged
     */
    private function commitStagedDocument(
        ApiSyncPairing $pairing,
        ApiSyncSnapshotDocument $document,
        array $candidate,
        array $staged,
    ): string {
        return DB::transaction(function () use ($pairing, $document, $candidate, $staged): string {
            // Match the v2 lifecycle's global invitation -> pairing -> child
            // lock order so a concurrent revoke/expiry cannot deadlock here.
            $invitation = filled($pairing->inbound_invitation_id)
                ? ApiSyncInvitation::query()->lockForUpdate()->find($pairing->inbound_invitation_id)
                : null;
            $lockedPairing = ApiSyncPairing::query()->lockForUpdate()->find($pairing->id);
            $lockedDocument = ApiSyncSnapshotDocument::query()->lockForUpdate()->find($document->id);
            if (! $invitation
                || ! $lockedPairing
                || ! $lockedDocument
                || ! hash_equals((string) $invitation->id, (string) $lockedPairing->inbound_invitation_id)
                || $invitation->status !== ApiSyncInvitation::STATUS_ACTIVE
                || ! $this->isRequested($invitation)
                || $lockedPairing->status !== ApiSyncPairing::STATUS_CLAIMED
                || $lockedPairing->snapshot_status !== ApiSyncSnapshotService::STATUS_READY
                || $lockedPairing->document_snapshot_status !== self::STATUS_BUILDING
                || ! hash_equals((string) $lockedPairing->snapshot_id, (string) $pairing->snapshot_id)
                || $lockedPairing->token_expires_at?->isPast()) {
                return 'session_closed';
            }
            if ($lockedDocument->state !== ApiSyncSnapshotDocument::STATE_PREPARING
                || ! hash_equals((string) $lockedDocument->pairing_id, (string) $lockedPairing->id)
                || ! hash_equals((string) $lockedDocument->snapshot_id, (string) $lockedPairing->snapshot_id)
                || ! hash_equals((string) $lockedDocument->source_revision, (string) ($candidate['revision'] ?? ''))) {
                return 'document_changed';
            }
            if (! $this->source->stillApprovedForUpdate($candidate)) {
                return 'source_changed';
            }

            $lockedDocument->forceFill([
                ...$staged,
                'state' => ApiSyncSnapshotDocument::STATE_READY,
                'hold_code' => null,
                'hold_message' => null,
                'copied_at' => now(),
            ])->save();

            return 'committed';
        }, 3);
    }

    /** @param array<string,mixed>|null $candidate @param array<string,mixed> $context */
    private function issue(
        ApiSyncPairing $pairing,
        ?ApiSyncSnapshotDocument $document,
        ?array $candidate,
        string $code,
        string $message,
        array $context = [],
    ): void {
        ApiSyncSnapshotDocumentIssue::query()->create([
            'pairing_id' => $pairing->id,
            'document_id' => $document?->id,
            'snapshot_id' => $pairing->snapshot_id,
            'source_type' => $candidate['source_type'] ?? null,
            'source_document_id' => $candidate['source_document_id'] ?? null,
            'source_version_id' => $candidate['source_version_id'] ?? null,
            'code' => $this->safeCode($code),
            'message' => $this->safeText($message, 500, 'The approved document was held for review.'),
            'context' => $context ?: null,
        ]);
    }

    /** @return array<string,mixed> */
    private function inventoryRow(ApiSyncSnapshotDocument $document): array
    {
        $ready = $document->state === ApiSyncSnapshotDocument::STATE_READY;

        return [
            'id' => (string) $document->source_document_id,
            'source_version_id' => $document->source_version_id ? (string) $document->source_version_id : null,
            'transfer_id' => (string) $document->id,
            'source_type' => (string) $document->source_type,
            'category' => (string) $document->category,
            'classification' => 'restricted',
            'title' => (string) $document->title,
            'filename' => $document->display_filename,
            'mime_type' => $ready ? $document->detected_mime : null,
            'bytes' => $ready ? (int) $document->byte_size : null,
            'sha256' => $ready ? (string) $document->sha256 : null,
            'etag' => $ready ? '"'.strtolower((string) $document->sha256).'"' : null,
            'state' => (string) $document->state,
            'hold' => $ready ? null : [
                'code' => (string) $document->hold_code,
                'message' => (string) $document->hold_message,
            ],
            'relationships' => [
                'portfolio_id' => $document->portfolio_external_id ? (string) $document->portfolio_external_id : null,
                'project_ids' => array_values((array) $document->project_external_ids),
            ],
            'parent' => [
                'type' => $document->parent_type,
                'id' => $document->parent_external_id ? (string) $document->parent_external_id : null,
            ],
            'updated_at' => $this->dateTime($document->source_updated_at),
            'copied_at' => $this->dateTime($document->copied_at),
        ];
    }

    private function isRequested(ApiSyncInvitation $invitation): bool
    {
        $scopes = (array) $invitation->requested_scopes;

        return (bool) config('api_sync_documents.enabled', true)
            && in_array((string) config('api_sync_documents.metadata_scope', 'documents.metadata.read'), $scopes, true)
            && in_array((string) config('api_sync_documents.content_scope', 'documents.content.read'), $scopes, true);
    }

    private function assertSnapshot(ApiSyncPairing $pairing, string $snapshotId): void
    {
        if (! hash_equals((string) $pairing->snapshot_id, $snapshotId)) {
            throw new ApiSyncException('snapshot_mismatch', 'The document snapshot does not belong to this synchronization session.', 409);
        }
    }

    private function assertReady(ApiSyncPairing $pairing): void
    {
        if (in_array($pairing->document_snapshot_status, [self::STATUS_PENDING, self::STATUS_BUILDING], true)) {
            throw new ApiSyncException('document_snapshot_building', 'The immutable approved-document snapshot is still being prepared.', 425, ['Retry-After' => '5']);
        }
        if ($pairing->document_snapshot_status === self::STATUS_PURGED) {
            throw new ApiSyncException('document_snapshot_purged', 'The immutable approved-document snapshot has expired and was removed.', 410);
        }
        if ($pairing->document_snapshot_status !== self::STATUS_READY) {
            throw $this->unavailable();
        }
    }

    private function unavailable(): ApiSyncException
    {
        return new ApiSyncException('document_snapshot_unavailable', 'The immutable approved-document snapshot is unavailable.', 409);
    }

    private function safeCode(string $code): string
    {
        $code = strtolower((string) preg_replace('/[^a-zA-Z0-9_]+/', '_', $code));

        return mb_substr(trim($code, '_'), 0, 80) ?: 'document_held';
    }

    private function safeText(string $text, int $limit, string $fallback): string
    {
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim($text)) ?? '';
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return mb_substr($text !== '' ? $text : $fallback, 0, $limit);
    }

    private function dateTime(mixed $value): ?string
    {
        return $value ? CarbonImmutable::parse($value)->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }
}
