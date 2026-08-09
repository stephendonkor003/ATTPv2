<?php

use App\Http\Controllers\MeFrameworkController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
view()->share('errors', new ViewErrorBag);
Auth::login(User::query()->whereNotNull('role_id')->firstOrFail());

$view = app(MeFrameworkController::class)->index(
    Request::create('/budget/me/framework', 'GET')
);
$html = $view->render();

$requiredContent = [
    'data-framework-workspace',
    'Results Framework Administration',
    'How to use this workspace',
    'Indicator explorer',
    'data-indicator-search',
    'data-tab-button="configuration"',
    'Indicator Reference Sheet',
    'Targets and allocation history',
    'System calculation rules',
    'data-target-scope',
];

foreach ($requiredContent as $content) {
    if (! str_contains($html, $content)) {
        throw new RuntimeException("Framework workspace is missing required content: {$content}");
    }
}

if (str_contains($html, 'Â·')) {
    throw new RuntimeException('Framework workspace contains a broken middle-dot encoding sequence.');
}

echo "ME_FRAMEWORK_WORKSPACE_SMOKE_OK\n";
