<?php

use App\Models\Role;
use App\Models\User;
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
        'name' => 'Optional Governance Think Tank User',
        'email' => 'optional-governance-' . bin2hex(random_bytes(4)) . '@example.test',
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

    $request = Request::create("/system/users/{$target->id}", 'POST', [
        '_token' => $token,
        '_method' => 'PUT',
        'name' => $target->name,
        'email' => $target->email,
        'role_id' => $role->id,
        'user_type' => 'think_tank',
        'governance_node_id' => '',
        'member_state_id' => '',
    ]);
    $request->setLaravelSession($session);

    $response = $app->make(HttpKernel::class)->handle($request);

    if (! in_array($response->getStatusCode(), [302, 303], true)) {
        fwrite(STDERR, "Expected redirect from user update, got {$response->getStatusCode()}.\n");
        fwrite(STDERR, substr((string) $response->getContent(), 0, 1200) . "\n");
        exit(1);
    }

    $target->refresh();

    if ($target->user_type !== 'think_tank') {
        fwrite(STDERR, "User type was not preserved as think_tank.\n");
        exit(1);
    }

    if ($target->governance_node_id !== null) {
        fwrite(STDERR, "Governance node should be optional and remain null.\n");
        exit(1);
    }

    echo "USER_EDIT_OPTIONAL_GOVERNANCE_OK\n";
} finally {
    DB::rollBack();
}
