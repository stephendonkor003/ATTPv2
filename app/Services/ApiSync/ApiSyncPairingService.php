<?php

namespace App\Services\ApiSync;

use App\Exceptions\ApiSyncException;
use App\Jobs\BuildApiSyncDocumentSnapshot;
use App\Jobs\BuildApiSyncSnapshot;
use App\Models\ApiSyncInvitation;
use App\Models\ApiSyncPairing;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ApiSyncPairingService
{
    public function __construct(
        private readonly ApiSyncAuditService $audit,
        private readonly ApiSyncInvitationAuditService $invitationAudit,
        private readonly ApiSyncSnapshotService $snapshots,
        private readonly ApiSyncDocumentSnapshotService $documents,
    ) {}

    /**
     * @return array{pairing: ApiSyncPairing, code: string}
     */
    public function generate(User $user, Request $request): array
    {
        $this->ensureEnabled();
        $this->ensureLegacyV1Enabled();

        return DB::transaction(function () use ($user, $request): array {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $stale = ApiSyncPairing::query()
                ->where('created_by', $user->id)
                ->where('status', ApiSyncPairing::STATUS_PENDING)
                ->lockForUpdate()
                ->get();

            foreach ($stale as $previous) {
                $previous->forceFill([
                    'status' => ApiSyncPairing::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'revoked_by' => $user->id,
                    'revoke_reason' => 'Replaced by a newly generated pairing code.',
                ])->save();
                $this->audit->record(
                    $previous,
                    'pairing_code_replaced',
                    'A previous unused API synchronization code was safely replaced.',
                    actor: $user,
                    request: $request,
                );
            }

            for ($attempt = 0; $attempt < 20; $attempt++) {
                $code = (string) random_int(1_000_000, 9_999_999);
                $hash = $this->codeHash($code);

                if (ApiSyncPairing::query()->where('code_hash', $hash)->exists()) {
                    continue;
                }

                $pairing = ApiSyncPairing::query()->create([
                    'code_hash' => $hash,
                    'status' => ApiSyncPairing::STATUS_PENDING,
                    'created_by' => $user->id,
                    'code_expires_at' => now()->addMinutes((int) config('api_sync.pairing_ttl_minutes', 10)),
                ]);

                $this->audit->record(
                    $pairing,
                    'pairing_code_generated',
                    'A single-use API synchronization code was generated.',
                    ['expires_at' => $pairing->code_expires_at?->toIso8601String()],
                    actor: $user,
                    request: $request,
                );

                return ['pairing' => $pairing, 'code' => $code];
            }

            throw new ApiSyncException('pairing_generation_failed', 'A unique pairing code could not be generated. Please try again.', 503);
        }, 3);
    }

    /**
     * @return array{pairing: ApiSyncPairing, access_token: string}
     */
    public function claim(
        #[\SensitiveParameter] string $code,
        string $consumerInstance,
        string $consumerName,
        string $idempotencyKey,
        #[\SensitiveParameter] string $recoveryKey,
        Request $request,
    ): array {
        $this->ensureEnabled();
        $this->ensureLegacyV1Enabled();
        $this->ensureSecureTransport($request);
        $this->ensureAsyncQueue();
        $idempotencyHash = hash('sha256', strtolower($idempotencyKey));
        $recoveryHash = hash('sha256', $recoveryKey);

        $replayed = ApiSyncPairing::query()->where('claim_idempotency_hash', $idempotencyHash)->first();
        if ($replayed) {
            $this->audit->record(
                $replayed,
                'pairing_claim_replay_rejected',
                'A repeated pairing claim was rejected without issuing another credential.',
                ['consumer_instance' => $consumerInstance],
                request: $request,
                statusCode: 409,
            );
            throw new ApiSyncException(
                'pairing_claim_already_processed',
                'This pairing claim was already processed. After an ambiguous network failure, abandon it with the original recovery key before generating a new code.',
                409,
            );
        }

        $reusedRecovery = ApiSyncPairing::query()->where('claim_recovery_hash', $recoveryHash)->first();
        if ($reusedRecovery) {
            $this->audit->record(
                $reusedRecovery,
                'pairing_claim_recovery_rejected',
                'A pairing claim was rejected because its one-time recovery key had already been used.',
                ['consumer_instance' => $consumerInstance],
                request: $request,
                statusCode: 422,
            );
            throw new ApiSyncException(
                'claim_recovery_key_unavailable',
                'The claim recovery key is unavailable. Generate a new high-entropy recovery key and claim again.',
                422,
            );
        }

        $token = $this->newToken();
        $result = null;

        try {
            $result = DB::transaction(function () use (
                $code,
                $consumerInstance,
                $consumerName,
                $idempotencyHash,
                $recoveryHash,
                $token,
            ): array {
                $pairing = ApiSyncPairing::query()
                    ->where('code_hash', $this->codeHash($code))
                    ->lockForUpdate()
                    ->first();

                if (! $pairing || $pairing->status !== ApiSyncPairing::STATUS_PENDING) {
                    return ['failure' => 'unavailable', 'pairing' => $pairing];
                }

                if ($pairing->code_expires_at?->isPast()) {
                    $pairing->forceFill([
                        'status' => ApiSyncPairing::STATUS_EXPIRED,
                        'revoke_reason' => 'The single-use pairing code expired before it was claimed.',
                    ])->save();

                    return ['failure' => 'expired', 'pairing' => $pairing];
                }

                $capacityLock = DB::table('api_sync_snapshot_capacity_locks')
                    ->where('scope', 'provider')
                    ->lockForUpdate()
                    ->first();
                if (! $capacityLock) {
                    throw new \RuntimeException('The API synchronization capacity lock is not initialized.');
                }
                $maximumActive = min(10, max(1, (int) config('api_sync.snapshot.maximum_active_sessions', 2)));
                $active = ApiSyncPairing::query()
                    ->whereNotNull('snapshot_id')
                    ->whereNull('snapshot_purged_at')
                    ->count();
                if ($active >= $maximumActive) {
                    return ['failure' => 'capacity', 'pairing' => $pairing];
                }

                $now = now();
                $pairing->forceFill([
                    'status' => ApiSyncPairing::STATUS_CLAIMED,
                    'claimed_at' => $now,
                    'claim_idempotency_hash' => $idempotencyHash,
                    'claim_recovery_hash' => $recoveryHash,
                    'consumer_instance' => $consumerInstance,
                    'consumer_name' => $consumerName,
                    'snapshot_id' => (string) Str::uuid(),
                    'snapshot_at' => $now,
                    'token_hash' => hash('sha256', $token),
                    'token_expires_at' => $now->copy()->addMinutes(
                        min(1_440, max(30, (int) config('api_sync.session_ttl_minutes', 360))),
                    ),
                ])->save();
                $this->snapshots->initialize($pairing);

                return ['pairing' => $pairing->fresh()];
            }, 3);
        } catch (QueryException $exception) {
            if (ApiSyncPairing::query()->where('claim_idempotency_hash', $idempotencyHash)->exists()) {
                throw new ApiSyncException(
                    'pairing_claim_already_processed',
                    'This pairing claim was already processed. Abandon it with the original recovery key before generating a new code.',
                    409,
                );
            }

            if (ApiSyncPairing::query()->where('claim_recovery_hash', $recoveryHash)->exists()) {
                throw new ApiSyncException(
                    'claim_recovery_key_unavailable',
                    'The claim recovery key is unavailable. Generate a new high-entropy recovery key and claim again.',
                    422,
                );
            }

            throw $exception;
        }

        /** @var ApiSyncPairing|null $pairing */
        $pairing = $result['pairing'] ?? null;
        if (isset($result['failure'])) {
            if ($result['failure'] === 'capacity') {
                $this->audit->record(
                    $pairing,
                    'pairing_claim_capacity_rejected',
                    'A pairing claim was deferred because the provider reached its bounded active snapshot capacity.',
                    request: $request,
                    statusCode: 409,
                );
                throw new ApiSyncException(
                    'snapshot_capacity_unavailable',
                    'This provider has reached its active synchronization capacity. Retry after an existing session closes.',
                    409,
                    ['Retry-After' => '60'],
                );
            }

            $this->audit->record(
                $pairing,
                $result['failure'] === 'expired' ? 'pairing_code_expired' : 'pairing_claim_rejected',
                $result['failure'] === 'expired'
                    ? 'An expired API synchronization code was rejected.'
                    : 'An invalid, used, or unavailable API synchronization code was rejected.',
                ['code_fingerprint' => mb_substr($this->codeHash($code), 0, 12)],
                request: $request,
                statusCode: 422,
            );
            throw new ApiSyncException('invalid_pairing_code', 'The pairing code is invalid, expired, or has already been used.', 422);
        }

        $this->audit->record(
            $pairing,
            'pairing_claimed',
            'AU-PReMIS successfully claimed the pairing code and opened a restricted synchronization session.',
            [
                'consumer_instance' => $consumerInstance,
                'consumer_name' => $consumerName,
                'token_expires_at' => $pairing->token_expires_at?->toIso8601String(),
                'snapshot_status' => $pairing->snapshot_status,
            ],
            request: $request,
        );

        try {
            $this->snapshots->dispatch($pairing);
        } catch (Throwable) {
            $this->audit->record(
                $pairing,
                'sync_snapshot_dispatch_deferred',
                'The immutable synchronization snapshot was queued for scheduler recovery after its initial dispatch was deferred.',
                request: $request,
            );
        }

        return ['pairing' => $pairing, 'access_token' => $token];
    }

    public function abandon(
        #[\SensitiveParameter] string $recoveryKey,
        string $consumerInstance,
        ?string $idempotencyKey,
        Request $request,
    ): void {
        $this->ensureEnabled();
        $this->ensureLegacyV1Enabled();
        $this->ensureSecureTransport($request);
        $recoveryHash = hash('sha256', $recoveryKey);

        $result = DB::transaction(function () use (
            $recoveryHash,
            $consumerInstance,
            $idempotencyKey,
            $request,
        ): array {
            $pairing = ApiSyncPairing::query()
                ->where('claim_recovery_hash', $recoveryHash)
                ->lockForUpdate()
                ->first();

            $hashMatches = $pairing !== null
                && hash_equals((string) $pairing->claim_recovery_hash, $recoveryHash);
            $consumerMatches = $pairing !== null
                && hash_equals((string) $pairing->consumer_instance, $consumerInstance);

            if (! $hashMatches || ! $consumerMatches) {
                return ['failure' => true, 'pairing' => $pairing];
            }

            if ($pairing->status === ApiSyncPairing::STATUS_ABANDONED) {
                $this->audit->record(
                    $pairing,
                    'sync_session_abandonment_replayed',
                    'AU-PReMIS repeated a valid request to abandon an already closed synchronization session.',
                    [
                        'consumer_instance' => $consumerInstance,
                        'idempotency_key_supplied' => filled($idempotencyKey),
                    ],
                    request: $request,
                );

                return ['success' => true];
            }

            if ($pairing->status !== ApiSyncPairing::STATUS_CLAIMED) {
                return ['failure' => true, 'pairing' => $pairing];
            }

            $now = now();
            $pairing->forceFill([
                'status' => ApiSyncPairing::STATUS_ABANDONED,
                'abandoned_at' => $now,
                'revoked_at' => $now,
                'token_hash' => null,
                'revoke_reason' => 'AU-PReMIS abandoned an ambiguous pairing claim using its recovery key.',
            ])->save();
            $this->snapshots->requestPurge($pairing);
            $this->documents->requestPurge($pairing);

            $this->audit->record(
                $pairing,
                'sync_session_abandoned',
                'AU-PReMIS safely abandoned an ambiguous pairing claim and revoked its credential.',
                [
                    'consumer_instance' => $consumerInstance,
                    'idempotency_key_supplied' => filled($idempotencyKey),
                ],
                request: $request,
            );

            return ['success' => true];
        }, 3);

        if (isset($result['failure'])) {
            $this->audit->record(
                $result['pairing'] ?? null,
                'sync_session_abandonment_rejected',
                'A request to abandon an ambiguous pairing claim was rejected.',
                ['consumer_instance' => $consumerInstance],
                request: $request,
                statusCode: 409,
            );
            throw new ApiSyncException(
                'pairing_abandonment_unavailable',
                'Pairing closure could not be confirmed. Retain the recovery credentials and retry the abandonment request.',
                409,
            );
        }
    }

    public function authenticate(Request $request): ApiSyncPairing
    {
        $this->ensureEnabled();
        $this->ensureLegacyV1Enabled();
        $this->ensureSecureTransport($request);
        $token = (string) $request->bearerToken();
        $consumerInstance = trim((string) $request->header('X-Consumer-Instance'));

        if ($token === '' || strlen($token) > 160 || ! str_starts_with($token, 'attp_sync_')) {
            throw new ApiSyncException('invalid_sync_credential', 'A valid synchronization credential is required.', 401);
        }

        $pairing = ApiSyncPairing::query()->where('token_hash', hash('sha256', $token))->first();
        if (! $pairing || ! hash_equals((string) $pairing->token_hash, hash('sha256', $token))) {
            throw new ApiSyncException('invalid_sync_credential', 'A valid synchronization credential is required.', 401);
        }

        if ($consumerInstance === '' || ! hash_equals((string) $pairing->consumer_instance, $consumerInstance)) {
            $this->audit->record(
                $pairing,
                'sync_instance_mismatch',
                'A synchronization request was rejected because its consumer instance did not match the pairing.',
                request: $request,
                statusCode: 401,
            );
            throw new ApiSyncException('consumer_instance_mismatch', 'The synchronization credential is not valid for this consumer instance.', 401);
        }

        if (! $pairing->isUsable()) {
            if ($pairing->status === ApiSyncPairing::STATUS_CLAIMED && $pairing->token_expires_at?->isPast()) {
                DB::transaction(function () use ($pairing, $request): void {
                    $locked = ApiSyncPairing::query()->lockForUpdate()->find($pairing->id);
                    if ($locked?->status === ApiSyncPairing::STATUS_CLAIMED && $locked->token_expires_at?->isPast()) {
                        $locked->forceFill([
                            'status' => ApiSyncPairing::STATUS_EXPIRED,
                            'token_hash' => null,
                            'revoke_reason' => 'The short-lived synchronization session expired.',
                        ])->save();
                        $this->snapshots->requestPurge($locked);
                        $this->documents->requestPurge($locked);
                        $this->audit->record(
                            $locked,
                            'sync_session_expired',
                            'The short-lived API synchronization session expired and its credential was revoked.',
                            request: $request,
                        );
                    }
                });
            }

            throw new ApiSyncException('sync_session_unavailable', 'The synchronization session has expired or been revoked.', 401);
        }

        ApiSyncPairing::query()->whereKey($pairing->id)->update([
            'last_used_at' => now(),
            'request_count' => DB::raw('request_count + 1'),
        ]);

        return $pairing;
    }

    public function complete(ApiSyncPairing $pairing, Request $request): void
    {
        $invitationId = $pairing->inbound_invitation_id;
        DB::transaction(function () use ($pairing, $request, $invitationId): void {
            // The v2 lifecycle always locks invitation -> pairing. Keeping one
            // order prevents completion from deadlocking local revoke/finalize.
            $invitation = filled($invitationId)
                ? ApiSyncInvitation::query()->lockForUpdate()->find($invitationId)
                : null;
            $locked = ApiSyncPairing::query()->lockForUpdate()->findOrFail($pairing->id);
            if ((string) ($locked->inbound_invitation_id ?? '') !== (string) ($invitationId ?? '')) {
                throw new ApiSyncException('pairing_binding_changed', 'The synchronization session binding changed unexpectedly.', 409);
            }
            if ($locked->status === ApiSyncPairing::STATUS_COMPLETED
                && is_string($locked->token_hash)
                && $locked->token_expires_at?->isFuture()) {
                if ($invitation && $invitation->status !== ApiSyncInvitation::STATUS_COMPLETED) {
                    $invitation->forceFill([
                        'status' => ApiSyncInvitation::STATUS_COMPLETED,
                        'completed_at' => $locked->completed_at ?? now(),
                    ])->save();
                }
                if ($invitation) {
                    $this->invitationAudit->recordOnce(
                        $invitation,
                        'invitation_transfer_completed',
                        'AU-PReMIS confirmed that the approved background data transfer completed.',
                        ['snapshot_id' => $locked->snapshot_id],
                        request: $request,
                    );
                }

                return;
            }
            if ($locked->status !== ApiSyncPairing::STATUS_CLAIMED) {
                throw new ApiSyncException('sync_session_unavailable', 'The synchronization session is no longer active.', 409);
            }
            $this->documents->assertCompletionReady($locked);

            $locked->forceFill([
                'status' => ApiSyncPairing::STATUS_COMPLETED,
                'completed_at' => now(),
                'revoke_reason' => 'AU-PReMIS confirmed that the synchronization completed.',
            ])->save();
            $invitation?->forceFill([
                'status' => ApiSyncInvitation::STATUS_COMPLETED,
                'completed_at' => $locked->completed_at,
            ])->save();
            $this->snapshots->requestPurge($locked);
            $this->documents->requestPurge($locked);
            $this->audit->record(
                $locked,
                'sync_session_completed',
                'AU-PReMIS completed the data synchronization and the session credential was revoked.',
                request: $request,
            );
            if ($invitation) {
                $this->invitationAudit->recordOnce(
                    $invitation,
                    'invitation_transfer_completed',
                    'AU-PReMIS confirmed that the approved background data transfer completed.',
                    ['snapshot_id' => $locked->snapshot_id],
                    request: $request,
                );
            }
        }, 3);
    }

    public function revoke(ApiSyncPairing $pairing, User $user, Request $request): void
    {
        $this->ensureEnabled();
        $this->ensureLegacyV1Enabled();

        DB::transaction(function () use ($pairing, $user, $request): void {
            $locked = ApiSyncPairing::query()->lockForUpdate()->findOrFail($pairing->id);
            if (in_array($locked->status, [ApiSyncPairing::STATUS_REVOKED, ApiSyncPairing::STATUS_COMPLETED, ApiSyncPairing::STATUS_ABANDONED], true)) {
                return;
            }

            $locked->forceFill([
                'status' => ApiSyncPairing::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by' => $user->id,
                'token_hash' => null,
                'revoke_reason' => 'Revoked by an authorized ATTP administrator.',
            ])->save();
            $this->snapshots->requestPurge($locked);
            $this->documents->requestPurge($locked);
            $this->audit->record(
                $locked,
                'sync_session_revoked',
                'An authorized administrator revoked the API synchronization session.',
                actor: $user,
                request: $request,
            );
        });
    }

    public function expireStale(int $limit = 500): int
    {
        $ids = ApiSyncPairing::query()
            ->where(function ($query): void {
                $query
                    ->where(function ($pending): void {
                        $pending->where('status', ApiSyncPairing::STATUS_PENDING)
                            ->where('code_expires_at', '<=', now());
                    })
                    ->orWhere(function ($claimed): void {
                        $claimed->where('status', ApiSyncPairing::STATUS_CLAIMED)
                            ->where('token_expires_at', '<=', now());
                    })
                    ->orWhere(function ($completed): void {
                        $completed->where('status', ApiSyncPairing::STATUS_COMPLETED)
                            ->whereNotNull('token_hash')
                            ->where('token_expires_at', '<=', now());
                    });
            })
            ->orderBy('id')
            ->limit(max(1, min($limit, 2_000)))
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            $changed = DB::transaction(function () use ($id): bool {
                $binding = ApiSyncPairing::query()->select(['id', 'inbound_invitation_id'])->find($id);
                if (! $binding) {
                    return false;
                }
                // Match the invitation -> pairing order used by activation,
                // finalization, local revoke, and remote completion.
                $invitation = filled($binding->inbound_invitation_id)
                    ? ApiSyncInvitation::query()->lockForUpdate()->find($binding->inbound_invitation_id)
                    : null;
                $pairing = ApiSyncPairing::query()->lockForUpdate()->find($id);
                if (! $pairing
                    || (string) ($pairing->inbound_invitation_id ?? '') !== (string) ($binding->inbound_invitation_id ?? '')) {
                    return false;
                }

                $pendingExpired = $pairing->status === ApiSyncPairing::STATUS_PENDING
                    && $pairing->code_expires_at?->isPast();
                $sessionExpired = $pairing->status === ApiSyncPairing::STATUS_CLAIMED
                    && $pairing->token_expires_at?->isPast();
                $completionRetryExpired = $pairing->status === ApiSyncPairing::STATUS_COMPLETED
                    && filled($pairing->token_hash)
                    && $pairing->token_expires_at?->isPast();
                if (! $pendingExpired && ! $sessionExpired && ! $completionRetryExpired) {
                    return false;
                }

                $pairing->forceFill([
                    'status' => $completionRetryExpired ? ApiSyncPairing::STATUS_COMPLETED : ApiSyncPairing::STATUS_EXPIRED,
                    'token_hash' => null,
                    'revoke_reason' => $completionRetryExpired
                        ? 'The bounded completion-retry credential expired.'
                        : ($pendingExpired
                            ? 'The single-use pairing code expired before it was claimed.'
                            : 'The short-lived synchronization session expired.'),
                ])->save();
                if ($sessionExpired && $invitation && in_array($invitation->status, [
                    ApiSyncInvitation::STATUS_ACTIVATION_RECEIVED,
                    ApiSyncInvitation::STATUS_ACTIVE,
                ], true)) {
                    $invitation->forceFill(['status' => ApiSyncInvitation::STATUS_EXPIRED])->save();
                }
                $this->snapshots->requestPurge($pairing);
                $this->documents->requestPurge($pairing);
                $this->audit->record(
                    $pairing,
                    $completionRetryExpired ? 'completion_retry_expired' : ($pendingExpired ? 'pairing_code_expired' : 'sync_session_expired'),
                    $completionRetryExpired
                        ? 'The bounded completion-retry credential digest expired and was destroyed.'
                        : ($pendingExpired
                            ? 'An unused API synchronization code reached its expiry time.'
                            : 'The short-lived API synchronization session expired and its credential was revoked.'),
                );

                return true;
            }, 3);

            $expired += $changed ? 1 : 0;
        }

        return $expired;
    }

    public function codeHash(#[\SensitiveParameter] string $code): string
    {
        return hash_hmac('sha256', $code, $this->key());
    }

    private function newToken(): string
    {
        return 'attp_sync_'.rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function key(): string
    {
        $key = (string) config('api_sync.pairing_pepper');
        if ($key === '') {
            throw new \RuntimeException('ATTP API Sync requires ATTP_API_SYNC_PAIRING_PEPPER or APP_KEY.');
        }

        return $key;
    }

    private function ensureEnabled(): void
    {
        if (! config('api_sync.enabled')) {
            throw new ApiSyncException('api_sync_disabled', 'API synchronization is disabled on this ATTP instance.', 503);
        }
    }

    private function ensureLegacyV1Enabled(): void
    {
        if (! (bool) config('api_sync.legacy_v1_enabled', false)) {
            throw new ApiSyncException(
                'legacy_api_sync_disabled',
                'This migration-only API synchronization flow is disabled. Use the signed AU-PReMIS invitation workflow.',
                404,
            );
        }
    }

    private function ensureSecureTransport(Request $request): void
    {
        if (config('api_sync.require_https') && app()->environment('production') && ! $request->isSecure()) {
            throw new ApiSyncException('https_required', 'API synchronization is available only over HTTPS.', 426);
        }
    }

    public function ensureAsyncQueue(bool $includeDocuments = false): void
    {
        $queues = [[
            'connection' => (string) config('api_sync.snapshot.connection', 'api_sync_database'),
            'minimum_retry_after' => max(BuildApiSyncSnapshot::TIMEOUT_SECONDS, BuildApiSyncSnapshot::MUTEX_SECONDS),
        ]];
        if ($includeDocuments) {
            $queues[] = [
                'connection' => (string) config('api_sync_documents.queue.connection', 'api_sync_database'),
                'minimum_retry_after' => max(BuildApiSyncDocumentSnapshot::TIMEOUT_SECONDS, BuildApiSyncDocumentSnapshot::MUTEX_SECONDS),
            ];
        }

        foreach ($queues as $queue) {
            $connection = $queue['connection'];
            $driver = (string) config("queue.connections.{$connection}.driver");
            if ($connection === '' || $driver !== 'database') {
                throw new ApiSyncException(
                    'snapshot_queue_unavailable',
                    'API synchronization requires the dedicated database queue for immutable snapshot preparation.',
                    503,
                );
            }
            $databaseConnection = trim((string) config('database.default'));
            $queueDatabase = trim((string) (config("queue.connections.{$connection}.connection") ?: $databaseConnection));
            if ($databaseConnection !== 'pgsql' || ! hash_equals($databaseConnection, $queueDatabase)) {
                throw new ApiSyncException(
                    'snapshot_queue_unavailable',
                    'The immutable snapshot queue must use the application PostgreSQL connection.',
                    503,
                );
            }

            $retryAfter = config("queue.connections.{$connection}.retry_after");
            if (! is_numeric($retryAfter) || (int) $retryAfter <= $queue['minimum_retry_after']) {
                throw new ApiSyncException(
                    'snapshot_queue_unavailable',
                    'The API synchronization queue retry window must exceed the snapshot job timeout and build-mutex lifetime.',
                    503,
                );
            }
        }

        $this->ensureSharedLockStore();

        $sessionSeconds = min(1_440, max(30, (int) config('api_sync.session_ttl_minutes', 360))) * 60;
        $maximumBuildSeconds = min(1_800, max(60, (int) config('api_sync.snapshot.maximum_build_seconds', 900)));
        if ($sessionSeconds < $maximumBuildSeconds + 900) {
            throw new ApiSyncException(
                'snapshot_configuration_invalid',
                'The synchronization session must allow the maximum snapshot build time plus at least 15 minutes for transfer.',
                503,
            );
        }
    }

    private function ensureSharedLockStore(): void
    {
        $store = trim((string) config('cache.default'));
        $driver = trim((string) config("cache.stores.{$store}.driver"));
        $databaseConnection = trim((string) config('database.default'));

        if ($store === '' || ! in_array($driver, ['database', 'redis'], true)) {
            throw new ApiSyncException(
                'snapshot_lock_store_unavailable',
                'API synchronization requires a shared PostgreSQL or Redis cache lock store.',
                503,
            );
        }

        if ($driver === 'database') {
            $cacheConnection = trim((string) (config("cache.stores.{$store}.connection") ?: $databaseConnection));
            $lockConnection = trim((string) (config("cache.stores.{$store}.lock_connection") ?: $cacheConnection));
            $cacheTable = trim((string) config("cache.stores.{$store}.table"));
            $lockTable = trim((string) (config("cache.stores.{$store}.lock_table") ?: 'cache_locks'));
            if ($databaseConnection !== 'pgsql'
                || ! hash_equals($databaseConnection, $cacheConnection)
                || ! hash_equals($databaseConnection, $lockConnection)
                || trim((string) config("database.connections.{$cacheConnection}.driver")) !== 'pgsql'
                || $cacheTable === ''
                || $lockTable === '') {
                throw new ApiSyncException(
                    'snapshot_lock_store_unavailable',
                    'The API synchronization lock store must use the shared application PostgreSQL connection and configured cache tables.',
                    503,
                );
            }

            return;
        }

        $redisConnection = trim((string) config("cache.stores.{$store}.connection"));
        $redisLockConnection = trim((string) (config("cache.stores.{$store}.lock_connection") ?: $redisConnection));
        if ($redisConnection === ''
            || $redisLockConnection === ''
            || ! is_array(config("database.redis.{$redisConnection}"))
            || ! is_array(config("database.redis.{$redisLockConnection}"))) {
            throw new ApiSyncException(
                'snapshot_lock_store_unavailable',
                'The API synchronization Redis lock store must use configured shared Redis connections.',
                503,
            );
        }
    }
}
