<?php

use App\Http\Controllers\Api\V1\ThinkTank\AuthenticationController;
use App\Http\Controllers\Api\V1\ThinkTank\MfaController;
use App\Models\ConsortiumThinkTank;
use App\Models\User;
use App\Services\PendingLoginService;
use App\Services\ThinkTank\ThinkTankAccountAccessService;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTank\ThinkTankAuthenticationStateService;
use App\Services\ThinkTank\ThinkTankMfaService;
use App\Services\ThinkTank\ThinkTankSessionService;
use Illuminate\Auth\Events\Login;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

it('keeps the API guard unauthenticated until OTP and then requires the password change', function () {
    $bootedHere = ! Container::getInstance()->bound(Kernel::class);

    if ($bootedHere) {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $application->make(Kernel::class)->bootstrap();
    }

    $connectionName = 'think_tank_pending_login_test';
    $original = [
        'database.default' => config('database.default'),
        'database.connections.'.$connectionName => config('database.connections.'.$connectionName),
        'session.driver' => config('session.driver'),
        'hashing.rehash_on_login' => config('hashing.rehash_on_login'),
        'think_tank_portal.require_mfa' => config('think_tank_portal.require_mfa'),
        'think_tank_portal.show_local_otp' => config('think_tank_portal.show_local_otp'),
    ];
    $originalSessionStore = app('session.store');
    $originalEvents = app('events');

    config([
        'database.default' => $connectionName,
        'database.connections.'.$connectionName => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ],
        'session.driver' => 'array',
        'hashing.rehash_on_login' => false,
        'think_tank_portal.require_mfa' => true,
        'think_tank_portal.show_local_otp' => false,
    ]);
    $session = new Store('think-tank-pending-login-test', new ArraySessionHandler(120));
    $session->start();
    app('session')->forgetDrivers();
    app()->instance('session.store', $session);
    app('redirect')->setSession($session);
    // This isolated authentication test owns only the users table. Keep the
    // application-wide model audit listener from requiring its separate audit
    // schema while still asserting the login event below.
    Event::fake();
    Auth::forgetGuards();

    try {
        $connection = DB::connection($connectionName);
        expect($connection->getDatabaseName())->toBe(':memory:');
        $connection->getSchemaBuilder()->create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email');
            $table->string('password');
            $table->string('user_type');
            $table->uuid('think_tank_member_id');
            $table->string('think_tank_access_level');
            $table->boolean('must_change_password')->default(true);
            $table->boolean('is_disabled')->default(false);
            $table->boolean('is_blacklisted')->default(false);
            $table->timestamp('disabled_until')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('otp_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $id = '20000000-0000-4000-8000-000000000001';
        $password = 'Pending-Password!77';
        $connection->table('users')->insert([
            'id' => $id,
            'name' => 'Pending User',
            'email' => 'pending-api@example.test',
            'password' => Hash::make($password),
            'user_type' => 'think_tank',
            'think_tank_member_id' => '20000000-0000-4000-8000-000000000002',
            'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
            'must_change_password' => true,
        ]);

        $membership = (new ConsortiumThinkTank)->forceFill([
            'id' => '20000000-0000-4000-8000-000000000002',
            'name' => 'Pending Test Think Tank',
            'status' => 'active',
        ]);
        $membership->setRelation('consortium', null);
        $accounts = Mockery::mock(ThinkTankAccountAccessService::class);
        $accounts->shouldReceive('membership')->times(4)->andReturn($membership);
        $mfa = Mockery::mock(ThinkTankMfaService::class);
        $mfa->shouldReceive('send')->once()->withArgs(
            fn (Request $request, User $user, bool $force): bool => (string) $user->getKey() === $id && $force,
        )->andReturn([
            'sent' => true,
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
            'resend_available_at' => now()->addMinute()->toIso8601String(),
            'masked_destination' => 'p***@example.test',
        ]);
        $mfa->shouldReceive('verify')->once()->withArgs(
            fn (Request $request, User $user, string $code): bool => (string) $user->getKey() === $id && $code === '123456',
        )->andReturnTrue();
        $audit = Mockery::mock(ThinkTankApiAuditService::class);
        $audit->shouldReceive('bestEffort')->times(3);
        $states = new ThinkTankAuthenticationStateService;
        $sessions = new ThinkTankSessionService;
        $pending = new PendingLoginService;
        $authentication = new AuthenticationController($accounts, $states, $mfa, $sessions, $audit, $pending);
        $mfaController = new MfaController($states, $mfa, $audit, $accounts, $sessions, $pending);
        $guard = Auth::guard('web');

        $makeRequest = function (string $uri, string $method, array $payload = []) use ($session, $guard): Request {
            $request = Request::create(
                $uri,
                $method,
                server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
                content: json_encode($payload, JSON_THROW_ON_ERROR),
            );
            $request->setLaravelSession($session);
            $request->setUserResolver(fn (): ?User => $guard->user());

            return $request;
        };

        $loginRequest = $makeRequest('/api/v1/think-tank/auth/login', 'POST', [
            'email' => 'PENDING-API@example.test',
            'password' => $password,
        ]);
        $loginPayload = $authentication->login($loginRequest)->getData(true)['data'];

        expect($loginPayload)->toMatchArray([
            'state' => 'MFA_REQUIRED',
            'next_action' => 'VERIFY_MFA',
            'user' => null,
        ])->and($guard->check())->toBeFalse()
            ->and($pending->user($loginRequest, PendingLoginService::PURPOSE_THINK_TANK_API)?->getKey())->toBe($id);
        Event::assertNotDispatched(Login::class);

        $sessionPayload = $authentication
            ->session($makeRequest('/api/v1/think-tank/auth/session', 'GET'))
            ->getData(true)['data'];
        expect($sessionPayload)->toHaveKeys(['state', 'next_action', 'user', 'challenge'])
            ->and($sessionPayload['state'])->toBe('MFA_REQUIRED')
            ->and($sessionPayload['next_action'])->toBe('VERIFY_MFA')
            ->and($sessionPayload['user'])->toBeNull()
            ->and($sessionPayload['challenge']['masked_destination'] ?? null)->toBe('p***@example.test')
            ->and($guard->check())->toBeFalse();

        $verifyRequest = $makeRequest('/api/v1/think-tank/auth/mfa/verify', 'POST', ['code' => '123456']);
        $verifiedPayload = $mfaController->verify($verifyRequest)->getData(true)['data'];

        expect($verifiedPayload)->toMatchArray([
            'state' => 'PASSWORD_CHANGE_REQUIRED',
            'next_action' => 'CHANGE_PASSWORD',
            'user' => null,
            'challenge' => null,
        ])->and($guard->id())->toBe($id)
            ->and($pending->user($verifyRequest, PendingLoginService::PURPOSE_THINK_TANK_API))->toBeNull()
            ->and($states->hasValidMfaSession($verifyRequest, $guard->user()))->toBeTrue()
            ->and($sessions->hasValidCurrentSession($guard->user(), $verifyRequest))->toBeTrue();
        Event::assertDispatchedTimes(Login::class, 1);
    } finally {
        DB::purge($connectionName);
        Auth::forgetGuards();
        Event::swap($originalEvents);
        app()->instance('events', $originalEvents);
        config($original);
        app('session')->forgetDrivers();
        app()->instance('session.store', $originalSessionStore);
        app('redirect')->setSession($originalSessionStore);
        Mockery::close();

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
})->skip(! extension_loaded('pdo_sqlite'), 'Enable pdo_sqlite to run isolated pending login tests.');

it('purpose-binds pending API metadata and retains only safe challenge fields', function () {
    $bootedHere = ! Container::getInstance()->bound(Kernel::class);

    if ($bootedHere) {
        $application = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $application->make(Kernel::class)->bootstrap();
    }

    $originalShowCode = config('think_tank_portal.show_local_otp');
    config(['think_tank_portal.show_local_otp' => false]);

    try {
        $session = new Store('think-tank-pending-purpose-test', new ArraySessionHandler(120));
        $session->start();
        $request = Request::create('/api/v1/think-tank/auth/login', 'POST');
        $request->setLaravelSession($session);
        $user = (new User)->forceFill([
            'id' => '30000000-0000-4000-8000-000000000001',
            'email' => 'purpose@example.test',
            'password' => Hash::make('Purpose-Password!77'),
            'user_type' => 'think_tank',
            'think_tank_member_id' => '30000000-0000-4000-8000-000000000002',
            'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
            'is_disabled' => false,
            'is_blacklisted' => false,
        ]);
        $pending = new PendingLoginService;
        $expiresAt = now()->addMinutes(10)->toIso8601String();
        $pending->begin(
            $request,
            $user,
            false,
            $expiresAt,
            PendingLoginService::PURPOSE_THINK_TANK_API,
            [
                'sent' => true,
                'expires_at' => $expiresAt,
                'masked_destination' => 'p***@example.test',
                'local_code' => '123456',
                'internal_message_id' => 'must-not-persist',
            ],
        );

        expect($pending->challenge($request, PendingLoginService::PURPOSE_WEB))->toBeNull()
            ->and($pending->remember($request, PendingLoginService::PURPOSE_WEB))->toBeFalse();
        $pending->clear($request, PendingLoginService::PURPOSE_WEB);
        expect($pending->challenge($request, PendingLoginService::PURPOSE_THINK_TANK_API))->toBe([
            'sent' => true,
            'expires_at' => $session->get('security.pending_login.expires_at'),
            'masked_destination' => 'p***@example.test',
        ]);
    } finally {
        config(['think_tank_portal.show_local_otp' => $originalShowCode]);

        if ($bootedHere) {
            restore_error_handler();
            restore_exception_handler();
        }
    }
});
