<?php

use App\Models\DiscussionTopic;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$kernel = $app->make(HttpKernel::class);

DB::beginTransaction();

try {
    $topic = DiscussionTopic::query()->where('status', 'open')->firstOrFail();
    $topic->forceFill([
        'related_links' => [
            [
                'title' => '<strong>Official AU resource</strong>',
                'url' => 'https://au.int/',
                'description' => '<em>Trusted institutional source.</em>',
                'type' => 'link',
            ],
            [
                'title' => 'Unsafe link',
                'url' => 'javascript:alert(1)',
                'type' => 'link',
            ],
        ],
        'materials' => [
            [
                'title' => 'Agenda 2063 overview',
                'url' => 'https://au.int/en/agenda2063/overview',
                'description' => 'Official background material.',
                'type' => 'guidance',
            ],
        ],
        'documents' => [
            [
                'title' => 'ATTP Community Discussion Guide',
                'url' => '/assets/attp-community-discussion-guide.txt',
                'description' => 'Local participation guide.',
                'type' => 'guide',
            ],
        ],
    ])->save();

    $topic->refresh();

    if (count($topic->related_links) !== 1 || $topic->related_links[0]['title'] !== 'Official AU resource') {
        throw new RuntimeException('Discussion resource normalization did not remove unsafe URLs and HTML markup.');
    }

    if (! DiscussionTopic::isSafeResourceUrl('/assets/attp-community-discussion-guide.txt')
        || DiscussionTopic::isSafeResourceUrl('//example.test/unsafe')
        || DiscussionTopic::isSafeResourceUrl('javascript:alert(1)')) {
        throw new RuntimeException('Discussion resource URL safety rules are not working correctly.');
    }

    if (! is_file(public_path('assets/attp-community-discussion-guide.txt'))) {
        throw new RuntimeException('The seeded ATTP community discussion guide is missing.');
    }

    $request = Request::create(
        "/api/discussions/topics/{$topic->slug}",
        'GET',
        [],
        [],
        [],
        [
            'HTTP_HOST' => '127.0.0.1:8000',
            'SERVER_PORT' => 8000,
            'HTTP_ACCEPT' => 'application/json',
            'REMOTE_ADDR' => '198.51.100.27',
        ]
    );

    $response = $kernel->handle($request);
    $payload = json_decode((string) $response->getContent(), true);
    $kernel->terminate($request, $response);

    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException("Discussion resource API returned HTTP {$response->getStatusCode()}.");
    }

    $data = $payload['data'] ?? [];
    foreach (['related_links', 'materials', 'documents'] as $collection) {
        if (! isset($data[$collection]) || ! is_array($data[$collection])) {
            throw new RuntimeException("Discussion detail is missing the normalized {$collection} array.");
        }
    }

    if (($data['related_links'][0]['title'] ?? null) !== 'Official AU resource'
        || ($data['materials'][0]['type'] ?? null) !== 'guidance'
        || ($data['documents'][0]['url'] ?? null) !== '/assets/attp-community-discussion-guide.txt') {
        throw new RuntimeException('Discussion detail did not serialize topic resources correctly.');
    }

    $serializedResources = json_encode([
        $data['related_links'],
        $data['materials'],
        $data['documents'],
    ], JSON_THROW_ON_ERROR);

    if (str_contains($serializedResources, '<') || str_contains($serializedResources, 'javascript:')) {
        throw new RuntimeException('Unsafe resource markup or schemes reached the public discussion API.');
    }

    echo "DISCUSSION_RESOURCES_OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
} finally {
    DB::rollBack();
}
