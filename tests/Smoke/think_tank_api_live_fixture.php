<?php

use App\Models\Consortium;
use App\Models\ConsortiumThinkTank;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

[$app, $runDirectory] = require __DIR__.'/think_tank_api_live_bootstrap.php';
if (is_file($runDirectory.'/database.sqlite')) {
    throw new RuntimeException('Refusing to overwrite an existing fixture; choose a fresh run identifier.');
}
touch($runDirectory.'/database.sqlite');

// A focused schema for this API contract. Full application migrations are verified
// separately; no application database is copied, reset, migrated, or seeded here.
Schema::create('roles', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->string('name')->unique();
    $table->text('description')->nullable();
    $table->timestamps();
});
Schema::create('attp_consortia', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('code')->unique();
    $table->string('status')->default('active');
    $table->timestamps();
});
Schema::create('attp_consortium_think_tanks', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->foreignUuid('consortium_id')->constrained('attp_consortia');
    $table->uuid('portal_user_id')->nullable()->unique();
    $table->string('name');
    $table->string('status')->default('active');
    $table->timestamps();
});
Schema::create('users', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->string('user_type');
    $table->foreignUuid('role_id')->nullable()->constrained('roles');
    $table->foreignUuid('think_tank_member_id')->nullable()->constrained('attp_consortium_think_tanks');
    $table->string('think_tank_access_level')->nullable();
    $table->boolean('must_change_password')->default(false);
    $table->boolean('is_disabled')->default(false);
    $table->boolean('is_blacklisted')->default(false);
    foreach (['email_verified_at', 'password_changed_at', 'otp_verified_at', 'disabled_at', 'disabled_until', 'blacklisted_at'] as $column) {
        $table->timestamp($column)->nullable();
    }
    $table->text('disabled_reason')->nullable();
    $table->text('blacklisted_reason')->nullable();
    $table->rememberToken();
    $table->timestamps();
});
Schema::create('user_login_otps', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->foreignUuid('user_id')->constrained('users');
    $table->string('otp_code', 64);
    $table->string('session_id');
    $table->timestamp('expires_at');
    $table->timestamp('verified_at')->nullable();
    $table->string('ip_address')->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamps();
});
Schema::create('sessions', function (Blueprint $table): void {
    $table->string('id')->primary();
    $table->uuid('user_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->longText('payload');
    $table->integer('last_activity')->index();
});
Schema::create('cache', function (Blueprint $table): void {
    $table->string('key')->primary();
    $table->mediumText('value');
    $table->integer('expiration');
});
Schema::create('cache_locks', function (Blueprint $table): void {
    $table->string('key')->primary();
    $table->string('owner');
    $table->integer('expiration');
});
Schema::create('password_reset_tokens', function (Blueprint $table): void {
    $table->string('email')->primary();
    $table->string('token');
    $table->timestamp('created_at')->nullable();
});
Schema::create('system_audit_logs', function (Blueprint $table): void {
    $table->uuid('id')->primary();
    $table->uuid('user_id')->nullable();
    foreach (['module', 'action', 'action_message', 'description', 'method', 'url', 'route_name', 'ip_address', 'country', 'user_agent'] as $column) {
        $table->text($column)->nullable();
    }
    $table->integer('status_code')->nullable();
    $table->json('payload')->nullable();
    $table->timestamps();
});

$role = Role::create(['name' => 'Think Tank User']);
$consortium = Consortium::create(['name' => 'Smoke Test Consortium', 'code' => 'SMOKE']);
$tenantA = ConsortiumThinkTank::create(['consortium_id' => $consortium->id, 'name' => 'Smoke Think Tank Alpha', 'status' => 'active']);
$tenantB = ConsortiumThinkTank::create(['consortium_id' => $consortium->id, 'name' => 'Smoke Think Tank Beta', 'status' => 'active']);
$password = 'Smoke-'.bin2hex(random_bytes(12)).'!9';
$accounts = [];
foreach ([
    'admin' => [$tenantA, User::THINK_TANK_ACCESS_ADMIN, true],
    'officer' => [$tenantA, User::THINK_TANK_ACCESS_PROCUREMENT, false],
    'foreign' => [$tenantB, User::THINK_TANK_ACCESS_ADMIN, false],
] as $name => [$tenant, $level, $mustChange]) {
    $user = User::create([
        'name' => 'Smoke '.ucfirst($name), 'email' => $name.'@example.test',
        'password' => $password, 'user_type' => 'think_tank', 'role_id' => $role->id,
        'think_tank_member_id' => $tenant->id, 'think_tank_access_level' => $level,
        'must_change_password' => $mustChange, 'password_changed_at' => now(),
    ]);
    if ($level === User::THINK_TANK_ACCESS_ADMIN) {
        $tenant->update(['portal_user_id' => $user->id]);
    }
    $accounts[$name] = ['id' => $user->id, 'email' => $user->email, 'tenant_id' => $tenant->id];
}
file_put_contents($runDirectory.'/fixture.json', json_encode([
    'accounts' => $accounts, 'password' => $password,
    'new_password' => 'Changed-'.bin2hex(random_bytes(12)).'!8',
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
echo json_encode(['directory' => $runDirectory], JSON_THROW_ON_ERROR).PHP_EOL;
