<?php

$origins = array_values(array_filter(array_map(
    static fn (string $origin): string => rtrim(trim($origin), '/'),
    explode(',', (string) env(
        'THINK_TANK_PORTAL_ALLOWED_ORIGINS',
        'http://localhost:3000,http://localhost:3100,http://127.0.0.1:3000,http://127.0.0.1:3100'
    ))
)));

return [
    'paths' => ['api/v1/think-tank/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'OPTIONS'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => [
        'Accept',
        'Content-Type',
        'Origin',
        'X-Requested-With',
        'X-XSRF-TOKEN',
    ],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => true,
];
