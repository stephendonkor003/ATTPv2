<?php

use App\Http\Controllers\System\DiscussionAdminController;
use App\Models\DiscussionTopic;
use App\Models\DiscussionTopicDocument;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$kernel = $app->make(HttpKernel::class);

foreach (['discussion.documents.read', 'discussion.documents.download', 'system.discussions.topics.documents.open'] as $routeName) {
    if (! Route::has($routeName)) {
        fwrite(STDERR, "Missing uploaded discussion document route: {$routeName}\n");
        exit(1);
    }
}

Storage::fake('local');
DB::beginTransaction();

try {
    $topic = DiscussionTopic::query()->where('status', 'open')->firstOrFail();
    $admin = User::query()
        ->whereHas('role', fn ($query) => $query->where('name', 'System Admin'))
        ->firstOrFail();
    $controller = $app->make(DiscussionAdminController::class);

    $makeUpdateRequest = function (array $parameters = [], array $files = []) use ($app, $topic, $admin): Request {
        $request = Request::create(
            "/system/discussions/topics/{$topic->id}",
            'PUT',
            [
                'theme_id' => $topic->theme_id,
                'title' => $topic->title,
                'slug' => $topic->slug,
                'summary' => $topic->summary,
                'body' => $topic->body,
                'status' => 'open',
                'is_featured' => $topic->is_featured ? '1' : '0',
                'requires_moderation' => $topic->requires_moderation ? '1' : '0',
                'allow_replies' => $topic->allow_replies ? '1' : '0',
                'starts_at' => $topic->starts_at?->format('Y-m-d H:i:s'),
                'closes_at' => $topic->closes_at?->format('Y-m-d H:i:s'),
                'related_links' => $topic->related_links,
                'materials' => $topic->materials,
                'documents' => $topic->documents,
                ...$parameters,
            ],
            [],
            $files,
            [
                'HTTP_HOST' => '127.0.0.1:8000',
                'SERVER_PORT' => 8000,
                'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
                'HTTP_REFERER' => "http://127.0.0.1:8000/system/discussions/topics/{$topic->id}/edit",
            ]
        );
        $request->setUserResolver(fn () => $admin);
        $app['auth']->guard()->setUser($admin);
        $app->instance('request', $request);

        return $request;
    };

    $upload = UploadedFile::fake()->createWithContent(
        'community-policy-note.txt',
        "ATTP community policy discussion note.\n"
    );
    $request = $makeUpdateRequest([], ['document_uploads' => [$upload]]);
    $controller->update($request, $topic);

    $document = $topic->uploadedDocuments()->firstOrFail();
    if (! Storage::disk('local')->exists($document->storage_path)
        || $document->file_name !== 'community-policy-note.txt'
        || $document->mime_type !== 'text/plain') {
        throw new RuntimeException('The administrator upload was not stored with safe file metadata.');
    }

    $apiRequest = Request::create(
        "/api/discussions/topics/{$topic->slug}",
        'GET',
        [],
        [],
        [],
        [
            'HTTP_HOST' => '127.0.0.1:8000',
            'SERVER_PORT' => 8000,
            'HTTP_ACCEPT' => 'application/json',
        ]
    );
    $app['auth']->forgetGuards();
    $app->instance('request', $apiRequest);
    $apiResponse = $kernel->handle($apiRequest);
    $payload = json_decode((string) $apiResponse->getContent(), true);
    $kernel->terminate($apiRequest, $apiResponse);

    $publicDocument = collect($payload['data']['documents'] ?? [])->firstWhere('id', $document->id);
    if ($apiResponse->getStatusCode() !== 200
        || ! is_array($publicDocument)
        || empty($publicDocument['view_url'])
        || empty($publicDocument['download_url'])
        || ($publicDocument['source'] ?? null) !== 'upload') {
        throw new RuntimeException('The public discussion API did not expose safe read and download document URLs.');
    }

    $serializedDocument = json_encode($publicDocument, JSON_THROW_ON_ERROR);
    if (str_contains($serializedDocument, $document->storage_path) || str_contains($serializedDocument, 'storage_path')) {
        throw new RuntimeException('A private uploaded-document storage path leaked through the public API.');
    }

    foreach ([
        'read' => route('discussion.documents.read', $document, false),
        'download' => route('discussion.documents.download', $document, false),
    ] as $mode => $url) {
        $fileRequest = Request::create($url, 'GET', [], [], [], [
            'HTTP_HOST' => '127.0.0.1:8000',
            'SERVER_PORT' => 8000,
        ]);
        $app['auth']->forgetGuards();
        $app->instance('request', $fileRequest);
        $fileResponse = $kernel->handle($fileRequest);
        $disposition = (string) $fileResponse->headers->get('Content-Disposition');
        $kernel->terminate($fileRequest, $fileResponse);

        if ($fileResponse->getStatusCode() !== 200
            || ! str_contains($disposition, $mode === 'read' ? 'inline' : 'attachment')
            || $fileResponse->headers->get('X-Content-Type-Options') !== 'nosniff') {
            throw new RuntimeException(sprintf(
                'The public document %s response is not secure or available (status=%d, disposition=%s, nosniff=%s, location=%s).',
                $mode,
                $fileResponse->getStatusCode(),
                $disposition,
                (string) $fileResponse->headers->get('X-Content-Type-Options'),
                (string) $fileResponse->headers->get('Location')
            ));
        }
    }

    $oldPath = $document->storage_path;
    $replacement = UploadedFile::fake()->createWithContent(
        'updated-community-policy-note.txt',
        "Updated ATTP community policy discussion note.\n"
    );
    $request = $makeUpdateRequest(
        [
            'uploaded_documents' => [
                $document->id => [
                    'title' => 'Updated community policy note',
                    'description' => 'A revised supporting note.',
                    'remove' => '0',
                ],
            ],
        ],
        [
            'uploaded_documents' => [
                $document->id => ['replacement' => $replacement],
            ],
        ]
    );
    $controller->update($request, $topic);
    $document->refresh();

    if (Storage::disk('local')->exists($oldPath)
        || ! Storage::disk('local')->exists($document->storage_path)
        || $document->title !== 'Updated community policy note') {
        throw new RuntimeException('Replacing an uploaded discussion document did not update and clean up the file.');
    }

    $replacementPath = $document->storage_path;
    $request = $makeUpdateRequest([
        'uploaded_documents' => [
            $document->id => [
                'title' => $document->title,
                'description' => $document->description,
                'remove' => '1',
            ],
        ],
    ]);
    $controller->update($request, $topic);

    if (DiscussionTopicDocument::query()->whereKey($document->id)->exists()
        || Storage::disk('local')->exists($replacementPath)) {
        throw new RuntimeException('Removing an uploaded discussion document did not clean up its private file.');
    }

    echo "DISCUSSION_DOCUMENT_UPLOAD_OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
} finally {
    DB::rollBack();
}
