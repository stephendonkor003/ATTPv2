<?php

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$paths = [
    '/' => ['ATTP', 'News', 'Login'],
    '/events' => ['Events', 'Login'],
    '/news' => ['News', 'Subscribe'],
    '/careers' => ['Careers', 'Login'],
    '/public/procurement' => ['Procurement', 'Login'],
    '/impact-map' => ['Impact'],
    '/world-indicators-performance' => ['World'],
];

foreach ($paths as $path => $needles) {
    $request = Request::create($path, 'GET', [], [], [], [
        'HTTP_HOST' => '127.0.0.1:8000',
        'SERVER_PORT' => 8000,
    ]);
    $response = $app->make(HttpKernel::class)->handle($request);
    $content = (string) $response->getContent();

    if ($response->getStatusCode() !== 200) {
        fwrite(STDERR, "Expected 200 from {$path}, got {$response->getStatusCode()}.\n");
        fwrite(STDERR, substr($content, 0, 1200) . "\n");
        exit(1);
    }

    foreach ($needles as $needle) {
        if (! str_contains($content, $needle)) {
            fwrite(STDERR, "Public page {$path} is missing expected text: {$needle}\n");
            exit(1);
        }
    }
}

$homeRequest = Request::create('/', 'GET', [], [], [], [
    'HTTP_HOST' => '127.0.0.1:8000',
    'SERVER_PORT' => 8000,
]);
$homeResponse = $app->make(HttpKernel::class)->handle($homeRequest);
$home = (string) $homeResponse->getContent();

if (! str_contains($home, 'http://127.0.0.1:8000/assets/style.css')) {
    fwrite(STDERR, "Homepage assets are not using APP_URL http://127.0.0.1:8000.\n");
    exit(1);
}

echo "PUBLIC_PAGES_OK\n";
