<?php

// php -S 127.0.0.1:3198 tests/Smoke/think_tank_api_live_router.php
if (PHP_SAPI !== 'cli-server' || ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit;
}

[$app, $runDirectory] = require __DIR__.'/think_tank_api_live_bootstrap.php';
if (! is_file($runDirectory.'/fixture.json')) {
    throw new RuntimeException('Create the isolated smoke fixture before starting HTTP requests.');
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($path !== '/sanctum/csrf-cookie' && ! str_starts_with((string) $path, '/api/v1/think-tank/')) {
    http_response_code(404);
    exit;
}

$request = Illuminate\Http\Request::capture();
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
