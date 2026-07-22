<?php

use App\Models\ConsortiumThinkTank;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ConsortiumOperationsPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Standalone smoke scripts do not enter PHPUnit's normal CLI bootstrap.
(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

class ThinkTankPortalAccessSmoke
{
    use InteractsWithAuthentication;
    use InteractsWithSession;
    use MakesHttpRequests;

    private const AREA_ROUTES = [
        'dashboard' => 'think-tank.dashboard',
        'me' => 'think-tank.me-data.index',
        'finance' => 'think-tank.finance',
        'procurement_plans' => 'think-tank.procurement-plans',
        'report_uploads' => 'think-tank.report-uploads',
    ];

    private const AREA_LABELS = [
        'dashboard' => 'Dashboard',
        'me' => 'M&E Data',
        'finance' => 'Finance',
        'procurement_plans' => 'Procurement Plans',
        'report_uploads' => 'Report Uploads',
    ];

    private const ACCESS_MATRIX = [
        'think_tank_admin' => [
            'dashboard',
            'me',
            'finance',
            'procurement_plans',
            'report_uploads',
        ],
        'procurement_officer' => [
            'dashboard',
            'procurement_plans',
        ],
        'me_officer' => [
            'dashboard',
            'me',
            'report_uploads',
        ],
        'finance_officer' => [
            'dashboard',
            'finance',
        ],
    ];

    private const LEGACY_ROUTE_NAMES = [
        'think-tank.reports',
        'think-tank.research',
        'think-tank.procurement',
    ];

    private const GRIEVANCE_ROUTE_NAME = 'think-tank.grievances.create';

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function run(): void
    {
        $this->assertSchemaAndRoutesReady();

        DB::beginTransaction();

        try {
            $context = $this->prepareContext();
            $this->assertAreaRouteMiddleware();
            $this->assertRoleAccessMatrix($context['users']);
            $this->assertGrievanceAccess($context['users']);
            $this->assertLegacyRoutesAreForbidden($context['users']);
            $this->assertSimplifiedNavigation($context['users']);

            echo "THINK_TANK_PORTAL_ACCESS_OK\n";
        } finally {
            DB::rollBack();
            $this->app['auth']->forgetGuards();
        }
    }

    private function assertSchemaAndRoutesReady(): void
    {
        $this->assertTrue(
            Schema::hasColumns('users', ['think_tank_member_id', 'think_tank_access_level']),
            'The think-tank member and access-level user columns are not migrated.'
        );

        foreach ([...array_values(self::AREA_ROUTES), ...self::LEGACY_ROUTE_NAMES, self::GRIEVANCE_ROUTE_NAME] as $routeName) {
            $this->assertTrue(
                Route::has($routeName),
                "Required think-tank portal route [{$routeName}] is missing."
            );
        }
    }

    /**
     * @return array{member: ConsortiumThinkTank, users: array<string, User>}
     */
    private function prepareContext(): array
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'Think Tank User'],
            ['description' => 'Think tank portal user']
        );

        Artisan::call('db:seed', [
            '--class' => ConsortiumOperationsPermissionsSeeder::class,
            '--force' => true,
        ]);

        $member = ConsortiumThinkTank::query()
            ->with('consortium')
            ->where('status', 'active')
            ->whereHas('consortium')
            ->orderBy('created_at')
            ->first();

        $this->assertTrue(
            (bool) $member,
            'An active think tank with a consortium is required for the portal access smoke test.'
        );

        $users = [];

        foreach (array_keys(self::ACCESS_MATRIX) as $accessLevel) {
            $users[$accessLevel] = User::query()->create([
                'name' => 'Portal access smoke '.str_replace('_', ' ', $accessLevel),
                'email' => 'portal-access-'.$accessLevel.'-'.Str::lower(Str::random(8)).'@example.test',
                'password' => 'Password123!',
                'user_type' => 'think_tank',
                'role_id' => $role->id,
                'think_tank_member_id' => $member->id,
                'think_tank_access_level' => $accessLevel,
                'must_change_password' => false,
                'password_changed_at' => now(),
                'otp_verified_at' => now(),
                'is_disabled' => false,
                'is_blacklisted' => false,
            ]);
        }

        return compact('member', 'users');
    }

    private function assertAreaRouteMiddleware(): void
    {
        foreach (self::AREA_ROUTES as $area => $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $middleware = $route?->gatherMiddleware() ?? [];
            $expected = "think.tank.area:{$area}";

            $this->assertTrue(
                in_array($expected, $middleware, true),
                "Route [{$routeName}] must retain middleware [{$expected}]."
            );
        }

        foreach (self::LEGACY_ROUTE_NAMES as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $middleware = $route?->gatherMiddleware() ?? [];

            $this->assertTrue(
                in_array('think.tank.area:legacy_admin', $middleware, true),
                "Legacy route [{$routeName}] must retain the system-admin-only area guard."
            );
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function assertRoleAccessMatrix(array $users): void
    {
        foreach (self::ACCESS_MATRIX as $accessLevel => $allowedAreas) {
            $user = $users[$accessLevel];

            foreach (self::AREA_ROUTES as $area => $routeName) {
                $response = $this->asPortalUser($user)->get(route($routeName));

                if (in_array($area, $allowedAreas, true)) {
                    $response->assertOk();

                    continue;
                }

                $response->assertForbidden();
            }
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function assertLegacyRoutesAreForbidden(array $users): void
    {
        foreach ($users as $user) {
            foreach (self::LEGACY_ROUTE_NAMES as $routeName) {
                $this->asPortalUser($user)
                    ->get(route($routeName))
                    ->assertForbidden();
            }
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function assertGrievanceAccess(array $users): void
    {
        foreach ($users as $accessLevel => $user) {
            $this->asPortalUser($user)
                ->get(route(self::GRIEVANCE_ROUTE_NAME))
                ->assertOk()
                ->assertSee('Log a Grievance')
                ->assertSee('Incident Details / Summary')
                ->assertSee('Think Tank Portal')
                ->assertSee('Automatically detected by the system.')
                ->assertSee('Your identity will be hidden')
                ->assertDontSee('<select name="channel"', false)
                ->assertSee(route('think-tank.grievances.store'));
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function assertSimplifiedNavigation(array $users): void
    {
        foreach (self::ACCESS_MATRIX as $accessLevel => $allowedAreas) {
            $response = $this->asPortalUser($users[$accessLevel])
                ->get(route('think-tank.dashboard'))
                ->assertOk();
            $navigation = $this->portalNavigation($response->getContent());
            $navigationText = preg_replace('/\s+/', ' ', strip_tags(html_entity_decode(
                $navigation,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            ))) ?: '';
            preg_match_all('/<a\b/i', $navigation, $navigationLinks);

            $this->assertTrue(
                count($navigationLinks[0]) === count($allowedAreas) + 1,
                "The {$accessLevel} menu must contain its allowed portal areas and the grievance link."
            );

            $this->assertTrue(
                str_contains($navigation, 'href="'.route(self::GRIEVANCE_ROUTE_NAME).'"'),
                "The {$accessLevel} menu is missing the grievance link."
            );
            $this->assertTrue(
                str_contains($navigationText, 'Grievance'),
                "The {$accessLevel} menu is missing the Grievance label."
            );

            foreach (self::AREA_ROUTES as $area => $routeName) {
                $link = 'href="'.route($routeName).'"';
                $label = self::AREA_LABELS[$area];

                if (in_array($area, $allowedAreas, true)) {
                    $this->assertTrue(
                        str_contains($navigation, $link),
                        "The {$accessLevel} menu is missing its allowed {$area} link."
                    );
                    $this->assertTrue(
                        str_contains($navigationText, $label),
                        "The {$accessLevel} menu is missing the expected [{$label}] label."
                    );

                    continue;
                }

                $this->assertTrue(
                    ! str_contains($navigation, $link),
                    "The {$accessLevel} menu exposes the disallowed {$area} link."
                );
                $this->assertTrue(
                    ! str_contains($navigationText, $label),
                    "The {$accessLevel} menu exposes the disallowed [{$label}] label."
                );
            }

            foreach (self::LEGACY_ROUTE_NAMES as $routeName) {
                $this->assertTrue(
                    ! str_contains($navigation, 'href="'.route($routeName).'"'),
                    "The simplified {$accessLevel} menu exposes legacy route [{$routeName}]."
                );
            }
        }
    }

    private function portalNavigation(string $html): string
    {
        $matched = preg_match(
            '/<nav\b[^>]*data-think-tank-area-navigation[^>]*>(.*?)<\/nav>/si',
            $html,
            $matches
        );

        $this->assertTrue(
            $matched === 1,
            'The dashboard is missing the simplified think-tank area navigation.'
        );

        return $matches[1];
    }

    private function asPortalUser(User $user): self
    {
        $this->actingAs($user)->withSession([
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);

        return $this;
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}

(new ThinkTankPortalAccessSmoke($app))->run();
