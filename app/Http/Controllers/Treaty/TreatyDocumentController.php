<?php

namespace App\Http\Controllers\Treaty;

use App\Http\Controllers\Controller;
use App\Models\TreatyMemberStateStatus;
use App\Models\TreatySupportingDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TreatyDocumentController extends Controller
{
    public function download(Request $request, TreatyMemberStateStatus $treatyStatus, string $type)
    {
        abort_unless(in_array($type, ['signed', 'ratified', 'original'], true), 404);

        $user = $request->user();
        $isAdminViewer = $user && (
            $user->can('treaties.view')
            || $user->can('treaties.edit')
            || $user->can('treaties.delete')
        );
        $hasMemberStateDocumentPermission = $user && $user->can('member_state.treaties.documents.download');
        $isMemberStateOwner = $user
            && $user->user_type === 'member_state'
            && $hasMemberStateDocumentPermission
            && $user->member_state_id === $treatyStatus->member_state_id;

        abort_unless($isAdminViewer || $isMemberStateOwner, 403);

        $path = $type === 'signed'
            ? (string) ($treatyStatus->signed_document_path ?? '')
            : ($type === 'ratified'
                ? (string) ($treatyStatus->ratified_document_path ?? '')
                : (string) ($treatyStatus->original_document_path ?? ''));
        $name = $type === 'signed'
            ? $treatyStatus->signed_document_name
            : ($type === 'ratified'
                ? $treatyStatus->ratified_document_name
                : $treatyStatus->original_document_name);

        abort_if($path === '', 404, 'Document not found.');

        $privateDisk = Storage::disk('local');

        if (!$privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            $stream = Storage::disk('public')->readStream($path);
            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                Storage::disk('public')->delete($path);
            }
        }

        abort_unless($privateDisk->exists($path), 404, 'Document file missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->boolean('download')) {
            return $privateDisk->download($path, $name ?? basename($path), $headers);
        }

        return $privateDisk->response($path, null, $headers);
    }

    public function downloadSupportingDocument(Request $request, TreatySupportingDocument $supportingDocument)
    {
        $user = $request->user();
        $isAdminViewer = $user && (
            $user->can('treaties.view')
            || $user->can('treaties.edit')
            || $user->can('treaties.delete')
        );
        $isMemberStateUser = $user
            && $user->user_type === 'member_state'
            && $user->can('member_state.treaties.documents.download');

        abort_unless($isAdminViewer || $isMemberStateUser, 403);

        $path = (string) ($supportingDocument->file_path ?? '');
        $name = $supportingDocument->file_name ?? null;

        abort_if($path === '', 404, 'Document not found.');

        $privateDisk = Storage::disk('local');

        if (!$privateDisk->exists($path) && Storage::disk('public')->exists($path)) {
            $stream = Storage::disk('public')->readStream($path);
            if ($stream !== false) {
                $privateDisk->writeStream($path, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                Storage::disk('public')->delete($path);
            }
        }

        abort_unless($privateDisk->exists($path), 404, 'Document file missing on disk.');

        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($request->boolean('download')) {
            return $privateDisk->download($path, $name ?? basename($path), $headers);
        }

        return $privateDisk->response($path, null, $headers);
    }
}
