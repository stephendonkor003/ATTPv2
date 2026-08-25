<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApiSyncAbandonRequest;
use App\Http\Requests\ApiSyncClaimRequest;
use App\Models\ApiSyncPairing;
use App\Services\ApiSync\ApiSyncAuditService;
use App\Services\ApiSync\ApiSyncPairingService;
use App\Services\ApiSync\ApiSyncSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiSyncController extends Controller
{
    public function __construct(
        private readonly ApiSyncPairingService $pairings,
        private readonly ApiSyncSnapshotService $snapshots,
        private readonly ApiSyncAuditService $audit,
    ) {}

    public function claim(ApiSyncClaimRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $claimed = $this->pairings->claim(
            $validated['code'],
            $validated['consumer_instance'],
            $validated['consumer_name'],
            $validated['idempotency_key'],
            $validated['recovery_key'],
            $request,
        );
        $manifest = $this->snapshots->claimDescriptor($claimed['pairing']);

        return response()->json([
            'data' => [
                'access_token' => $claimed['access_token'],
                'token_type' => 'Bearer',
                'expires_at' => $claimed['pairing']->token_expires_at?->utc()->format('Y-m-d\TH:i:s\Z'),
                'consumer_instance' => $claimed['pairing']->consumer_instance,
                ...$manifest,
            ],
        ], 201, [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }

    public function manifest(Request $request): JsonResponse
    {
        $pairing = $this->pairing($request);
        $manifest = $this->snapshots->manifest($pairing);
        $this->audit->record(
            $pairing,
            'sync_manifest_exported',
            'AU-PReMIS retrieved the synchronization manifest.',
            ['dataset_count' => count($manifest['datasets'])],
            request: $request,
        );

        return response()->json(['data' => $manifest], 200, ['Cache-Control' => 'no-store, private']);
    }

    public function dataset(Request $request, string $dataset): JsonResponse
    {
        $validated = validator($request->query(), [
            'snapshot_id' => ['required', 'uuid'],
            'cursor' => ['nullable', 'string', 'max:1024'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ])->validate();
        $pairing = $this->pairing($request);
        $page = $this->snapshots->page(
            $pairing,
            $dataset,
            $validated['snapshot_id'],
            $validated['cursor'] ?? null,
            (int) ($validated['limit'] ?? config('api_sync.pagination.default_limit', 100)),
        );
        $this->audit->record(
            $pairing,
            'sync_dataset_page_exported',
            sprintf('AU-PReMIS retrieved %d %s synchronization record(s).', $page['meta']['returned'], str_replace('_', ' ', $dataset)),
            [
                'snapshot_id' => $validated['snapshot_id'],
                'has_more' => $page['meta']['has_more'],
                'cursor_supplied' => filled($validated['cursor'] ?? null),
            ],
            dataset: $dataset,
            recordCount: $page['meta']['returned'],
            request: $request,
        );

        return response()->json($page, 200, ['Cache-Control' => 'no-store, private']);
    }

    public function complete(Request $request): JsonResponse
    {
        $validated = validator($request->all(), [
            'snapshot_id' => ['required', 'uuid'],
        ])->validate();
        $pairing = $this->pairing($request);

        if (! hash_equals((string) $pairing->snapshot_id, $validated['snapshot_id'])) {
            throw new \App\Exceptions\ApiSyncException('snapshot_mismatch', 'The snapshot does not belong to this synchronization session.', 409);
        }

        $this->pairings->complete($pairing, $request);

        return response()->json([
            'data' => [
                'status' => 'completed',
                'snapshot_id' => $validated['snapshot_id'],
                'credential_revoked' => true,
            ],
        ]);
    }

    public function abandon(ApiSyncAbandonRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->pairings->abandon(
            $validated['recovery_key'],
            $validated['consumer_instance'],
            $validated['idempotency_key'] ?? null,
            $request,
        );

        return response()->json([
            'data' => [
                'status' => 'abandoned',
                'credential_revoked' => true,
            ],
        ], 200, [
            'Cache-Control' => 'no-store, private',
            'Pragma' => 'no-cache',
        ]);
    }

    private function pairing(Request $request): ApiSyncPairing
    {
        $pairing = $request->attributes->get('api_sync_pairing');
        abort_unless($pairing instanceof ApiSyncPairing, 401);

        return $pairing;
    }
}
