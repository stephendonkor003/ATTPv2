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

$kernel = $app->make(HttpKernel::class);

$request = Request::create('/dashboard', 'GET');
$request->setLaravelSession($session);
$response = $kernel->handle($request);

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, "Dashboard failed before sidebar sweep: {$response->getStatusCode()}\n");
    exit(1);
}

$html = (string) $response->getContent();
preg_match_all('/href="([^"]+)"/', $html, $matches);

$paths = collect($matches[1])
    ->map(function (string $href) {
        if (str_starts_with($href, 'http://localhost')) {
            return parse_url($href, PHP_URL_PATH) ?: '/';
        }

        if (str_starts_with($href, url('/'))) {
            return parse_url($href, PHP_URL_PATH) ?: '/';
        }

        if (str_starts_with($href, '/')) {
            return $href;
        }

        return null;
    })
    ->filter()
    ->reject(fn (string $path) => $path === '/'
        || str_starts_with($path, '/language/')
        || str_starts_with($path, '/admin/assets/')
        || str_starts_with($path, '/assets/'))
    ->unique()
    ->values();

$failures = [];

foreach ($paths as $path) {
    $request = Request::create($path, 'GET');
    $request->setLaravelSession($session);
    $response = $kernel->handle($request);
    $status = $response->getStatusCode();

    if (! in_array($status, [200, 302], true)) {
        $failures[] = "{$path} => {$status}";
    }
}

if ($failures) {
    fwrite(STDERR, "Sidebar route failures:\n" . implode("\n", $failures) . "\n");
    exit(1);
}

echo "SIDEBAR_ROUTES_OK {$paths->count()}\n";
