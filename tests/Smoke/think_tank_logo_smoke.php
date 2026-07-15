<?php

use App\Models\ConsortiumThinkTank;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Standalone smoke scripts do not enter PHPUnit's normal CLI bootstrap.
(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

class ThinkTankLogoSmoke
{
    use InteractsWithAuthentication;
    use InteractsWithSession;
    use MakesHttpRequests;

    private const PRIMARY_NAV_ROUTES = [
        'think-tank.dashboard',
        'think-tank.me-data.index',
        'think-tank.finance',
        'think-tank.procurement-plans',
        'think-tank.report-uploads',
    ];

    private const OFFICER_ACCESS_LEVELS = [
        User::THINK_TANK_ACCESS_PROCUREMENT,
        User::THINK_TANK_ACCESS_ME,
        User::THINK_TANK_ACCESS_FINANCE,
    ];

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function run(): void
    {
        $this->assertProductionContract();

        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('public');
        DB::beginTransaction();

        try {
            $context = $this->prepareContext();
            $this->assertRouteGuards();
            $this->assertOfficersCannotUpload($context);
            $this->assertInvalidImageIsRejected($context);
            $this->assertThinkTankAdminUploadAndReplacement($context);
            $this->assertCrossMemberProtection($context);
            $this->assertSystemAdminUpload($context);

            echo "THINK_TANK_LOGO_OK\n";
        } finally {
            DB::rollBack();
            Storage::disk('public')->deleteDirectory('think-tank-logos');
            $this->app['auth']->forgetGuards();
        }
    }

    private function assertProductionContract(): void
    {
        $this->assertTrue(
            Schema::hasColumn('attp_consortium_think_tanks', 'logo_path'),
            'The think-tank logo_path column is not migrated.'
        );

        foreach ([
            'think-tank.branding.logo.update',
            'think-tanks-admin.logo.update',
            ...self::PRIMARY_NAV_ROUTES,
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Required route [{$routeName}] is missing.");
        }
    }

    /**
     * @return array{
     *     member: ConsortiumThinkTank,
     *     otherMember: ConsortiumThinkTank,
     *     thinkTankAdmin: User,
     *     officers: array<string, User>,
     *     systemAdmin: User
     * }
     */
    private function prepareContext(): array
    {
        $members = ConsortiumThinkTank::query()
            ->with('consortium')
            ->where('status', 'active')
            ->whereHas('consortium')
            ->orderBy('created_at')
            ->limit(2)
            ->get();

        $this->assertTrue(
            $members->count() === 2,
            'Two active think tanks with consortium memberships are required for logo isolation coverage.'
        );

        /** @var ConsortiumThinkTank $member */
        $member = $members->first();
        /** @var ConsortiumThinkTank $otherMember */
        $otherMember = $members->last();
        $member->forceFill(['logo_path' => null])->save();
        $otherMember->forceFill(['logo_path' => null])->save();

        $thinkTankRole = Role::query()->firstOrCreate(
            ['name' => 'Think Tank User'],
            ['description' => 'Think tank portal user']
        );
        $systemAdminRole = Role::query()->firstOrCreate(
            ['name' => 'System Admin'],
            ['description' => 'System administrator']
        );

        $thinkTankAdmin = $this->createUser(
            User::THINK_TANK_ACCESS_ADMIN,
            $member,
            $thinkTankRole
        );
        $officers = [];

        foreach (self::OFFICER_ACCESS_LEVELS as $accessLevel) {
            $officers[$accessLevel] = $this->createUser($accessLevel, $member, $thinkTankRole);
        }

        $systemAdmin = User::query()->create([
            'name' => 'Logo smoke system administrator',
            'email' => 'logo-system-admin-'.Str::lower(Str::random(10)).'@example.test',
            'password' => 'Password123!',
            'user_type' => 'admin',
            'role_id' => $systemAdminRole->id,
            'must_change_password' => false,
            'password_changed_at' => now(),
            'otp_verified_at' => now(),
            'is_disabled' => false,
            'is_blacklisted' => false,
        ]);

        return compact('member', 'otherMember', 'thinkTankAdmin', 'officers', 'systemAdmin');
    }

    private function createUser(
        string $accessLevel,
        ConsortiumThinkTank $member,
        Role $role
    ): User {
        return User::query()->create([
            'name' => 'Logo smoke '.str_replace('_', ' ', $accessLevel),
            'email' => 'logo-'.$accessLevel.'-'.Str::lower(Str::random(10)).'@example.test',
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

    private function assertRouteGuards(): void
    {
        $portalRoute = Route::getRoutes()->getByName('think-tank.branding.logo.update');
        $portalMiddleware = $portalRoute?->gatherMiddleware() ?? [];
        $this->assertTrue(
            in_array('think.tank.area:team', $portalMiddleware, true),
            'The portal logo route must retain the think-tank-admin team-area guard.'
        );
        $this->assertTrue(
            in_array('permission:think_tank.team.manage', $portalMiddleware, true),
            'The portal logo route must retain the team-management permission guard.'
        );

        $adminRoute = Route::getRoutes()->getByName('think-tanks-admin.logo.update');
        $adminMiddleware = $adminRoute?->gatherMiddleware() ?? [];
        $this->assertTrue(
            in_array('permission:think_tanks.directory.edit', $adminMiddleware, true),
            'The system-admin logo route must retain directory-edit authorization.'
        );
    }

    /**
     * @param  array{member: ConsortiumThinkTank, officers: array<string, User>}  $context
     */
    private function assertOfficersCannotUpload(array $context): void
    {
        foreach ($context['officers'] as $accessLevel => $officer) {
            $this->asUser($officer)
                ->put(route('think-tank.branding.logo.update'), [
                    'logo' => UploadedFile::fake()->image("{$accessLevel}.png", 100, 100),
                ])
                ->assertForbidden();

            $context['member']->refresh();
            $this->assertTrue(
                blank($context['member']->logo_path),
                "The {$accessLevel} changed the think-tank logo despite lacking branding access."
            );
        }

        $this->assertTrue(
            Storage::disk('public')->allFiles('think-tank-logos') === [],
            'A forbidden officer upload left a logo file behind.'
        );
    }

    /**
     * @param  array{member: ConsortiumThinkTank, thinkTankAdmin: User}  $context
     */
    private function assertInvalidImageIsRejected(array $context): void
    {
        $invalidFile = UploadedFile::fake()->create('not-an-image.png', 4, 'text/plain');

        $this->asUser($context['thinkTankAdmin'])
            ->from(route('think-tank.dashboard'))
            ->put(route('think-tank.branding.logo.update'), ['logo' => $invalidFile])
            ->assertRedirect(route('think-tank.dashboard'))
            ->assertSessionHasErrors('logo');

        $this->assertTrue(
            blank($context['member']->refresh()->logo_path),
            'An invalid image changed the think-tank logo path.'
        );
        $this->assertTrue(
            Storage::disk('public')->allFiles('think-tank-logos') === [],
            'An invalid image was written to logo storage.'
        );
    }

    /**
     * @param  array{member: ConsortiumThinkTank, thinkTankAdmin: User}  $context
     */
    private function assertThinkTankAdminUploadAndReplacement(array $context): void
    {
        $this->asUser($context['thinkTankAdmin'])
            ->from(route('think-tank.dashboard'))
            ->put(route('think-tank.branding.logo.update'), [
                'logo' => UploadedFile::fake()->image('first-logo.png', 160, 160),
            ])
            ->assertRedirect(route('think-tank.dashboard'))
            ->assertSessionHasNoErrors();

        $firstPath = (string) $context['member']->refresh()->logo_path;
        $this->assertStoredLogoPath($firstPath, 'The Think Tank Admin upload was not persisted.');
        $this->assertPortalBranding($context['thinkTankAdmin'], $context['member'], true);

        $this->asUser($context['thinkTankAdmin'])
            ->from(route('think-tank.dashboard'))
            ->put(route('think-tank.branding.logo.update'), [
                'logo' => UploadedFile::fake()->image('replacement-logo.webp', 180, 180),
            ])
            ->assertRedirect(route('think-tank.dashboard'))
            ->assertSessionHasNoErrors();

        $replacementPath = (string) $context['member']->refresh()->logo_path;
        $this->assertTrue($replacementPath !== $firstPath, 'The replacement upload reused the old logo path.');
        $this->assertStoredLogoPath($replacementPath, 'The replacement logo was not persisted.');
        $this->assertTrue(
            ! Storage::disk('public')->exists($firstPath),
            'The replaced logo file was not removed.'
        );
        $this->assertPortalBranding($context['thinkTankAdmin'], $context['member'], true);
    }

    /**
     * @param  array{
     *     member: ConsortiumThinkTank,
     *     otherMember: ConsortiumThinkTank,
     *     thinkTankAdmin: User
     * }  $context
     */
    private function assertCrossMemberProtection(array $context): void
    {
        $previousOwnPath = (string) $context['member']->logo_path;

        $this->asUser($context['thinkTankAdmin'])
            ->from(route('think-tank.dashboard'))
            ->put(route('think-tank.branding.logo.update', [
                'think_tank_member_id' => $context['otherMember']->id,
            ]), [
                'logo' => UploadedFile::fake()->image('cross-member-attempt.png', 140, 140),
            ])
            ->assertRedirect(route('think-tank.dashboard'))
            ->assertSessionHasNoErrors();

        $ownPathAfterAttempt = (string) $context['member']->refresh()->logo_path;
        $this->assertTrue(
            $ownPathAfterAttempt !== $previousOwnPath,
            'The scoped portal upload did not update the authenticated admin\'s own think tank.'
        );
        $this->assertTrue(
            blank($context['otherMember']->refresh()->logo_path),
            'A Think Tank Admin changed another think tank by supplying its member ID.'
        );
        $this->assertTrue(
            ! Storage::disk('public')->exists($previousOwnPath),
            'The scoped replacement left the previous own-member logo behind.'
        );

        $this->asUser($context['thinkTankAdmin'])
            ->put(route('think-tanks-admin.logo.update', $context['otherMember']), [
                'logo' => UploadedFile::fake()->image('forbidden-admin-route.png', 100, 100),
            ])
            ->assertForbidden();

        $this->assertTrue(
            blank($context['otherMember']->refresh()->logo_path),
            'A Think Tank Admin used the system directory route to change another member.'
        );
    }

    /**
     * @param  array{otherMember: ConsortiumThinkTank, systemAdmin: User}  $context
     */
    private function assertSystemAdminUpload(array $context): void
    {
        $this->asUser($context['systemAdmin'])
            ->from(route('think-tanks-admin.show', $context['otherMember']))
            ->put(route('think-tanks-admin.logo.update', $context['otherMember']), [
                'logo' => UploadedFile::fake()->image('system-admin-logo.jpg', 200, 200),
            ])
            ->assertRedirect(route('think-tanks-admin.show', $context['otherMember']))
            ->assertSessionHasNoErrors();

        $path = (string) $context['otherMember']->refresh()->logo_path;
        $this->assertStoredLogoPath($path, 'The System Admin logo upload was not persisted.');
        $this->assertPortalBranding($context['systemAdmin'], $context['otherMember'], false);
    }

    private function assertPortalBranding(
        User $user,
        ConsortiumThinkTank $member,
        bool $expectBrandingForm
    ): void {
        $response = $this->asUser($user)
            ->get(route('think-tank.dashboard', ['think_tank_member_id' => $member->id]))
            ->assertOk();
        $html = $response->getContent();

        foreach (['data-tt-au-logo', 'data-tt-member-logo', 'data-tt-member-watermark'] as $hook) {
            $this->assertTrue(str_contains($html, $hook), "Portal branding hook [{$hook}] is missing.");
        }

        $logoUrl = e((string) $member->logo_url);
        $this->assertTrue(
            $logoUrl !== '' && substr_count($html, $logoUrl) >= 2,
            'The uploaded logo is not rendered in both the portal header and watermark.'
        );

        $hasBrandingForm = str_contains($html, 'data-tt-branding-form');
        $this->assertTrue(
            $hasBrandingForm === $expectBrandingForm,
            $expectBrandingForm
                ? 'The Think Tank Admin branding form is missing.'
                : 'The System Admin preview unexpectedly exposes the think-tank self-service branding form.'
        );

        $navigation = $this->portalNavigation($html);
        preg_match_all('/<a\b/i', $navigation, $navigationLinks);
        $this->assertTrue(
            count($navigationLinks[0]) === count(self::PRIMARY_NAV_ROUTES),
            'Logo branding changed the five-area primary navigation.'
        );

        foreach (self::PRIMARY_NAV_ROUTES as $routeName) {
            $parameters = $user->isAdmin() || $user->isSuperAdmin()
                ? ['think_tank_member_id' => $member->id]
                : [];
            $this->assertTrue(
                str_contains($navigation, 'href="'.route($routeName, $parameters).'"'),
                "The branded portal navigation is missing route [{$routeName}]."
            );
        }
    }

    private function assertStoredLogoPath(string $path, string $message): void
    {
        $this->assertTrue(
            Str::startsWith($path, 'think-tank-logos/') && Storage::disk('public')->exists($path),
            $message
        );
    }

    private function portalNavigation(string $html): string
    {
        $matched = preg_match(
            '/<nav\b[^>]*data-think-tank-area-navigation[^>]*>(.*?)<\/nav>/si',
            $html,
            $matches
        );

        $this->assertTrue($matched === 1, 'The five-area think-tank navigation hook is missing.');

        return $matches[1];
    }

    private function asUser(User $user): self
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

(new ThinkTankLogoSmoke($app))->run();
