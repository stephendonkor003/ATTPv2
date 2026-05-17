<?php

use App\Models\User;
use Database\Seeders\MasterAdminSeeder;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$user = User::where('email', MasterAdminSeeder::EMAIL)->firstOrFail();

$session = $app['session.store'];
$session->start();
Auth::login($user);
$session->save();

$request = Request::create('/dashboard', 'GET');
$request->setLaravelSession($session);

$response = $app->make(HttpKernel::class)->handle($request);
$content = $response->getContent();

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "Expected 200 from /dashboard, got {$response->getStatusCode()}.\n");
    fwrite(STDERR, substr((string) $content, 0, 1200) . "\n");
    exit(1);
}

foreach (['Dashboard', 'Command Center', 'Quick Links'] as $needle) {
    if (! str_contains((string) $content, $needle)) {
        fwrite(STDERR, "Dashboard response is missing expected text: {$needle}\n");
        exit(1);
    }
}

$request = Request::create('/consortium-operations', 'GET');
$request->setLaravelSession($session);
$response = $app->make(HttpKernel::class)->handle($request);
$content = $response->getContent();

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "Expected 200 from /consortium-operations, got {$response->getStatusCode()}.\n");
    fwrite(STDERR, substr((string) $content, 0, 1200) . "\n");
    exit(1);
}

foreach (['Consortium Operations', 'Think Tank Management'] as $needle) {
    if (! str_contains((string) $content, $needle)) {
        fwrite(STDERR, "Consortium response is missing expected text: {$needle}\n");
        exit(1);
    }
}

$request = Request::create('/system/attp-ai-guide/settings', 'GET');
$request->setLaravelSession($session);
$response = $app->make(HttpKernel::class)->handle($request);
$content = $response->getContent();

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "Expected 200 from /system/attp-ai-guide/settings, got {$response->getStatusCode()}.\n");
    fwrite(STDERR, substr((string) $content, 0, 1200) . "\n");
    exit(1);
}

foreach (['ATTP AI Guide Settings', 'Configuration'] as $needle) {
    if (! str_contains((string) $content, $needle)) {
        fwrite(STDERR, "AI Guide response is missing expected text: {$needle}\n");
        exit(1);
    }
}

$systemPaths = [
    '/system/audit',
    '/system/communications',
    '/system/national-data-reviews',
    '/system/permissions',
    '/system/roles',
    '/system/users',
    '/system/news',
    '/system/news/create',
];

foreach ($systemPaths as $path) {
    $request = Request::create($path, 'GET');
    $request->setLaravelSession($session);
    $response = $app->make(HttpKernel::class)->handle($request);

    if ($response->getStatusCode() !== 200) {
        fwrite(STDERR, "Expected 200 from {$path}, got {$response->getStatusCode()}.\n");
        fwrite(STDERR, substr((string) $response->getContent(), 0, 1200) . "\n");
        exit(1);
    }
}

echo "DASHBOARD_OK\n";
