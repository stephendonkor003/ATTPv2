<?php

use App\Http\Controllers\System\DiscussionAdminController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$routeNames = [
    'system.discussions.dashboard',
    'system.discussions.topics.index',
    'system.discussions.topics.create',
    'system.discussions.topics.store',
    'system.discussions.topics.edit',
    'system.discussions.topics.update',
    'system.discussions.topics.status',
    'system.discussions.topics.documents.open',
    'system.discussions.themes.index',
    'system.discussions.themes.store',
    'system.discussions.themes.update',
    'system.discussions.themes.destroy',
    'system.discussions.participants.index',
    'system.discussions.participants.block',
    'system.discussions.participants.unblock',
    'system.discussions.participants.revoke',
    'system.discussions.moderation.index',
    'system.discussions.moderation.live',
    'system.discussions.moderation.live.feed',
    'system.discussions.moderation.remove',
];

foreach ($routeNames as $routeName) {
    if (! Route::has($routeName)) {
        fwrite(STDERR, "Missing discussion administration route: {$routeName}\n");
        exit(1);
    }
}

foreach (['system.discussions.moderation.approve', 'system.discussions.moderation.reject'] as $obsoleteRoute) {
    if (Route::has($obsoleteRoute)) {
        fwrite(STDERR, "Obsolete pre-publication moderation route still exists: {$obsoleteRoute}\n");
        exit(1);
    }
}

$admin = User::query()
    ->whereHas('role', fn ($query) => $query->where('name', 'System Admin'))
    ->first();

if (! $admin) {
    fwrite(STDERR, "A System Admin user is required for the discussion administration smoke test.\n");
    exit(1);
}

$app['auth']->guard()->setUser($admin);
$app['view']->share('errors', new ViewErrorBag());
$request = Request::create('/system/discussions', 'GET');
$request->setUserResolver(fn () => $admin);
$app->instance('request', $request);

$controller = $app->make(DiscussionAdminController::class);
$pages = [
    'dashboard' => fn () => $controller->dashboard(),
    'discussion list' => fn () => $controller->index($request),
    'create discussion' => fn () => $controller->create(),
    'thematic areas' => fn () => $controller->themes(),
    'participants' => fn () => $controller->participants($request),
    'moderation history' => fn () => $controller->moderation($request),
    'live moderation' => fn () => $controller->liveModeration(),
];

foreach ($pages as $label => $page) {
    try {
        $html = $page()->render();
    } catch (Throwable $exception) {
        fwrite(STDERR, "Could not render {$label}: {$exception->getMessage()}\n");
        exit(1);
    }

    if (! str_contains($html, 'Discussion Controls')) {
        fwrite(STDERR, "The {$label} page is missing the Discussion Controls navigation.\n");
        exit(1);
    }

    if ($label === 'live moderation') {
        foreach ([
            'Live Discussion Monitor',
            'data-poll-interval="4000"',
            'data-request-timeout="8000"',
            'data-stats-refresh-interval="15000"',
            'data-full-refresh-interval="60000"',
            'data-max-cards="40"',
            'system/discussions/moderation/live/feed',
            'assets/js/discussion-live-moderation.js',
            'discussion-live-moderation.js?v=',
        ] as $liveMarkup) {
            if (! str_contains($html, $liveMarkup)) {
                fwrite(STDERR, "The live moderation page is missing {$liveMarkup}.\n");
                exit(1);
            }
        }

        foreach ([
            'admin/assets/vendors/js/vendors.min.js' => 1,
            'admin/assets/js/common-init.min.js' => 1,
        ] as $leanScript => $expectedCount) {
            if (substr_count($html, $leanScript) !== $expectedCount) {
                fwrite(STDERR, "The live moderation page did not load {$leanScript} exactly once.\n");
                exit(1);
            }
        }

        foreach (['dataTables.min.js', 'select2.min.js', 'quill.min.js', 'apexcharts.min.js'] as $unusedScript) {
            if (str_contains($html, $unusedScript)) {
                fwrite(STDERR, "The live moderation page still loads the unused {$unusedScript} bundle.\n");
                exit(1);
            }
        }
    }

    if ($label === 'create discussion') {
        foreach (['related_links[0][title]', 'materials[0][title]', 'documents[0][title]'] as $resourceField) {
            if (! str_contains($html, 'name="'.$resourceField.'"')) {
                fwrite(STDERR, "The discussion editor is missing the {$resourceField} resource field.\n");
                exit(1);
            }
        }

        foreach (['enctype="multipart/form-data"', 'name="document_uploads[]"'] as $uploadMarkup) {
            if (! str_contains($html, $uploadMarkup)) {
                fwrite(STDERR, "The discussion editor is missing uploaded-document support ({$uploadMarkup}).\n");
                exit(1);
            }
        }
    }
}

echo "DISCUSSION_ADMIN_OK\n";
