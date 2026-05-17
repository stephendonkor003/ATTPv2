<?php

use App\Models\User;
use App\Models\Role;
use Database\Seeders\MasterAdminSeeder;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$admin = User::where('email', MasterAdminSeeder::EMAIL)->firstOrFail();

DB::beginTransaction();

try {
    $role = Role::firstOrCreate(['name' => 'Think Tank User'], ['description' => 'Think tank user']);
    $target = User::create([
        'name' => 'Fallback Reset User',
        'email' => 'fallback-reset-' . bin2hex(random_bytes(4)) . '@example.test',
        'password' => bcrypt('Password123!'),
        'user_type' => 'think_tank',
        'role_id' => $role->id,
        'must_change_password' => false,
    ]);

    $session = $app['session.store'];
    $session->start();
    Auth::login($admin);
    $token = bin2hex(random_bytes(20));
    $session->put('_token', $token);
    $session->save();

    $request = Request::create("/system/users/{$target->id}/reset-password", 'POST', [
        '_token' => $token,
    ]);
    $request->setLaravelSession($session);

    $response = $app->make(HttpKernel::class)->handle($request);

    if (! in_array($response->getStatusCode(), [302, 303], true)) {
        fwrite(STDERR, "Expected redirect from password reset, got {$response->getStatusCode()}.\n");
        fwrite(STDERR, substr((string) $response->getContent(), 0, 1200) . "\n");
        exit(1);
    }

    $session->start();
    $success = (string) $session->get('success', '');

    if (! str_contains($success, 'Password reset successfully') && ! str_contains($success, 'Password reset and emailed successfully')) {
        fwrite(STDERR, "Password reset did not produce expected success message: {$success}\n");
        exit(1);
    }

    echo "USER_PASSWORD_RESET_MAIL_FALLBACK_OK\n";
} finally {
    DB::rollBack();
}
