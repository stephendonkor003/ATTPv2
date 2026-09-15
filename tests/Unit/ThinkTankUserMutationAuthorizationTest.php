<?php

use App\Data\ThinkTank\UpdateThinkTankUserData;
use App\Exceptions\ThinkTankApiException;
use App\Models\ConsortiumThinkTank;
use App\Models\User;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTank\ThinkTankInvitationService;
use App\Services\ThinkTank\ThinkTankSessionService;
use App\Services\ThinkTank\ThinkTankUserManagementService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

it('rechecks effective account and tenant authority before updating another staff member', function (
    array $actorChanges,
    string $tenantStatus,
    bool $allowed,
) {
    $bootedHere = ! Container::getInstance()->bound(Kernel::class);

    if ($bootedHere) {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $application->make(Kernel::class)->bootstrap();
    }

    $connectionName = 'think_tank_mutation_test';
    $originalConnection = config('database.default');
    $originalDefinition = config('database.connections.'.$connectionName);
    $originalLockStore = config('think_tank_portal.email_lock_store');
    config([
        'database.default' => $connectionName,
        'database.connections.'.$connectionName => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'think_tank_portal.email_lock_store' => 'array',
    ]);

    try {
        $connection = DB::connection($connectionName);
        expect($connection->getDatabaseName())->toBe(':memory:')
            ->and(DB::getDefaultConnection())->toBe($connectionName);

        $schema = $connection->getSchemaBuilder();
        $schema->create('attp_consortium_think_tanks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status');
        });
        $schema->create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('user_type');
            $table->uuid('think_tank_member_id');
            $table->string('think_tank_access_level');
            $table->boolean('is_disabled')->default(false);
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamp('disabled_until')->nullable();
            $table->timestamps();
        });

        $tenantId = '10000000-0000-4000-8000-000000000001';
        $actorId = '10000000-0000-4000-8000-000000000002';
        $targetId = '10000000-0000-4000-8000-000000000003';
        $connection->table('attp_consortium_think_tanks')->insert([
            'id' => $tenantId,
            'status' => 'active',
        ]);
        $connection->table('users')->insert([
            'id' => $actorId,
            'name' => 'Administrator',
            'email' => 'administrator@example.test',
            'user_type' => 'think_tank',
            'think_tank_member_id' => $tenantId,
            'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
        ]);
        $connection->table('users')->insert([
            'id' => $targetId,
            'name' => 'Original staff name',
            'email' => 'staff@example.test',
            'user_type' => 'think_tank',
            'think_tank_member_id' => $tenantId,
            'think_tank_access_level' => User::THINK_TANK_ACCESS_FINANCE,
        ]);
        $tenant = ConsortiumThinkTank::query()->findOrFail($tenantId);
        $actor = User::query()->findOrFail($actorId);
        $target = User::query()->findOrFail($targetId);

        // Simulate authority changing after request middleware loaded the actor.
        if ($actorChanges !== []) {
            $connection->table('users')->where('id', $actorId)->update($actorChanges);
        }
        $connection->table('attp_consortium_think_tanks')->where('id', $tenantId)->update([
            'status' => $tenantStatus,
        ]);

        $audit = Mockery::mock(ThinkTankApiAuditService::class);
        $audit->shouldReceive('required')->times($allowed ? 1 : 0);
        $service = new ThinkTankUserManagementService(
            $audit,
            Mockery::mock(ThinkTankInvitationService::class),
            Mockery::mock(ThinkTankSessionService::class),
        );
        $update = fn () => User::withoutEvents(fn () => $service->update(
            Request::create('/api/v1/think-tank/users/'.$targetId, 'PATCH'),
            $actor,
            $tenant,
            $target,
            UpdateThinkTankUserData::from(['name' => 'Updated staff name']),
        ));

        if ($allowed) {
            expect($update()['user']->name)->toBe('Updated staff name');
        } else {
            expect($update)->toThrow(ThinkTankApiException::class);
        }

        expect($connection->table('users')->where('id', $targetId)->value('name'))
            ->toBe($allowed ? 'Updated staff name' : 'Original staff name');
    } finally {
        DB::purge($connectionName);
        config([
            'database.default' => $originalConnection,
            'database.connections.'.$connectionName => $originalDefinition,
            'think_tank_portal.email_lock_store' => $originalLockStore,
        ]);
        Mockery::close();

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
})->with([
    'active administrator' => [[], 'active', true],
    'expired temporary block' => [['is_disabled' => true, 'disabled_until' => '2000-01-01 00:00:00'], 'active', true],
    'current temporary block' => [['is_disabled' => true, 'disabled_until' => '2999-01-01 00:00:00'], 'active', false],
    'permanent block' => [['is_disabled' => true, 'disabled_until' => null], 'active', false],
    'blacklisted administrator' => [['is_blacklisted' => true], 'active', false],
    'reassigned administrator' => [['think_tank_member_id' => '20000000-0000-4000-8000-000000000001'], 'active', false],
    'demoted administrator' => [['think_tank_access_level' => User::THINK_TANK_ACCESS_FINANCE], 'active', false],
    'inactive tenant' => [[], 'inactive', false],
])->skip(! extension_loaded('pdo_sqlite'), 'Enable pdo_sqlite to run isolated database authorization tests.');
