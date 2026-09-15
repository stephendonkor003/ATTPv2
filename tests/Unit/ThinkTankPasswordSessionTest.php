<?php

use App\Exceptions\ThinkTankApiException;
use App\Http\Controllers\Api\V1\ThinkTank\PasswordController;
use App\Models\ConsortiumThinkTank;
use App\Models\User;
use App\Services\ThinkTank\ThinkTankAccountAccessService;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTank\ThinkTankAuthenticationStateService;
use App\Services\ThinkTank\ThinkTankInvitationService;
use App\Services\ThinkTank\ThinkTankMfaService;
use App\Services\ThinkTank\ThinkTankSessionService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

it('preserves the current Sanctum session after password change and MFA delivery', function (bool $mailFails) {
    $bootedHere = ! Container::getInstance()->bound(Kernel::class);

    if ($bootedHere) {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $application->make(Kernel::class)->bootstrap();
    }

    $connectionName = 'think_tank_password_test';
    $original = [
        'database.default' => config('database.default'),
        'database.connections.'.$connectionName => config('database.connections.'.$connectionName),
        'session.driver' => config('session.driver'),
        'sanctum.guard' => config('sanctum.guard'),
        'think_tank_portal.require_mfa' => config('think_tank_portal.require_mfa'),
    ];
    config([
        'database.default' => $connectionName,
        'database.connections.'.$connectionName => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ],
        'session.driver' => 'array',
        'sanctum.guard' => ['web'],
        'think_tank_portal.require_mfa' => true,
    ]);
    Auth::forgetGuards();

    try {
        $connection = DB::connection($connectionName);
        expect($connection->getDatabaseName())->toBe(':memory:');
        $schema = $connection->getSchemaBuilder();
        $schema->create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email');
            $table->string('password');
            $table->string('user_type');
            $table->uuid('think_tank_member_id');
            $table->string('think_tank_access_level');
            $table->boolean('must_change_password')->default(true);
            $table->boolean('is_disabled')->default(false);
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        $schema->create('user_login_otps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
        });
        $id = '10000000-0000-4000-8000-000000000001';
        $oldPassword = 'Original-Password!77';
        $newPassword = 'Updated-Password!88';
        $connection->table('users')->insert([
            'id' => $id,
            'email' => 'password-session@example.test',
            'password' => Hash::make($oldPassword),
            'user_type' => 'think_tank',
            'think_tank_member_id' => '10000000-0000-4000-8000-000000000002',
            'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
        ]);
        $guard = Auth::guard('web');
        $guard->setUser(User::query()->findOrFail($id));
        $session = new Store('password-session-test', new ArraySessionHandler(120));
        $oldSessionHash = $guard->hashPasswordForCookie($guard->user()->getAuthPassword());
        $session->put('password_hash_web', $oldSessionHash);
        $request = Request::create('/api/v1/think-tank/auth/password', 'PUT', server: [
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode([
            'current_password' => $oldPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ], JSON_THROW_ON_ERROR));
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $guard->user());

        $accounts = Mockery::mock(ThinkTankAccountAccessService::class);
        $accounts->shouldReceive('membership')->once()->andReturn(new ConsortiumThinkTank);
        $audit = Mockery::mock(ThinkTankApiAuditService::class);
        $audit->shouldReceive('required')->once();
        $mfa = Mockery::mock(ThinkTankMfaService::class);
        $delivery = $mfa->shouldReceive('send')->once();
        if ($mailFails) {
            $delivery->andThrow(new ThinkTankApiException('MFA_DELIVERY_FAILED', 'Local fixture delivery failed.', 503));
        } else {
            $delivery->andReturn(['sent' => true]);
        }
        $sessions = new ThinkTankSessionService;
        $controller = new PasswordController(
            $accounts,
            new ThinkTankAuthenticationStateService,
            Mockery::mock(ThinkTankInvitationService::class),
            $sessions,
            $mfa,
            $audit,
        );
        $middleware = new AuthenticateSession(app('auth'));
        $changePassword = fn () => User::withoutEvents(fn () => $middleware->handle(
            $request,
            fn (Request $request) => $controller->update($request),
        ));
        if ($mailFails) {
            expect($changePassword)->toThrow(ThinkTankApiException::class);
        } else {
            expect($changePassword()->getData(true)['data']['state'])->toBe('MFA_REQUIRED');
        }

        // Simulate the next HTTP request reloading the account from its database.
        $freshUser = User::query()->findOrFail($id);
        $guard->setUser($freshUser);
        $nextRequest = Request::create('/api/v1/think-tank/auth/session', 'GET');
        $nextRequest->setLaravelSession($session);
        $nextRequest->setUserResolver(fn () => $guard->user());
        $response = $middleware->handle($nextRequest, fn () => new Response('authenticated'));

        expect($response->getStatusCode())->toBe(200)
            ->and(Hash::check($newPassword, $freshUser->getAuthPassword()))->toBeTrue()
            ->and($session->get('password_hash_web'))->not->toBe($oldSessionHash)
            ->and($sessions->hasValidCurrentSession($freshUser, $nextRequest))->toBeTrue()
            ->and($session->get('otp_verified', false))->toBeFalse();
    } finally {
        DB::purge($connectionName);
        Auth::forgetGuards();
        config($original);
        Mockery::close();

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
})->with(['successful delivery' => false, 'failed delivery' => true])
    ->skip(! extension_loaded('pdo_sqlite'), 'Enable pdo_sqlite to run isolated password session tests.');
