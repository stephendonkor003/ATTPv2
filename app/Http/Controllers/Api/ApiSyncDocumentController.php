<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiSyncException;
use App\Http\Controllers\Controller;
use App\Models\ApiSyncInvitation;
use App\Models\ApiSyncPairing;
use App\Services\ApiSync\ApiSyncAuditService;
use App\Services\ApiSync\ApiSyncDocumentSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class ApiSyncDocumentController extends Controller
{
    public function __construct(
        private readonly ApiSyncDocumentSnapshotService $documents,
        private readonly ApiSyncAuditService $audit,
    ) {}

    public function inventory(Request $request): JsonResponse
    {
        [$pairing, $invitation] = $this->context($request);
        $this->assertInvitationHeader($request, $invitation);
        $this->assertScope($invitation, (string) config('api_sync_documents.metadata_scope', 'documents.metadata.read'));
        $validated = validator($request->query(), [
            'snapshot_id' => ['required', 'uuid'],
            'cursor' => ['nullable', 'string', 'max:1024'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:250'],
        ])->validate();
        if (array_diff(array_keys($request->query()), ['snapshot_id', 'cursor', 'limit']) !== []) {
            throw new ApiSyncException('unexpected_inventory_parameter', 'The document inventory request contains an unsupported parameter.', 422);
        }
        $inventory = $this->documents->inventory(
            $pairing,
            (string) $validated['snapshot_id'],
            $validated['cursor'] ?? null,
            (int) ($validated['limit'] ?? config('api_sync_documents.page_size', 100)),
        );
        $this->audit->record($pairing, 'sync_document_inventory_exported', 'AU-PReMIS retrieved one immutable approved-document inventory page.', [
            'snapshot_id' => $validated['snapshot_id'],
            'returned' => $inventory['meta']['returned'],
            'has_more' => $inventory['meta']['has_more'],
        ], dataset: 'documents', recordCount: (int) $inventory['meta']['returned'], request: $request);

        return response()->json($inventory, 200, $this->noStore());
    }

    public function content(Request $request, string $transferId): Response
    {
        [$pairing, $invitation] = $this->context($request);
        $this->assertInvitationHeader($request, $invitation);
        $this->assertScope($invitation, (string) config('api_sync_documents.metadata_scope', 'documents.metadata.read'));
        $this->assertScope($invitation, (string) config('api_sync_documents.content_scope', 'documents.content.read'));
        if (! Str::isUuid($transferId)) {
            throw new ApiSyncException('document_not_found', 'The requested immutable document is not part of this synchronization snapshot.', 404);
        }
        $validated = validator($request->query(), ['snapshot_id' => ['required', 'uuid']])->validate();
        if (array_keys($request->query()) !== ['snapshot_id']) {
            throw new ApiSyncException('unexpected_content_parameter', 'The document content request contains an unsupported parameter.', 422);
        }
        $chunk = $this->documents->content(
            $pairing,
            (string) $validated['snapshot_id'],
            strtolower($transferId),
            $request->header('Range'),
            $request->header('If-Match'),
        );
        $document = $chunk['document'];
        $filename = $document->display_filename ?: 'approved-document.bin';
        $fallback = preg_replace('/[^A-Za-z0-9._-]+/', '-', Str::ascii($filename)) ?: 'approved-document.bin';
        $headers = [
            ...$this->noStore(),
            'Accept-Ranges' => 'bytes',
            'Content-Type' => (string) $document->detected_mime,
            'Content-Length' => (string) strlen($chunk['bytes']),
            'Content-Range' => sprintf('bytes %d-%d/%d', $chunk['start'], $chunk['end'], $chunk['total']),
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $filename, $fallback),
            'ETag' => $chunk['etag'],
            'X-Content-SHA256' => (string) $document->sha256,
            'X-Chunk-SHA256' => $chunk['chunk_sha256'],
            'X-Snapshot-Id' => (string) $validated['snapshot_id'],
            'X-Document-Transfer-Id' => (string) $document->id,
        ];

        return response($chunk['bytes'], 206, $headers);
    }

    /** @return array{ApiSyncPairing,ApiSyncInvitation} */
    private function context(Request $request): array
    {
        $pairing = $request->attributes->get('api_sync_pairing');
        $invitation = $request->attributes->get('api_sync_invitation');
        abort_unless($pairing instanceof ApiSyncPairing && $invitation instanceof ApiSyncInvitation, 401);

        return [$pairing, $invitation];
    }

    private function assertInvitationHeader(Request $request, ApiSyncInvitation $invitation): void
    {
        $provided = strtolower(trim((string) $request->header('X-AUPReMIS-Invitation-Id')));
        if ($provided === '' || ! hash_equals(strtolower((string) $invitation->id), $provided)) {
            throw new ApiSyncException('document_invitation_mismatch', 'The document request is not bound to this approved invitation.', 403);
        }
    }

    private function assertScope(ApiSyncInvitation $invitation, string $scope): void
    {
        if (! in_array($scope, (array) $invitation->requested_scopes, true)) {
            throw new ApiSyncException('document_scope_not_approved', 'Document transfer was not included in the locally approved scope.', 403);
        }
    }

    /** @return array<string,string> */
    private function noStore(): array
    {
        return ['Cache-Control' => 'no-store, private', 'Pragma' => 'no-cache'];
    }
}
