<?php

it('keeps the former ATTP generated code workflow disabled by default', function () {
    $root = dirname(__DIR__, 2);
    $config = file_get_contents($root.'/config/api_sync.php');
    $apiRoutes = file_get_contents($root.'/routes/api.php');
    $webRoutes = file_get_contents($root.'/routes/web.php');
    $service = file_get_contents($root.'/app/Services/ApiSync/ApiSyncPairingService.php');
    $roleSeeder = file_get_contents($root.'/database/seeders/RolePermissionSeeder.php');
    $view = file_get_contents($root.'/resources/views/system/api-sync/index.blade.php');
    $example = file_get_contents($root.'/.env.api-sync.example');

    expect($config)
        ->toContain("env('ATTP_API_SYNC_V1_ENABLED', false)")
        ->and($apiRoutes)->toContain("config('api_sync.legacy_v1_enabled', false)")
        ->and($webRoutes)->toContain("config('api_sync.legacy_v1_enabled', false)")
        ->and($service)->toContain('legacy_api_sync_disabled')
        ->and($roleSeeder)->toContain("config('api_sync.legacy_v1_enabled', false)")
        ->and($view)->toContain('@if($legacyV1Enabled)')
        ->and($example)->toContain('ATTP_API_SYNC_V1_ENABLED=false');
});
