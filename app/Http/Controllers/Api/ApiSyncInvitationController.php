<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiSyncException;
use App\Http\Controllers\Controller;
use App\Models\ApiSyncInvitation;
use App\Models\ApiSyncPairing;
use App\Services\ApiSync\ApiSyncAuditService;
use App\Services\ApiSync\ApiSyncDocumentSnapshotService;
use App\Services\ApiSync\ApiSyncInvitationService;
use App\Services\ApiSync\ApiSyncPairingService;
use App\Services\ApiSync\ApiSyncSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiSyncInvitationController extends Controller
{
    public function __construct(
        private readonly ApiSyncInvitationService $invitations,
        private readonly ApiSyncSnapshotService $snapshots,
        private readonly ApiSyncDocumentSnapshotService $documents,
        private readonly ApiSyncPairingService $pairings,
        private readonly ApiSyncAuditService $syncAudit,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $this->assertBoundedBody($request);
        $payload = $request->json()->all();
        if (! is_array($payload) || array_is_list($payload)) {
            throw new ApiSyncException('invalid_signed_payload', 'The synchronization invitation must be a JSON object.', 400);
        }
        $result = $this->invitations->receive($payload, $request);
        $invitation = $result['invitation'];

        return response()->json([
            'data' => [
                'status' => $result['created'] ? 'pending_local_approval' : $invitation->status,
                'invitation_id' => (string) $invitation->id,
                'remote_instance_id' => (string) config('api_sync.provider.instance_id'),
                'received_at' => $invitation->received_at?->utc()->format('Y-m-d\TH:i:sP'),
                'expires_at' => $invitation->expires_at?->utc()->format('Y-m-d\TH:i:sP'),
            ],
        ], $result['created'] ? 202 : 200, $this->noStore());
    }

    public function activate(Request $request, ApiSyncInvitation $invitation): JsonResponse
    {
        $this->assertBoundedBody($request);
        $payload = $request->json()->all();
        if (! is_array($payload) || array_is_list($payload)) {
            throw new ApiSyncException('invalid_signed_payload', 'The activation proof must be a JSON object.', 400);
        }

        return response()->json([
            'data' => $this->invitations->activate($invitation, $payload, $request),
        ], 200, $this->noStore());
    }

    public function finalize(Request $request, ApiSyncInvitation $invitation): JsonResponse
    {
        $this->assertBoundedBody($request);
        $payload = $request->json()->all();
        if (! is_array($payload) || array_is_list($payload)) {
            throw new ApiSyncException('invalid_signed_payload', 'The finalization proof must be a JSON object.', 400);
        }

        return response()->json([
            'data' => $this->invitations->finalize($invitation, $payload, $request),
        ], 200, $this->noStore());
    }

    public function manifest(Request $request): JsonResponse
    {
        [$pairing, $invitation] = $this->context($request);
        $this->assertRecordsScope($invitation);
        $manifest = $this->snapshots->manifest($pairing);
        $selected = collect($manifest['datasets'])
            ->filter(fn (array $dataset): bool => $invitation->permitsDataset((string) $dataset['name']))
            ->values();
        $totals = DB::table('api_sync_snapshot_datasets')
            ->where('pairing_id', $pairing->id)
            ->whereIn('dataset', (array) $invitation->requested_datasets)
            ->selectRaw('COALESCE(SUM(record_count), 0) AS records, COALESCE(SUM(payload_bytes), 0) AS bytes')
            ->first();
        $manifest['datasets'] = $selected->all();
        $manifest['snapshot']['record_count'] = (int) ($totals?->records ?? 0);
        $manifest['snapshot']['payload_bytes'] = (int) ($totals?->bytes ?? 0);
        $manifest['protocol_version'] = '2.0';
        $manifest['invitation_id'] = (string) $invitation->id;
        $scopes = (array) $invitation->requested_scopes;
        $documentsApproved = (bool) config('api_sync.v2.documents.enabled', true)
            && in_array((string) config('api_sync.v2.documents.metadata_scope', 'documents.metadata.read'), $scopes, true)
            && in_array((string) config('api_sync.v2.documents.content_scope', 'documents.content.read'), $scopes, true);
        $manifest['document_transfer'] = $documentsApproved
            ? [
                ...$this->documents->summary($pairing),
                'inventory_path' => '/api/sync/v2/documents/inventory',
                'content_path_template' => '/api/sync/v2/documents/{transfer_id}/content',
            ]
            : ['status' => ApiSyncDocumentSnapshotService::STATUS_NOT_REQUESTED];

        $this->syncAudit->record($pairing, 'v2_manifest_exported', 'AU-PReMIS retrieved the approved immutable synchronization manifest.', ['invitation_id' => $invitation->id, 'dataset_count' => $selected->count()], request: $request);

        return response()->json(['data' => $manifest], 200, $this->noStore());
    }

    public function dataset(Request $request, string $dataset): JsonResponse
    {
        [$pairing, $invitation] = $this->context($request);
        $this->assertRecordsScope($invitation);
        if (! $invitation->permitsDataset($dataset)) {
            throw new ApiSyncException('dataset_not_approved', 'This dataset was not included in the locally approved transfer scope.', 403);
        }
        $validated = validator($request->query(), [
            'snapshot_id' => ['required', 'uuid'],
            'cursor' => ['nullable', 'string', 'max:1024'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ])->validate();
        $page = $this->snapshots->page(
            $pairing,
            $dataset,
            (string) $validated['snapshot_id'],
            $validated['cursor'] ?? null,
            (int) ($validated['limit'] ?? config('api_sync.pagination.default_limit', 100)),
        );
        $this->syncAudit->record($pairing, 'v2_dataset_page_exported', sprintf('AU-PReMIS retrieved %d approved %s record(s).', $page['meta']['returned'], str_replace('_', ' ', $dataset)), ['invitation_id' => $invitation->id, 'has_more' => $page['meta']['has_more']], $dataset, $page['meta']['returned'], request: $request);

        return response()->json($page, 200, $this->noStore());
    }

    public function complete(Request $request): JsonResponse
    {
        [$pairing, $invitation] = $this->context($request);
        $validated = validator($request->all(), ['snapshot_id' => ['required', 'uuid']])->validate();
        if (! hash_equals((string) $pairing->snapshot_id, (string) $validated['snapshot_id'])) {
            throw new ApiSyncException('snapshot_mismatch', 'The snapshot does not belong to this invitation.', 409);
        }
        $this->pairings->complete($pairing, $request);

        return response()->json(['data' => [
            'credential_revoked' => true,
            'snapshot_id' => (string) $validated['snapshot_id'],
        ]], 200, $this->noStore());
    }

    /** @return array{ApiSyncPairing, ApiSyncInvitation} */
    private function context(Request $request): array
    {
        $pairing = $request->attributes->get('api_sync_pairing');
        $invitation = $request->attributes->get('api_sync_invitation');
        abort_unless($pairing instanceof ApiSyncPairing && $invitation instanceof ApiSyncInvitation, 401);

        return [$pairing, $invitation];
    }

    private function assertRecordsScope(ApiSyncInvitation $invitation): void
    {
        if (! in_array('records.read', (array) $invitation->requested_scopes, true)) {
            throw new ApiSyncException('scope_not_approved', 'Record transfer was not included in the locally approved scope.', 403);
        }
    }

    /** @return array<string, string> */
    private function noStore(): array
    {
        return ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache'];
    }

    private function assertBoundedBody(Request $request): void
    {
        if (strlen($request->getContent()) > 65_536) {
            throw new ApiSyncException('signed_payload_too_large', 'The signed synchronization payload exceeds the 64 KiB protocol limit.', 413);
        }
    }
}
