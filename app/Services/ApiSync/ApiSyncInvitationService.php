<?php

namespace App\Services\ApiSync;

use App\Exceptions\ApiSyncException;
use App\Models\ApiSyncInvitation;
use App\Models\ApiSyncPairing;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ApiSyncInvitationService
{
    public function __construct(
        private readonly ApiSyncV2SignatureService $signatures,
        private readonly ApiSyncV2EndpointGuard $endpoints,
        private readonly ApiSyncInvitationAuditService $audit,
        private readonly ApiSyncPairingService $pairings,
        private readonly ApiSyncSnapshotService $snapshots,
        private readonly ApiSyncDocumentSnapshotService $documents,
        private readonly ApiSyncDatasetService $datasets,
    ) {}

    /**
     * Receive a signed, central AU-PReMIS invitation. The seven-digit approval
     * code is deliberately absent from this protocol message.
     *
     * @param  array<string, mixed>  $payload
     * @return array{invitation: ApiSyncInvitation, created: bool}
     */
    public function receive(array $payload, Request $request): array
    {
        $this->ensureSecureRequest($request);
        $signature = $this->signatures->verifyRequest($request, $payload);
        $validated = $this->validateInvitation($payload);
        $invitationId = strtolower((string) $validated['invitation_id']);

        if (! hash_equals($invitationId, strtolower($signature['request_id']))) {
            throw new ApiSyncException('invitation_request_mismatch', 'The signed request ID does not match the invitation.', 422);
        }
        $configuredCentral = trim((string) config('api_sync.v2.central.instance_id'));
        if ($configuredCentral === '' || ! hash_equals($configuredCentral, (string) $validated['central_instance_id'])) {
            throw new ApiSyncException('central_instance_mismatch', 'The invitation was not issued by this deployment’s trusted AU-PReMIS instance.', 422);
        }

        $this->endpoints->assertInvitationOrigins(
            (string) $validated['central_origin'],
            (string) $validated['target_origin'],
            (string) $validated['confirmation_url'],
            $invitationId,
        );
        $existing = ApiSyncInvitation::query()->find($invitationId);
        if ($existing) {
            $sameRequest = hash_equals((string) $existing->invitation_payload_hash, $signature['payload_hash'])
                && hash_equals(strtolower((string) $existing->invitation_nonce), $signature['nonce'])
                && hash_equals((string) $existing->central_instance_id, (string) $validated['central_instance_id']);
            if (! $sameRequest) {
                $this->audit->record($existing, 'invitation_replay_rejected', 'A conflicting replay of an AU-PReMIS invitation was rejected.', request: $request, statusCode: 409);
                throw new ApiSyncException('invitation_replay_rejected', 'This invitation identifier has already been used.', 409);
            }

            $existing = DB::transaction(function () use ($existing, $request): ApiSyncInvitation {
                $locked = ApiSyncInvitation::query()->lockForUpdate()->findOrFail($existing->id);
                $this->audit->recordOnce(
                    $locked,
                    'invitation_received',
                    'A verified synchronization request was received from AU-PReMIS and is awaiting local approval.',
                    [
                        'central_name' => $locked->central_name,
                        'datasets' => $locked->requested_datasets,
                        'scopes' => $locked->requested_scopes,
                        'expires_at' => $locked->expires_at?->toIso8601String(),
                    ],
                    request: $request,
                    statusCode: 202,
                );

                return $locked;
            }, 3);

            return ['invitation' => $existing, 'created' => false];
        }
        $this->assertInvitationTimes((string) $validated['issued_at'], (string) $validated['expires_at'], $signature['timestamp']);

        try {
            $invitation = DB::transaction(function () use ($validated, $signature, $invitationId, $request): ApiSyncInvitation {
                $this->reserveNonce(
                    (string) $validated['central_instance_id'],
                    $signature['nonce'],
                    $signature['request_id'],
                    'invitation',
                    $signature['payload_hash'],
                    CarbonImmutable::parse((string) $validated['expires_at']),
                );

                $invitation = ApiSyncInvitation::query()->create([
                    'id' => $invitationId,
                    'protocol_version' => '2.0',
                    'central_instance_id' => $validated['central_instance_id'],
                    'central_name' => $validated['central_name'],
                    'central_origin' => $this->endpoints->origin((string) $validated['central_origin']),
                    'target_origin' => $this->endpoints->origin((string) $validated['target_origin']),
                    'confirmation_url' => $validated['confirmation_url'],
                    'requested_datasets' => array_values($validated['requested_datasets']),
                    'requested_scopes' => array_values($validated['requested_scopes']),
                    'credential_digest' => strtolower((string) $validated['credential_digest']),
                    'signature_key_id' => $signature['key_id'],
                    'invitation_nonce' => $signature['nonce'],
                    'invitation_payload_hash' => $signature['payload_hash'],
                    'status' => ApiSyncInvitation::STATUS_PENDING,
                    'issued_at' => CarbonImmutable::parse((string) $validated['issued_at']),
                    'expires_at' => CarbonImmutable::parse((string) $validated['expires_at']),
                    'credential_expires_at' => CarbonImmutable::parse((string) $validated['credential_expires_at']),
                    'received_at' => now(),
                ]);

                $this->audit->recordOnce(
                    $invitation,
                    'invitation_received',
                    'A verified synchronization request was received from AU-PReMIS and is awaiting local approval.',
                    [
                        'central_name' => $invitation->central_name,
                        'datasets' => $invitation->requested_datasets,
                        'scopes' => $invitation->requested_scopes,
                        'expires_at' => $invitation->expires_at?->toIso8601String(),
                    ],
                    request: $request,
                    statusCode: 202,
                );

                return $invitation;
            }, 3);
        } catch (QueryException $exception) {
            $duplicate = ApiSyncInvitation::query()->find($invitationId);
            $sameRequest = $duplicate
                && hash_equals((string) $duplicate->invitation_payload_hash, $signature['payload_hash'])
                && hash_equals(strtolower((string) $duplicate->invitation_nonce), $signature['nonce'])
                && hash_equals((string) $duplicate->central_instance_id, (string) $validated['central_instance_id']);
            if ($sameRequest) {
                $duplicate = DB::transaction(function () use ($duplicate, $request): ApiSyncInvitation {
                    $locked = ApiSyncInvitation::query()->lockForUpdate()->findOrFail($duplicate->id);
                    $this->audit->recordOnce(
                        $locked,
                        'invitation_received',
                        'A verified synchronization request was received from AU-PReMIS and is awaiting local approval.',
                        [
                            'central_name' => $locked->central_name,
                            'datasets' => $locked->requested_datasets,
                            'scopes' => $locked->requested_scopes,
                            'expires_at' => $locked->expires_at?->toIso8601String(),
                        ],
                        request: $request,
                        statusCode: 202,
                    );

                    return $locked;
                }, 3);

                return ['invitation' => $duplicate, 'created' => false];
            }
            if ($duplicate) {
                throw new ApiSyncException('signed_nonce_reused', 'This signed synchronization nonce or credential has already been used.', 409);
            }

            throw $exception;
        }

        return ['invitation' => $invitation, 'created' => true];
    }

    /**
     * Called by the trusted AU-PReMIS server while the local administrator's
     * confirmation request is in flight. Possession of the bearer credential
     * is the high-entropy activation proof; only its digest is stored here.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function activate(ApiSyncInvitation $invitation, array $payload, Request $request): array
    {
        $this->ensureSecureRequest($request);
        $signature = $this->signatures->verifyRequest($request, $payload);
        $validated = validator($payload, [
            'protocol_version' => ['required', 'in:2.0'],
            'invitation_id' => ['required', 'uuid'],
            'central_instance_id' => ['required', 'string', 'max:120'],
            'activated_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'nonce' => ['required', 'uuid'],
        ])->validate();
        $this->assertExactKeys($payload, ['protocol_version', 'invitation_id', 'central_instance_id', 'activated_at', 'nonce']);

        if (! hash_equals(strtolower((string) $invitation->id), strtolower((string) $validated['invitation_id']))
            || ! hash_equals((string) $invitation->central_instance_id, (string) $validated['central_instance_id'])
            || ! hash_equals(strtolower((string) $validated['nonce']), $signature['nonce'])
            || ! hash_equals(strtolower((string) $invitation->id), $signature['request_id'])) {
            throw new ApiSyncException('activation_binding_mismatch', 'The activation proof is not bound to this invitation.', 422);
        }
        $activatedAt = CarbonImmutable::parse((string) $validated['activated_at']);
        if (abs(now()->diffInSeconds($activatedAt, false)) > (int) config('api_sync.v2.maximum_clock_skew_seconds', 300)) {
            throw new ApiSyncException('stale_activation', 'The activation proof is outside the allowed clock window.', 401);
        }

        $bearer = trim((string) $request->bearerToken());
        if ($bearer === '' || strlen($bearer) < 43 || strlen($bearer) > 160 || ! preg_match('/^[A-Za-z0-9_-]+$/', $bearer)) {
            throw new ApiSyncException('invalid_sync_credential', 'A valid synchronization credential is required.', 401);
        }
        $digest = hash('sha256', $bearer);
        if (! hash_equals((string) $invitation->credential_digest, $digest)) {
            $this->audit->record($invitation, 'activation_credential_rejected', 'AU-PReMIS activation was rejected because its credential proof was invalid.', request: $request, statusCode: 401);
            throw new ApiSyncException('invalid_sync_credential', 'A valid synchronization credential is required.', 401);
        }
        $this->pairings->ensureAsyncQueue(in_array(
            (string) config('api_sync.v2.documents.metadata_scope', 'documents.metadata.read'),
            (array) $invitation->requested_scopes,
            true,
        ));

        $activation = DB::transaction(function () use ($invitation, $signature, $digest, $request): ApiSyncPairing|ApiSyncException {
            $locked = ApiSyncInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $existing = ApiSyncPairing::query()->where('inbound_invitation_id', $locked->id)->first();

            if ($locked->activation_request_id !== null) {
                $sameActivation = hash_equals(strtolower((string) $locked->activation_request_id), $signature['request_id'])
                    && hash_equals(strtolower((string) $locked->activation_nonce), $signature['nonce'])
                    && hash_equals((string) $locked->activation_payload_hash, $signature['payload_hash']);
                if ($sameActivation && $existing) {
                    if ($this->isLiveActivationPairing($locked, $existing, $digest)) {
                        return $existing;
                    }

                    throw new ApiSyncException('activation_session_terminal', 'The locally approved synchronization session is no longer active.', 409);
                }

                $recoverableActivation = $existing
                    && hash_equals(strtolower((string) $locked->activation_request_id), $signature['request_id'])
                    && $this->isLiveActivationPairing($locked, $existing, $digest);
                if ($recoverableActivation) {
                    if (DB::table('api_sync_v2_nonces')->where('nonce', $signature['nonce'])->exists()) {
                        throw new ApiSyncException('activation_replay_rejected', 'This activation proof has already been processed.', 409);
                    }
                    $this->reserveNonce(
                        (string) $locked->central_instance_id,
                        $signature['nonce'],
                        $signature['request_id'],
                        'activation',
                        $signature['payload_hash'],
                        now()->addDay(),
                    );
                    $locked->forceFill([
                        'activation_nonce' => $signature['nonce'],
                        'activation_payload_hash' => $signature['payload_hash'],
                    ])->save();
                    $this->audit->record(
                        $locked,
                        'activation_recovered',
                        'AU-PReMIS safely recovered an earlier activation whose response was not received.',
                        ['snapshot_id' => $existing->snapshot_id],
                        request: $request,
                    );

                    return $existing;
                }
                throw new ApiSyncException('activation_replay_rejected', 'This invitation has already received a different activation proof.', 409);
            }

            $signedAuthorizationStored = $locked->status === ApiSyncInvitation::STATUS_ACTIVATION_PENDING
                && $locked->authorization_verified_at !== null
                && is_array($locked->authorization_receipt);
            $approvalResponseWasLost = $locked->status === ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS
                && Str::isUuid((string) $locked->confirmation_request_id)
                && Str::isUuid((string) $locked->confirmation_request_nonce);
            if ($locked->credential_expires_at?->isPast()) {
                $locked->forceFill(['status' => ApiSyncInvitation::STATUS_EXPIRED])->save();
                $this->audit->record(
                    $locked,
                    'invitation_expired',
                    'The bounded background-activation credential expired before activation completed.',
                    ['reason' => 'activation_credential_expired'],
                    request: $request,
                    statusCode: 410,
                );

                return new ApiSyncException('activation_credential_expired', 'The bounded background-activation credential has expired.', 410);
            }
            if ($locked->expires_at?->isPast() && ! $signedAuthorizationStored && ! $approvalResponseWasLost) {
                $locked->forceFill(['status' => ApiSyncInvitation::STATUS_EXPIRED])->save();
                $this->audit->record(
                    $locked,
                    'invitation_expired',
                    'The synchronization request expired before local approval completed.',
                    request: $request,
                    statusCode: 410,
                );

                return new ApiSyncException('invitation_expired', 'This synchronization request expired before local approval completed.', 410);
            }
            if (blank($locked->approved_by) || (! $signedAuthorizationStored && ! $approvalResponseWasLost)) {
                throw new ApiSyncException('local_authorization_pending', 'The signed AU-PReMIS authorization must be stored locally before background activation.', 425, ['Retry-After' => '10']);
            }

            $capacityLock = DB::table('api_sync_snapshot_capacity_locks')->where('scope', 'provider')->lockForUpdate()->first();
            if (! $capacityLock) {
                throw new ApiSyncException('snapshot_capacity_unavailable', 'Snapshot capacity is not initialized on this ATTP instance.', 503);
            }
            $active = ApiSyncPairing::query()->whereNotNull('snapshot_id')->whereNull('snapshot_purged_at')->count();
            $maximum = (int) config('api_sync.snapshot.maximum_active_sessions', 2);
            if ($active >= $maximum) {
                throw new ApiSyncException('snapshot_capacity_unavailable', 'This ATTP instance is already preparing its maximum number of snapshots.', 409, ['Retry-After' => '60']);
            }

            $this->reserveNonce(
                (string) $locked->central_instance_id,
                $signature['nonce'],
                $signature['request_id'],
                'activation',
                $signature['payload_hash'],
                now()->addDay(),
            );

            $now = now();
            $pairing = ApiSyncPairing::query()->create([
                'inbound_invitation_id' => $locked->id,
                'code_hash' => hash('sha256', 'v2-invitation\0'.$locked->id.'\0'.bin2hex(random_bytes(32))),
                'status' => ApiSyncPairing::STATUS_CLAIMED,
                'created_by' => $locked->approved_by,
                'code_expires_at' => $locked->expires_at,
                'claimed_at' => $now,
                'claim_idempotency_hash' => hash('sha256', 'v2-activation\0'.$locked->id),
                'consumer_instance' => $locked->central_instance_id,
                'consumer_name' => $locked->central_name,
                'snapshot_id' => (string) Str::uuid(),
                'snapshot_at' => $now,
                'token_hash' => $digest,
                'token_expires_at' => $locked->credential_expires_at,
            ]);
            $this->snapshots->initialize($pairing);
            $this->documents->initialize($pairing, $locked);

            $locked->forceFill([
                'status' => ApiSyncInvitation::STATUS_ACTIVE,
                'approved_at' => $locked->approved_at ?? $now,
                'activation_received_at' => $now,
                'activation_request_id' => $signature['request_id'],
                'activation_nonce' => $signature['nonce'],
                'activation_payload_hash' => $signature['payload_hash'],
            ])->save();

            $this->audit->record(
                $locked,
                'invitation_activated',
                $approvalResponseWasLost
                    ? 'AU-PReMIS safely completed background activation after the local approval response was lost.'
                    : 'AU-PReMIS proved possession of the invitation credential and immutable snapshot preparation was authorized.',
                ['snapshot_id' => $pairing->snapshot_id, 'datasets' => $locked->requested_datasets, 'approval_response_recovered' => $approvalResponseWasLost],
                request: $request,
            );

            return $pairing->fresh();
        }, 3);

        if ($activation instanceof ApiSyncException) {
            throw $activation;
        }
        $pairing = $activation;

        try {
            $this->snapshots->dispatch($pairing);
        } catch (Throwable) {
            $this->audit->record(
                $invitation,
                'snapshot_dispatch_deferred',
                'Snapshot preparation will be recovered by the scheduler after its initial queue dispatch was deferred.',
                ['snapshot_id' => $pairing->snapshot_id],
                request: $request,
                statusCode: 202,
            );
        }

        return $this->activationDescriptor($invitation->fresh(), $pairing->fresh());
    }

    /**
     * Persist the central worker's final outcome after asynchronous activation.
     * A signed rejection immediately revokes the local bearer digest and marks
     * every immutable snapshot for purge; an accepted receipt binds the run.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: string, invitation_id: string, outcome: string}
     */
    public function finalize(ApiSyncInvitation $invitation, array $payload, Request $request): array
    {
        $this->ensureSecureRequest($request);
        $signature = $this->signatures->verifyRequest($request, $payload);
        $common = validator($payload, [
            'protocol_version' => ['required', 'in:2.0'],
            'invitation_id' => ['required', 'uuid'],
            'central_instance_id' => ['required', 'string', 'max:120'],
            'outcome' => ['required', Rule::in(['accepted', 'rejected'])],
            'finalized_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'receipt' => ['required_if:outcome,accepted', 'array'],
            'error_code' => ['required_if:outcome,rejected', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
        ])->validate();
        $expectedKeys = ['protocol_version', 'invitation_id', 'central_instance_id', 'outcome', 'finalized_at'];
        $expectedKeys[] = $common['outcome'] === 'accepted' ? 'receipt' : 'error_code';
        $this->assertExactKeys($payload, $expectedKeys);

        if (! hash_equals(strtolower((string) $invitation->id), strtolower((string) $common['invitation_id']))
            || ! hash_equals((string) $invitation->central_instance_id, (string) $common['central_instance_id'])
            || ! hash_equals(strtolower((string) $invitation->id), $signature['request_id'])) {
            throw new ApiSyncException('finalization_binding_mismatch', 'The signed finalization is not bound to this invitation.', 422);
        }
        if (abs(now()->diffInSeconds(CarbonImmutable::parse((string) $common['finalized_at']), false)) > (int) config('api_sync.v2.maximum_clock_skew_seconds', 300)) {
            throw new ApiSyncException('stale_finalization', 'The finalization timestamp is outside the allowed clock window.', 401);
        }

        $receipt = null;
        if ($common['outcome'] === 'accepted') {
            $receipt = $this->validateFinalReceipt((array) $common['receipt'], $invitation);
        }

        $finalized = DB::transaction(function () use ($invitation, $common, $receipt, $signature, $request): ApiSyncInvitation {
            $locked = ApiSyncInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            $pairing = ApiSyncPairing::query()->where('inbound_invitation_id', $locked->id)->lockForUpdate()->first();
            $seen = DB::table('api_sync_v2_nonces')->where('nonce', $signature['nonce'])->first();
            if ($seen !== null) {
                $same = hash_equals(strtolower((string) $seen->request_id), $signature['request_id'])
                    && hash_equals((string) $seen->purpose, 'finalization')
                    && hash_equals((string) $seen->payload_hash, $signature['payload_hash']);
                if (! $same || ! $this->finalizationAlreadyApplied($locked, (string) $common['outcome'], $receipt, (string) ($common['error_code'] ?? ''))) {
                    throw new ApiSyncException('finalization_replay_rejected', 'This finalization proof has already been used.', 409);
                }

                return $locked;
            }

            $this->reserveNonce(
                (string) $locked->central_instance_id,
                $signature['nonce'],
                $signature['request_id'],
                'finalization',
                $signature['payload_hash'],
                now()->addDay(),
            );

            if ($common['outcome'] === 'rejected') {
                $errorCode = (string) $common['error_code'];
                if ($pairing?->status === ApiSyncPairing::STATUS_CLAIMED) {
                    $pairing->forceFill([
                        'status' => ApiSyncPairing::STATUS_REVOKED,
                        'token_hash' => null,
                        'revoked_at' => now(),
                        'revoke_reason' => 'AU-PReMIS issued a signed terminal rejection after background activation.',
                    ])->save();
                    $this->snapshots->requestPurge($pairing);
                    $this->documents->requestPurge($pairing);
                }
                $locked->forceFill([
                    'status' => in_array($errorCode, ['invitation_expired', 'activation_credential_expired'], true)
                        ? ApiSyncInvitation::STATUS_EXPIRED
                        : ApiSyncInvitation::STATUS_FAILED,
                    'terminal_error_code' => $errorCode,
                    'revoke_reason' => 'AU-PReMIS securely rejected the asynchronous pairing.',
                ])->save();

                $finalized = $locked->fresh();
                $this->audit->record(
                    $finalized,
                    'invitation_terminally_rejected',
                    'A signed AU-PReMIS rejection revoked the local credential and scheduled the snapshot for removal.',
                    ['outcome' => $common['outcome'], 'run_id' => $finalized->central_run_id, 'error_code' => $finalized->terminal_error_code],
                    request: $request,
                );

                return $finalized;
            }

            if ($pairing === null || $pairing->status !== ApiSyncPairing::STATUS_CLAIMED || $locked->status !== ApiSyncInvitation::STATUS_ACTIVE) {
                throw new ApiSyncException('activation_proof_missing', 'A final receipt cannot be accepted before local credential activation.', 409);
            }
            if (abs(CarbonImmutable::parse((string) $receipt['credential_expires_at'])->diffInSeconds($pairing->token_expires_at, false)) > 5) {
                throw new ApiSyncException('confirmation_expiry_mismatch', 'The final receipt changed the activated credential expiry.', 422);
            }
            $locked->forceFill([
                'confirmation_id' => strtolower((string) $receipt['confirmation_id']),
                'central_run_id' => (string) $receipt['run_id'],
                'confirmation_receipt' => $receipt,
                'receipt_verified_at' => now(),
                'terminal_error_code' => null,
            ])->save();

            $finalized = $locked->fresh();
            $this->audit->record(
                $finalized,
                'invitation_finalized',
                'AU-PReMIS confirmed the background activation and bound the local snapshot to its queued transfer.',
                ['outcome' => $common['outcome'], 'run_id' => $finalized->central_run_id, 'error_code' => $finalized->terminal_error_code],
                request: $request,
            );

            return $finalized;
        }, 3);

        return ['status' => 'finalized', 'invitation_id' => (string) $finalized->id, 'outcome' => (string) $common['outcome']];
    }

    public function approve(
        ApiSyncInvitation $invitation,
        User $administrator,
        #[\SensitiveParameter] string $code,
        Request $request,
    ): ApiSyncInvitation {
        $attempt = DB::transaction(function () use ($invitation, $administrator, $request): array {
            $locked = ApiSyncInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if (in_array($locked->status, [ApiSyncInvitation::STATUS_ACTIVATION_PENDING, ApiSyncInvitation::STATUS_ACTIVE], true)) {
                return ['already_active' => true, 'invitation' => $locked];
            }
            $credentialExpired = in_array($locked->status, [
                ApiSyncInvitation::STATUS_PENDING,
                ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS,
                ApiSyncInvitation::STATUS_ACTIVATION_RECEIVED,
            ], true) && (! $locked->credential_expires_at || $locked->credential_expires_at->isPast());
            if ($credentialExpired) {
                if (in_array($locked->status, [
                    ApiSyncInvitation::STATUS_PENDING,
                    ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS,
                ], true)) {
                    $locked->forceFill(['status' => ApiSyncInvitation::STATUS_EXPIRED])->save();
                    $this->audit->record(
                        $locked,
                        'invitation_expired',
                        'The bounded synchronization credential expired before local approval completed.',
                        ['reason' => 'approval_credential_expired'],
                        $administrator,
                        $request,
                        410,
                    );
                }

                return ['failure' => new ApiSyncException(
                    'approval_credential_expired',
                    'This request can no longer be approved because its bounded synchronization credential has expired. Ask AU-PReMIS to send a new request.',
                    410,
                )];
            }
            $recovering = in_array($locked->status, [
                ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS,
                ApiSyncInvitation::STATUS_ACTIVATION_RECEIVED,
            ], true)
                && Str::isUuid((string) $locked->confirmation_request_id)
                && Str::isUuid((string) $locked->confirmation_request_nonce);

            if (! $recovering && $locked->expires_at?->isPast()) {
                $locked->forceFill(['status' => ApiSyncInvitation::STATUS_EXPIRED])->save();
                $this->audit->record(
                    $locked,
                    'invitation_expired',
                    'The synchronization request expired before local approval completed.',
                    actor: $administrator,
                    request: $request,
                    statusCode: 410,
                );

                return ['failure' => new ApiSyncException('invitation_expired', 'This synchronization request has expired. Ask AU-PReMIS to send a new request.', 410)];
            }
            if (! in_array($locked->status, [
                ApiSyncInvitation::STATUS_PENDING,
                ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS,
                ApiSyncInvitation::STATUS_ACTIVATION_RECEIVED,
            ], true)) {
                throw new ApiSyncException('invitation_unavailable', 'This synchronization request is no longer available for approval.', 409);
            }

            $maximum = (int) config('api_sync.v2.maximum_approval_attempts', 5);
            if (! $recovering && (int) $locked->approval_attempts >= $maximum) {
                $locked->forceFill(['status' => ApiSyncInvitation::STATUS_FAILED])->save();
                $this->audit->record(
                    $locked,
                    'approval_attempts_exhausted',
                    'The local approval attempt limit was reached and the synchronization request was closed.',
                    actor: $administrator,
                    request: $request,
                    statusCode: 429,
                );

                return ['failure' => new ApiSyncException('approval_attempts_exhausted', 'The approval attempt limit has been reached. Ask AU-PReMIS to create a new request.', 429)];
            }

            if ($recovering) {
                $requestId = strtolower((string) $locked->confirmation_request_id);
                $nonce = strtolower((string) $locked->confirmation_request_nonce);
                $locked->forceFill(['last_approval_attempt_at' => now()])->save();
            } else {
                $requestId = (string) Str::uuid();
                $nonce = (string) Str::uuid();
                $locked->forceFill([
                    'status' => ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS,
                    'approval_attempts' => (int) $locked->approval_attempts + 1,
                    'last_approval_attempt_at' => now(),
                    'confirmation_request_id' => $requestId,
                    'confirmation_request_nonce' => $nonce,
                    'approved_by' => $administrator->id,
                ])->save();
            }

            return [
                'already_active' => false,
                'invitation' => $locked->fresh(),
                'request_id' => $requestId,
                'nonce' => $nonce,
                'recovering' => $recovering,
            ];
        }, 3);

        if (($attempt['failure'] ?? null) instanceof ApiSyncException) {
            throw $attempt['failure'];
        }
        if ($attempt['already_active']) {
            return $attempt['invitation'];
        }

        /** @var ApiSyncInvitation $pending */
        $pending = $attempt['invitation'];
        $path = (string) parse_url((string) $pending->confirmation_url, PHP_URL_PATH);
        $confirmedAt = now()->utc()->format('Y-m-d\TH:i:sP');
        $body = [
            'protocol_version' => '2.0',
            'invitation_id' => (string) $pending->id,
            'remote_instance_id' => (string) $this->datasets->instance()['id'],
            'remote_name' => (string) $this->datasets->instance()['name'],
            'target_origin' => (string) $pending->target_origin,
            'code' => $code,
            'confirmed_at' => $confirmedAt,
            'nonce' => $attempt['nonce'],
        ];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->withHeaders([
                    'X-ATTP-Request-Id' => $attempt['request_id'],
                    'X-ATTP-Instance' => (string) $this->datasets->instance()['id'],
                ])
                ->withOptions($this->endpoints->httpOptions((string) $pending->confirmation_url))
                ->connectTimeout(min(10, (int) config('api_sync.v2.confirmation_timeout_seconds', 25)))
                ->timeout((int) config('api_sync.v2.confirmation_timeout_seconds', 25))
                ->post((string) $pending->confirmation_url, $body);
        } catch (ConnectionException) {
            $this->restorePendingAfterConfirmationFailure($pending, 'AU-PReMIS could not be reached. No approval receipt was accepted.');
            throw new ApiSyncException('central_confirmation_unavailable', 'AU-PReMIS could not be reached. You can safely retry this request.', 503);
        } catch (Throwable) {
            $this->restorePendingAfterConfirmationFailure($pending, 'The secure AU-PReMIS confirmation request could not be completed.');
            throw new ApiSyncException('central_confirmation_unavailable', 'The secure AU-PReMIS confirmation could not be completed. You can safely retry.', 503);
        } finally {
            $body['code'] = '';
            $code = '';
        }

        if (strlen($response->body()) > 65_536) {
            $this->restorePendingAfterConfirmationFailure($pending, 'The AU-PReMIS response exceeded the safe verification limit; the same approval attempt can be recovered.');
            throw new ApiSyncException('central_confirmation_ambiguous', 'The AU-PReMIS response could not be verified. You can safely retry the same approval.', 503);
        }
        $envelope = $response->json();
        if (! is_array($envelope)) {
            $this->restorePendingAfterConfirmationFailure($pending, 'The AU-PReMIS response was not valid JSON; the same approval attempt can be recovered.');
            throw new ApiSyncException('central_confirmation_ambiguous', 'The AU-PReMIS response could not be verified. You can safely retry the same approval.', 503);
        }

        try {
            $verified = $this->signatures->verifyResponse($response, $path, $envelope, $attempt['request_id']);
        } catch (Throwable) {
            $this->restorePendingAfterConfirmationFailure($pending, 'The AU-PReMIS response signature could not be verified; no broadened data access was granted.');
            throw new ApiSyncException('central_confirmation_ambiguous', 'The AU-PReMIS response could not be authenticated. You can safely retry the same approval.', 503);
        }

        if ($response->status() !== 202) {
            $errorCode = is_string(data_get($envelope, 'error.code')) ? (string) data_get($envelope, 'error.code') : 'central_confirmation_rejected';
            if ($this->isTerminalSignedRejection($pending, $errorCode, $response->status())) {
                $this->revokeAfterSignedCentralRejection($pending, $errorCode);
            } else {
                $this->resetAfterRecoverableSignedRejection($pending, $errorCode);
            }

            $message = match ($errorCode) {
                'invalid_confirmation_code' => 'The seven-digit code was not accepted. Check the code in AU-PReMIS and try again.',
                'invitation_temporarily_locked' => 'Too many incorrect attempts were made. Wait before trying again.',
                'source_transfer_active' => 'A transfer is already active for this source. Try again after it finishes.',
                'remote_instance_changed', 'remote_instance_duplicate', 'invitation_failed', 'invitation_revoked' => 'AU-PReMIS securely closed this request because its deployment binding is no longer valid.',
                'invitation_expired', 'activation_credential_expired' => 'This synchronization authorization expired. Ask AU-PReMIS to issue a new request.',
                default => 'AU-PReMIS did not authorize this attempt. You can retry if the request remains available.',
            };
            throw new ApiSyncException('central_confirmation_rejected', $message, min(503, max(409, $response->status())));
        }

        try {
            $this->assertExactKeys($envelope, ['data']);
            $receipt = is_array($envelope['data'] ?? null) ? $envelope['data'] : [];
            $this->assertExactKeys($receipt, [
                'protocol_version', 'status', 'invitation_id', 'authorization_id', 'authorized_at',
                'approved_datasets', 'approved_scopes', 'credential_expires_at',
            ]);
            $receipt = validator($receipt, [
                'protocol_version' => ['required', 'in:2.0'],
                'status' => ['required', 'in:activation_pending'],
                'invitation_id' => ['required', 'uuid'],
                'authorization_id' => ['required', 'uuid'],
                'authorized_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
                'approved_datasets' => ['required', 'array', 'min:1'],
                'approved_datasets.*' => ['string', Rule::in((array) $pending->requested_datasets)],
                'approved_scopes' => ['required', 'array', 'min:1'],
                'approved_scopes.*' => ['string', Rule::in((array) $pending->requested_scopes)],
                'credential_expires_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            ])->validate();
            if (! hash_equals(strtolower((string) $pending->id), strtolower((string) $receipt['invitation_id']))
                || array_values($receipt['approved_datasets']) !== array_values((array) $pending->requested_datasets)
                || array_values($receipt['approved_scopes']) !== array_values((array) $pending->requested_scopes)
                || abs(CarbonImmutable::parse((string) $receipt['credential_expires_at'])->diffInSeconds($pending->credential_expires_at, false)) > 5) {
                throw new ApiSyncException('authorization_receipt_mismatch', 'The AU-PReMIS authorization changed the invitation binding.', 502);
            }
        } catch (Throwable $exception) {
            $this->revokeAfterInvalidReceipt($pending, 'The signed AU-PReMIS authorization receipt failed contract validation.');
            if ($exception instanceof ApiSyncException) {
                throw $exception;
            }
            throw new ApiSyncException('authorization_receipt_invalid', 'The AU-PReMIS authorization receipt was invalid.', 502);
        }

        try {
            $authorized = DB::transaction(function () use ($pending, $receipt, $verified, $administrator, $request): ApiSyncInvitation {
                $locked = ApiSyncInvitation::query()->lockForUpdate()->findOrFail($pending->id);
                if ($locked->status === ApiSyncInvitation::STATUS_ACTIVATION_PENDING
                    && hash_equals((string) $locked->authorization_id, strtolower((string) $receipt['authorization_id']))) {
                    $this->audit->recordOnce(
                        $locked,
                        'invitation_authorized',
                        'The local administrator approved the request and a signed AU-PReMIS background-activation authorization was stored.',
                        ['authorization_id' => $locked->authorization_id, 'datasets' => $locked->requested_datasets],
                        actor: $administrator,
                        request: $request,
                    );

                    return $locked;
                }
                if (! in_array($locked->status, [ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS, ApiSyncInvitation::STATUS_ACTIVE], true)) {
                    throw new ApiSyncException('authorization_state_changed', 'The local invitation changed before authorization could be stored.', 409);
                }
                $this->reserveNonce(
                    (string) $locked->central_instance_id,
                    $verified['nonce'],
                    $verified['request_id'],
                    'authorization',
                    $verified['payload_hash'],
                    now()->addDay(),
                );
                $locked->forceFill([
                    'status' => $locked->status === ApiSyncInvitation::STATUS_ACTIVE
                        ? ApiSyncInvitation::STATUS_ACTIVE
                        : ApiSyncInvitation::STATUS_ACTIVATION_PENDING,
                    'approved_at' => now(),
                    'authorization_id' => strtolower((string) $receipt['authorization_id']),
                    'authorization_receipt' => $receipt,
                    'authorization_verified_at' => now(),
                ])->save();

                $this->audit->recordOnce(
                    $locked,
                    'invitation_authorized',
                    'The local administrator approved the request and a signed AU-PReMIS background-activation authorization was stored.',
                    ['authorization_id' => $locked->authorization_id, 'datasets' => $locked->requested_datasets],
                    actor: $administrator,
                    request: $request,
                );

                return $locked->fresh();
            }, 3);
        } catch (ApiSyncException $exception) {
            $this->revokeAfterInvalidReceipt($pending, 'The signed AU-PReMIS authorization could not be bound to the local request.');

            throw $exception;
        } catch (Throwable $exception) {
            // The signed authorization may already be durable at AU-PReMIS.
            // A local database/audit interruption is therefore ambiguous: the
            // same request/nonce must remain recoverable instead of being
            // terminalized or granted broader access.
            throw new ApiSyncException('authorization_persistence_unavailable', 'The signed authorization could not yet be stored locally. Retry the same approval to recover it safely.', 503);
        }

        return $authorized;
    }

    public function decline(ApiSyncInvitation $invitation, User $administrator, string $reason, Request $request): ApiSyncInvitation
    {
        $declined = DB::transaction(function () use ($invitation, $administrator, $reason, $request): ApiSyncInvitation {
            $locked = ApiSyncInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if (! in_array($locked->status, [ApiSyncInvitation::STATUS_PENDING, ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS], true)) {
                throw new ApiSyncException('invitation_unavailable', 'Only a pending synchronization request can be declined.', 409);
            }
            $locked->forceFill([
                'status' => ApiSyncInvitation::STATUS_DECLINED,
                'declined_at' => now(),
                'declined_by' => $administrator->id,
                'decline_reason' => trim($reason),
            ])->save();

            $declined = $locked->fresh();
            $this->audit->record($declined, 'invitation_declined', 'An authorized local administrator declined the AU-PReMIS synchronization request.', ['reason' => $declined->decline_reason], $administrator, $request);

            return $declined;
        }, 3);

        return $declined;
    }

    public function revoke(ApiSyncInvitation $invitation, User $administrator, string $reason, Request $request): ApiSyncInvitation
    {
        $revoked = DB::transaction(function () use ($invitation, $administrator, $reason, $request): ApiSyncInvitation {
            $locked = ApiSyncInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            if (! in_array($locked->status, [ApiSyncInvitation::STATUS_ACTIVATION_RECEIVED, ApiSyncInvitation::STATUS_ACTIVE], true)) {
                throw new ApiSyncException('invitation_unavailable', 'Only an active synchronization request can be revoked.', 409);
            }
            $pairing = ApiSyncPairing::query()->where('inbound_invitation_id', $locked->id)->lockForUpdate()->first();
            if ($pairing && $pairing->status === ApiSyncPairing::STATUS_CLAIMED) {
                $pairing->forceFill([
                    'status' => ApiSyncPairing::STATUS_REVOKED,
                    'token_hash' => null,
                    'revoked_at' => now(),
                    'revoked_by' => $administrator->id,
                    'revoke_reason' => 'The inbound AU-PReMIS transfer was revoked by a local administrator.',
                ])->save();
                $this->snapshots->requestPurge($pairing);
                $this->documents->requestPurge($pairing);
            }
            $locked->forceFill([
                'status' => ApiSyncInvitation::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoked_by' => $administrator->id,
                'revoke_reason' => trim($reason),
            ])->save();

            $revoked = $locked->fresh();
            $this->audit->record($revoked, 'invitation_revoked', 'An authorized local administrator stopped the AU-PReMIS data transfer and revoked its credential.', ['reason' => $revoked->revoke_reason], $administrator, $request);

            return $revoked;
        }, 3);

        return $revoked;
    }

    public function expireAndPrune(int $limit = 500): array
    {
        $ids = ApiSyncInvitation::query()
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->where('status', ApiSyncInvitation::STATUS_PENDING)
                        ->where(function ($expiry): void {
                            $expiry->where('expires_at', '<=', now())
                                ->orWhere('credential_expires_at', '<=', now());
                        });
                })->orWhere(function ($authorizedLifecycle): void {
                    $authorizedLifecycle->whereIn('status', [
                        ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS,
                        ApiSyncInvitation::STATUS_ACTIVATION_PENDING,
                    ])->where('credential_expires_at', '<=', now());
                });
            })
            ->orderBy('expires_at')
            ->limit(max(1, min(2_000, $limit)))
            ->pluck('id');
        $expired = 0;
        foreach ($ids as $id) {
            $changed = DB::transaction(function () use ($id): bool {
                $invitation = ApiSyncInvitation::query()->lockForUpdate()->find($id);
                if (! $invitation) {
                    return false;
                }
                $pendingExpired = $invitation->status === ApiSyncInvitation::STATUS_PENDING
                    && ($invitation->expires_at?->isPast() || $invitation->credential_expires_at?->isPast());
                $authorizationExpired = in_array($invitation->status, [
                    ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS,
                    ApiSyncInvitation::STATUS_ACTIVATION_PENDING,
                ], true) && $invitation->credential_expires_at?->isPast();
                if (! $pendingExpired && ! $authorizationExpired) {
                    return false;
                }
                $invitation->forceFill(['status' => ApiSyncInvitation::STATUS_EXPIRED])->save();
                $this->audit->record(
                    $invitation,
                    'invitation_expired',
                    'An unapproved AU-PReMIS synchronization request reached its safe expiry time and released no data.',
                );

                return true;
            }, 3);
            $expired += $changed ? 1 : 0;
        }
        $nonceIds = DB::table('api_sync_v2_nonces')
            ->where('expires_at', '<=', now()->subHour())
            ->orderBy('expires_at')
            ->limit(max(1, min(5_000, $limit * 5)))
            ->pluck('id');
        $nonces = $nonceIds->isEmpty()
            ? 0
            : DB::table('api_sync_v2_nonces')->whereIn('id', $nonceIds)->delete();

        return ['expired_invitations' => $expired, 'pruned_nonces' => $nonces];
    }

    /** @return array<string, mixed> */
    private function activationDescriptor(ApiSyncInvitation $invitation, ApiSyncPairing $pairing): array
    {
        $instance = $this->datasets->instance();

        return [
            'status' => 'active',
            'invitation_id' => (string) $invitation->id,
            'instance' => ['id' => $instance['id'], 'name' => $instance['name']],
            'target_origin' => (string) $invitation->target_origin,
            'snapshot' => ['id' => (string) $pairing->snapshot_id, 'status' => (string) ($pairing->snapshot_status ?: ApiSyncSnapshotService::STATUS_PENDING)],
            'document_transfer' => $this->documents->summary($pairing),
            'datasets' => collect((array) $invitation->requested_datasets)->map(fn (string $name): array => [
                'name' => $name,
                'count' => null,
                'status' => ApiSyncSnapshotService::STATUS_PENDING,
            ])->values()->all(),
            'credential_expires_at' => $pairing->token_expires_at?->utc()->format('Y-m-d\TH:i:sP'),
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function validateInvitation(array $payload): array
    {
        $validated = validator($payload, [
            'protocol_version' => ['required', 'in:2.0'],
            'invitation_id' => ['required', 'uuid'],
            'central_instance_id' => ['required', 'string', 'min:8', 'max:120', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/'],
            'central_name' => ['required', 'string', 'min:3', 'max:160'],
            'central_origin' => ['required', 'url:http,https', 'max:512'],
            'target_origin' => ['required', 'url:http,https', 'max:512'],
            'requested_datasets' => ['required', 'array', 'min:1', 'max:20'],
            'requested_datasets.*' => ['required', 'string', 'distinct:strict', Rule::in((array) config('api_sync.v2.allowed_datasets', []))],
            'requested_scopes' => ['required', 'array', 'min:1', 'max:10'],
            'requested_scopes.*' => ['required', 'string', 'distinct:strict', Rule::in((array) config('api_sync.v2.allowed_scopes', []))],
            'credential_digest' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'confirmation_url' => ['required', 'url:http,https', 'max:1500'],
            'issued_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'expires_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'credential_expires_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
        ])->validate();
        $this->assertExactKeys($payload, [
            'protocol_version', 'invitation_id', 'central_instance_id', 'central_name', 'central_origin', 'target_origin',
            'requested_datasets', 'requested_scopes', 'credential_digest', 'confirmation_url', 'issued_at', 'expires_at', 'credential_expires_at',
        ]);
        $scopes = (array) $validated['requested_scopes'];
        $metadata = in_array((string) config('api_sync.v2.documents.metadata_scope', 'documents.metadata.read'), $scopes, true);
        $content = in_array((string) config('api_sync.v2.documents.content_scope', 'documents.content.read'), $scopes, true);
        if ($metadata !== $content) {
            throw new ApiSyncException('invalid_document_scope_pair', 'Immutable document metadata and content scopes must be requested together.', 422);
        }

        $credentialExpiry = CarbonImmutable::parse((string) $validated['credential_expires_at']);
        $approvalExpiry = CarbonImmutable::parse((string) $validated['expires_at']);
        $minimumTransferSeconds = (int) config('api_sync.snapshot.maximum_build_seconds', 900) + 900;
        if ($credentialExpiry->lessThanOrEqualTo($approvalExpiry)
            || $credentialExpiry->lessThanOrEqualTo(now()->addSeconds($minimumTransferSeconds))
            || $credentialExpiry->greaterThan(now()->addDay())) {
            throw new ApiSyncException('invalid_credential_expiry', 'The signed transfer credential expiry does not provide a safe bounded transfer window.', 422);
        }

        return $validated;
    }

    private function assertInvitationTimes(string $issuedAtValue, string $expiresAtValue, int $signatureTimestamp): void
    {
        $issuedAt = CarbonImmutable::parse($issuedAtValue);
        $expiresAt = CarbonImmutable::parse($expiresAtValue);
        $skew = (int) config('api_sync.v2.maximum_clock_skew_seconds', 300);
        $maximumTtl = (int) config('api_sync.v2.maximum_invitation_ttl_minutes', 15);
        if (abs($issuedAt->timestamp - $signatureTimestamp) > $skew || abs(now()->diffInSeconds($issuedAt, false)) > $skew) {
            throw new ApiSyncException('stale_invitation', 'The invitation issue time is outside the allowed clock window.', 422);
        }
        if ($expiresAt->lessThanOrEqualTo(now()) || $expiresAt->lessThanOrEqualTo($issuedAt) || $issuedAt->diffInMinutes($expiresAt) > $maximumTtl) {
            throw new ApiSyncException('invalid_invitation_expiry', 'The invitation expiry is invalid or exceeds the allowed approval window.', 422);
        }
    }

    /** @param array<string, mixed> $payload @param list<string> $expected */
    private function assertExactKeys(array $payload, array $expected): void
    {
        $actual = array_keys($payload);
        sort($actual, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($actual !== $expected) {
            throw new ApiSyncException('unexpected_signed_fields', 'The signed synchronization payload contains missing or unsupported fields.', 422);
        }
    }

    private function reserveNonce(string $centralInstance, string $nonce, string $requestId, string $purpose, string $payloadHash, CarbonImmutable|\Carbon\Carbon $expiresAt): void
    {
        DB::table('api_sync_v2_nonces')->insert([
            'id' => (string) Str::uuid(),
            'central_instance_id' => $centralInstance,
            'nonce' => strtolower($nonce),
            'request_id' => strtolower($requestId),
            'purpose' => $purpose,
            'payload_hash' => $payloadHash,
            'seen_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }

    private function restorePendingAfterConfirmationFailure(ApiSyncInvitation $invitation, string $message): void
    {
        // An unavailable or unverifiable response is ambiguous: AU-PReMIS may
        // already have consumed the code and committed its authorization. Keep
        // the same request ID and nonce so a retry can recover that exact receipt.
        $this->audit->record($invitation->fresh(), 'approval_confirmation_failed', $message, statusCode: 502);
    }

    private function resetAfterRecoverableSignedRejection(ApiSyncInvitation $invitation, string $errorCode): void
    {
        DB::transaction(function () use ($invitation): void {
            $locked = ApiSyncInvitation::query()->lockForUpdate()->find($invitation->id);
            if ($locked?->status === ApiSyncInvitation::STATUS_APPROVAL_IN_PROGRESS) {
                $locked->forceFill([
                    'status' => ApiSyncInvitation::STATUS_PENDING,
                    'confirmation_request_id' => null,
                    'confirmation_request_nonce' => null,
                    'approved_by' => null,
                ])->save();
            }
        }, 3);
        $this->audit->record(
            $invitation->fresh(),
            'approval_recoverably_rejected',
            'AU-PReMIS signed a recoverable approval rejection; no credential was activated.',
            ['error_code' => $errorCode],
            statusCode: 409,
        );
    }

    private function isTerminalSignedRejection(ApiSyncInvitation $invitation, string $errorCode, int $status): bool
    {
        if (in_array($errorCode, [
            'remote_instance_changed',
            'remote_instance_duplicate',
            'invitation_failed',
            'invitation_revoked',
            'invitation_expired',
            'activation_credential_expired',
            'activation_credential_unavailable',
        ], true)) {
            return true;
        }
        if (in_array($errorCode, [
            'invalid_confirmation_code',
            'invitation_temporarily_locked',
            'source_transfer_active',
            'confirmation_in_progress',
            'confirmation_replay',
            'activation_already_pending',
            'stale_confirmation',
        ], true)) {
            return false;
        }

        return in_array($invitation->fresh()?->status, [ApiSyncInvitation::STATUS_ACTIVATION_RECEIVED, ApiSyncInvitation::STATUS_ACTIVE], true)
            && $status >= 400 && $status < 500;
    }

    private function revokeAfterSignedCentralRejection(ApiSyncInvitation $invitation, string $errorCode): void
    {
        DB::transaction(function () use ($invitation, $errorCode): void {
            $locked = ApiSyncInvitation::query()->lockForUpdate()->find($invitation->id);
            $pairing = ApiSyncPairing::query()->where('inbound_invitation_id', $invitation->id)->lockForUpdate()->first();
            if ($pairing?->status === ApiSyncPairing::STATUS_CLAIMED) {
                $pairing->forceFill([
                    'status' => ApiSyncPairing::STATUS_REVOKED,
                    'token_hash' => null,
                    'revoked_at' => now(),
                    'revoke_reason' => 'AU-PReMIS issued a signed terminal rejection.',
                ])->save();
                $this->snapshots->requestPurge($pairing);
                $this->documents->requestPurge($pairing);
            }
            if ($locked) {
                $locked->forceFill([
                    'status' => in_array($errorCode, ['invitation_expired', 'activation_credential_expired'], true)
                        ? ApiSyncInvitation::STATUS_EXPIRED
                        : ApiSyncInvitation::STATUS_FAILED,
                    'terminal_error_code' => $errorCode,
                ])->save();
            }
        }, 3);
        $this->audit->record(
            $invitation->fresh(),
            'signed_central_rejection_applied',
            'A signed terminal AU-PReMIS rejection revoked the local credential and scheduled snapshot removal.',
            ['error_code' => $errorCode],
            statusCode: 409,
        );
    }

    /** @param array<string, mixed> $receipt @return array<string, mixed> */
    private function validateFinalReceipt(array $receipt, ApiSyncInvitation $invitation): array
    {
        $this->assertExactKeys($receipt, [
            'protocol_version', 'status', 'invitation_id', 'confirmation_id', 'run_id', 'accepted_at',
            'approved_datasets', 'approved_scopes', 'credential_expires_at',
        ]);
        $validated = validator($receipt, [
            'protocol_version' => ['required', 'in:2.0'],
            'status' => ['required', 'in:accepted'],
            'invitation_id' => ['required', 'uuid'],
            'confirmation_id' => ['required', 'uuid'],
            'run_id' => ['required', 'string', 'max:120'],
            'accepted_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'approved_datasets' => ['required', 'array', 'min:1'],
            'approved_datasets.*' => ['string', Rule::in((array) $invitation->requested_datasets)],
            'approved_scopes' => ['required', 'array', 'min:1'],
            'approved_scopes.*' => ['string', Rule::in((array) $invitation->requested_scopes)],
            'credential_expires_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
        ])->validate();
        if (! hash_equals(strtolower((string) $invitation->id), strtolower((string) $validated['invitation_id']))
            || array_values($validated['approved_datasets']) !== array_values((array) $invitation->requested_datasets)
            || array_values($validated['approved_scopes']) !== array_values((array) $invitation->requested_scopes)) {
            throw new ApiSyncException('confirmation_receipt_mismatch', 'The final receipt changed the approved invitation binding.', 422);
        }

        return $validated;
    }

    /** @param null|array<string, mixed> $receipt */
    private function finalizationAlreadyApplied(ApiSyncInvitation $invitation, string $outcome, ?array $receipt, string $errorCode): bool
    {
        if ($outcome === 'accepted') {
            return $invitation->status === ApiSyncInvitation::STATUS_ACTIVE
                && is_array($receipt)
                && hash_equals((string) $invitation->confirmation_id, strtolower((string) $receipt['confirmation_id']))
                && hash_equals((string) $invitation->central_run_id, (string) $receipt['run_id']);
        }

        return in_array($invitation->status, [ApiSyncInvitation::STATUS_FAILED, ApiSyncInvitation::STATUS_EXPIRED], true)
            && hash_equals((string) $invitation->terminal_error_code, $errorCode);
    }

    private function isLiveActivationPairing(ApiSyncInvitation $invitation, ApiSyncPairing $pairing, string $digest): bool
    {
        return in_array($invitation->status, [ApiSyncInvitation::STATUS_ACTIVATION_RECEIVED, ApiSyncInvitation::STATUS_ACTIVE], true)
            && $pairing->status === ApiSyncPairing::STATUS_CLAIMED
            && is_string($pairing->token_hash)
            && hash_equals($pairing->token_hash, $digest)
            && $pairing->token_expires_at?->isFuture();
    }

    private function revokeAfterInvalidReceipt(ApiSyncInvitation $invitation, string $message): void
    {
        DB::transaction(function () use ($invitation): void {
            $locked = ApiSyncInvitation::query()->lockForUpdate()->find($invitation->id);
            $pairing = ApiSyncPairing::query()->where('inbound_invitation_id', $invitation->id)->lockForUpdate()->first();
            if ($pairing?->status === ApiSyncPairing::STATUS_CLAIMED) {
                $pairing->forceFill([
                    'status' => ApiSyncPairing::STATUS_REVOKED,
                    'token_hash' => null,
                    'revoked_at' => now(),
                    'revoke_reason' => 'The signed central approval receipt was invalid.',
                ])->save();
                $this->snapshots->requestPurge($pairing);
                $this->documents->requestPurge($pairing);
            }
            if ($locked) {
                $locked->forceFill(['status' => ApiSyncInvitation::STATUS_FAILED])->save();
            }
        });
        $this->audit->record($invitation->fresh(), 'confirmation_receipt_rejected', $message, statusCode: 502);
    }

    private function ensureSecureRequest(Request $request): void
    {
        if (! $request->expectsJson() && ! str_contains(strtolower((string) $request->header('Content-Type')), 'application/json')) {
            throw new ApiSyncException('json_required', 'Synchronization protocol requests must use JSON.', 415);
        }
        if (config('api_sync.require_https') && app()->environment('production') && ! $request->isSecure()) {
            throw new ApiSyncException('https_required', 'Synchronization protocol requests require HTTPS.', 426);
        }
    }
}
