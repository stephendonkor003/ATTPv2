<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiSyncException;
use App\Models\ApiSyncInvitation;
use App\Models\ApiSyncPairing;
use App\Services\ApiSync\ApiSyncDocumentSnapshotService;
use App\Services\ApiSync\ApiSyncInvitationAuditService;
use App\Services\ApiSync\ApiSyncSnapshotService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiSyncV2
{
    public function __construct(
        private readonly ApiSyncInvitationAuditService $audit,
        private readonly ApiSyncSnapshotService $snapshots,
        private readonly ApiSyncDocumentSnapshotService $documents,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (config('api_sync.require_https') && app()->environment('production') && ! $request->isSecure()) {
            throw new ApiSyncException('https_required', 'Synchronization data can only be transferred over HTTPS.', 426);
        }

        $token = trim((string) $request->bearerToken());
        $consumer = trim((string) $request->header('X-Consumer-Instance'));
        if ($token === '' || strlen($token) < 43 || strlen($token) > 160 || ! preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
            throw new ApiSyncException('invalid_sync_credential', 'A valid synchronization credential is required.', 401);
        }

        $digest = hash('sha256', $token);
        $pairing = ApiSyncPairing::query()
            ->with('inboundInvitation')
            ->whereNotNull('inbound_invitation_id')
            ->where('token_hash', $digest)
            ->first();
        $invitation = $pairing?->inboundInvitation;
        if (! $pairing || ! $invitation || ! hash_equals((string) $pairing->token_hash, $digest)) {
            throw new ApiSyncException('invalid_sync_credential', 'A valid synchronization credential is required.', 401);
        }
        if ($consumer === '' || ! hash_equals((string) $invitation->central_instance_id, $consumer)) {
            $this->audit->record($invitation, 'consumer_instance_rejected', 'A synchronization request was rejected because its AU-PReMIS instance identifier did not match.', request: $request, statusCode: 401);
            throw new ApiSyncException('consumer_instance_mismatch', 'The synchronization credential is not valid for this AU-PReMIS instance.', 401);
        }
        $completionRetry = $request->routeIs('api.sync.v2.complete')
            && $pairing->status === ApiSyncPairing::STATUS_COMPLETED
            && $invitation->status === ApiSyncInvitation::STATUS_COMPLETED
            && $pairing->token_expires_at?->isFuture();
        if ($completionRetry) {
            $request->attributes->set('api_sync_pairing', $pairing);
            $request->attributes->set('api_sync_invitation', $invitation);

            return $next($request);
        }
        if (! in_array($invitation->status, [ApiSyncInvitation::STATUS_ACTIVATION_RECEIVED, ApiSyncInvitation::STATUS_ACTIVE], true)
            || ! $pairing->isUsable()) {
            if ($pairing->status === ApiSyncPairing::STATUS_CLAIMED && $pairing->token_expires_at?->isPast()) {
                DB::transaction(function () use ($pairing, $invitation): void {
                    $lockedInvitation = ApiSyncInvitation::query()->lockForUpdate()->find($invitation->id);
                    $lockedPairing = ApiSyncPairing::query()->lockForUpdate()->find($pairing->id);
                    $bindingMatches = $lockedInvitation !== null
                        && $lockedPairing !== null
                        && hash_equals((string) $lockedInvitation->id, (string) $lockedPairing->inbound_invitation_id);
                    if ($bindingMatches
                        && $lockedPairing->status === ApiSyncPairing::STATUS_CLAIMED
                        && $lockedPairing->token_expires_at?->isPast()) {
                        $lockedPairing->forceFill([
                            'status' => ApiSyncPairing::STATUS_EXPIRED,
                            'token_hash' => null,
                            'revoke_reason' => 'The AU-PReMIS synchronization credential expired.',
                        ])->save();
                        $this->snapshots->requestPurge($lockedPairing);
                        $this->documents->requestPurge($lockedPairing);
                        $lockedInvitation?->forceFill(['status' => ApiSyncInvitation::STATUS_EXPIRED])->save();
                    }
                }, 3);
            }
            throw new ApiSyncException('sync_session_unavailable', 'The synchronization session has expired, is incomplete, or was revoked.', 401);
        }

        ApiSyncPairing::query()->whereKey($pairing->id)->update([
            'last_used_at' => now(),
            'request_count' => DB::raw('request_count + 1'),
        ]);
        $request->attributes->set('api_sync_pairing', $pairing);
        $request->attributes->set('api_sync_invitation', $invitation);

        return $next($request);
    }
}
