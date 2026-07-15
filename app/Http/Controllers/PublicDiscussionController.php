<?php

namespace App\Http\Controllers;

use App\Models\DiscussionTopic;
use App\Models\DiscussionTopicDocument;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicDiscussionController extends Controller
{
    public function thematicAreas(): View
    {
        return view('discussion.thematic-areas');
    }

    public function current(): View
    {
        return view('discussion.current');
    }

    public function join(): View
    {
        return view('discussion.join');
    }

    public function readDocument(DiscussionTopicDocument $document): StreamedResponse|RedirectResponse
    {
        $this->ensureDocumentIsPublic($document);

        if (! $document->canPreview()) {
            return redirect()->route('discussion.documents.download', $document);
        }

        return Storage::disk('local')->response(
            $document->storage_path,
            $document->file_name,
            $this->documentHeaders($document, true),
            'inline'
        );
    }

    public function downloadDocument(DiscussionTopicDocument $document): StreamedResponse
    {
        $this->ensureDocumentIsPublic($document);

        return Storage::disk('local')->download(
            $document->storage_path,
            $document->file_name,
            $this->documentHeaders($document, false)
        );
    }

    private function ensureDocumentIsPublic(DiscussionTopicDocument $document): void
    {
        abort_unless(
            DiscussionTopic::query()
                ->publiclyVisible()
                ->whereKey($document->topic_id)
                ->exists(),
            404
        );

        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);
    }

    /**
     * @return array<string, string>
     */
    private function documentHeaders(DiscussionTopicDocument $document, bool $inline): array
    {
        $headers = [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ];

        if ($inline) {
            $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox";
        }

        return $headers;
    }
}
