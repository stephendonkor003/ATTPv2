<?php

use App\Models\Consortium;
use App\Models\ConsortiumActivityReport;
use App\Models\ConsortiumThinkTank;
use App\Models\ConsortiumWorkplan;
use App\Models\ProcurementPurchaseOrder;
use App\Models\Role;
use App\Models\ThinkTankProcurementPlan;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Standalone smoke scripts do not enter PHPUnit's normal CLI bootstrap.
(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

class ThinkTankPortalWorkflowsSmoke
{
    use InteractsWithAuthentication;
    use InteractsWithSession;
    use MakesHttpRequests;

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function run(): void
    {
        $this->assertSchemaAndRoutesReady();

        $disk = (string) config('filesystems.default', 'local');
        Storage::fake($disk);
        DB::beginTransaction();

        try {
            $context = $this->prepareContext();
            $procurementOfficer = $this->assertAdminCanCreateStaff($context);

            $this->assertExistingSystemEmailIsGeneric($context);
            $this->assertProcurementPlanWriteBoundaries($context, $procurementOfficer);
            $this->assertReportUploadWriteBoundaries($context, $disk);
            $this->assertCrossMemberFinanceDetailIsDenied($context);
            $this->assertAdminCannotRemoveOwnAccess($context);

            echo "THINK_TANK_PORTAL_WORKFLOWS_OK\n";
        } finally {
            DB::rollBack();
            Storage::disk($disk)->deleteDirectory('consortium-evidence');
            $this->app['auth']->forgetGuards();
        }
    }

    private function assertSchemaAndRoutesReady(): void
    {
        $this->assertTrue(
            Schema::hasColumns('users', ['think_tank_member_id', 'think_tank_access_level']),
            'The think-tank staff access migration has not been run.'
        );

        foreach ([
            'think-tank.team-access.store',
            'think-tank.team-access.update',
            'think-tank.procurement-plans.store',
            'think-tank.report-uploads.store',
            'think-tank.purchase-orders.show',
        ] as $routeName) {
            $this->assertTrue(Route::has($routeName), "Required route [{$routeName}] is missing.");
        }
    }

    /**
     * @return array{
     *     admin: User,
     *     meOfficer: User,
     *     financeOfficer: User,
     *     member: ConsortiumThinkTank,
     *     otherMember: ConsortiumThinkTank,
     *     workplan: ConsortiumWorkplan,
     *     otherWorkplan: ConsortiumWorkplan,
     *     otherPurchaseOrder: ProcurementPurchaseOrder,
     *     normalSystemUser: User
     * }
     */
    private function prepareContext(): array
    {
        $thinkTankRole = Role::query()->firstOrCreate(
            ['name' => 'Think Tank User'],
            ['description' => 'Think tank portal user']
        );
        $systemRole = Role::query()->firstOrCreate(
            ['name' => 'Workflow Smoke System User'],
            ['description' => 'Transaction-scoped system user for portal workflow smoke coverage']
        );

        $normalSystemUser = User::query()->create([
            'name' => 'Workflow Smoke System User',
            'email' => $this->email('normal-system'),
            'password' => 'Password123!',
            'user_type' => 'admin',
            'role_id' => $systemRole->id,
            'must_change_password' => false,
            'password_changed_at' => now(),
            'is_disabled' => false,
            'is_blacklisted' => false,
        ]);

        $admin = User::query()->create([
            'name' => 'Workflow Think Tank Admin',
            'email' => $this->email('tank-admin'),
            'password' => 'Password123!',
            'user_type' => 'think_tank',
            'role_id' => $thinkTankRole->id,
            'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
            'must_change_password' => false,
            'password_changed_at' => now(),
            'otp_verified_at' => now(),
            'is_disabled' => false,
            'is_blacklisted' => false,
        ]);

        $consortium = Consortium::query()->create([
            'code' => 'WF-CONS-'.Str::upper(Str::random(8)),
            'name' => 'Workflow Consortium '.Str::upper(Str::random(5)),
            'secretariat_manager_id' => $normalSystemUser->id,
            'country' => 'Ghana',
            'region' => 'West Africa',
            'approved_budget' => 125000,
            'currency' => 'USD',
            'status' => 'active',
            'mandate' => 'Transaction-scoped portal workflow smoke coverage.',
        ]);

        $member = ConsortiumThinkTank::query()->create([
            'consortium_id' => $consortium->id,
            'portal_user_id' => $admin->id,
            'name' => 'Workflow Policy Think Tank',
            'country' => 'Ghana',
            'email' => $admin->email,
            'role' => 'lead',
            'budget_allocated' => 50000,
            'status' => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        $admin->forceFill(['think_tank_member_id' => $member->id])->save();

        $meOfficer = $this->createPortalUser(
            $thinkTankRole,
            $member,
            User::THINK_TANK_ACCESS_ME,
            'me-officer'
        );
        $financeOfficer = $this->createPortalUser(
            $thinkTankRole,
            $member,
            User::THINK_TANK_ACCESS_FINANCE,
            'finance-officer'
        );

        $workplan = ConsortiumWorkplan::query()->create([
            'consortium_id' => $consortium->id,
            'title' => 'Workflow Current Consortium Plan',
            'period_label' => '2026',
            'starts_on' => now()->startOfYear()->toDateString(),
            'ends_on' => now()->endOfYear()->toDateString(),
            'planned_budget' => 50000,
            'status' => 'approved',
            'objectives' => 'Validate member-scoped activity reporting.',
        ]);

        $otherConsortium = Consortium::query()->create([
            'code' => 'WF-OTHER-'.Str::upper(Str::random(8)),
            'name' => 'Other Workflow Consortium '.Str::upper(Str::random(5)),
            'secretariat_manager_id' => $normalSystemUser->id,
            'country' => 'Kenya',
            'region' => 'East Africa',
            'approved_budget' => 90000,
            'currency' => 'USD',
            'status' => 'active',
        ]);
        $otherMember = ConsortiumThinkTank::query()->create([
            'consortium_id' => $otherConsortium->id,
            'name' => 'Other Workflow Think Tank',
            'country' => 'Kenya',
            'email' => $this->email('other-tank'),
            'role' => 'member',
            'budget_allocated' => 35000,
            'status' => 'active',
            'joined_at' => now()->toDateString(),
        ]);
        $otherWorkplan = ConsortiumWorkplan::query()->create([
            'consortium_id' => $otherConsortium->id,
            'title' => 'Foreign Consortium Workplan',
            'period_label' => '2026',
            'planned_budget' => 25000,
            'status' => 'approved',
        ]);
        $otherPurchaseOrder = ProcurementPurchaseOrder::query()->create([
            'consortium_id' => $otherConsortium->id,
            'think_tank_member_id' => $otherMember->id,
            'reference_no' => 'WF-PO-'.Str::upper(Str::random(10)),
            'po_type' => 'think_tank_transfer',
            'amount' => 12000,
            'currency' => 'USD',
            'status' => 'issued',
            'created_by' => $normalSystemUser->id,
            'issued_at' => now(),
        ]);

        return compact(
            'admin',
            'meOfficer',
            'financeOfficer',
            'member',
            'otherMember',
            'workplan',
            'otherWorkplan',
            'otherPurchaseOrder',
            'normalSystemUser'
        );
    }

    private function createPortalUser(
        Role $role,
        ConsortiumThinkTank $member,
        string $accessLevel,
        string $emailPrefix
    ): User {
        return User::query()->create([
            'name' => 'Workflow '.User::THINK_TANK_ACCESS_LEVELS[$accessLevel],
            'email' => $this->email($emailPrefix),
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

    private function assertAdminCanCreateStaff(array $context): User
    {
        $email = $this->email('created-procurement');

        $this->asPortalUser($context['admin'])
            ->postWithCsrf(route('think-tank.team-access.store'), [
                'name' => 'Created Procurement Officer',
                'email' => $email,
                'access_level' => User::THINK_TANK_ACCESS_PROCUREMENT,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('temporary_password', fn ($value): bool => is_string($value) && strlen($value) >= 12);

        $staff = User::query()->where('email', $email)->first();
        $this->assertTrue((bool) $staff, 'The think tank administrator did not create the staff account.');
        $this->assertSame('think_tank', $staff->user_type, 'Created staff has the wrong user type.');
        $this->assertSame(
            (string) $context['member']->id,
            (string) $staff->think_tank_member_id,
            'Created staff was not assigned to the administrator\'s think tank.'
        );
        $this->assertSame(
            User::THINK_TANK_ACCESS_PROCUREMENT,
            $staff->think_tank_access_level,
            'Created staff has the wrong portal access level.'
        );
        $this->assertSame('Think Tank User', $staff->role?->name, 'Created staff has the wrong system role.');
        $this->assertTrue($staff->must_change_password, 'Created staff must change the temporary password.');

        // Simulate completion of the mandatory first-login password change so
        // this newly created officer can exercise the canonical write route.
        $staff->forceFill([
            'must_change_password' => false,
            'password_changed_at' => now(),
            'otp_verified_at' => now(),
        ])->save();

        return $staff->refresh();
    }

    private function assertExistingSystemEmailIsGeneric(array $context): void
    {
        $existing = $context['normalSystemUser'];
        $usersBefore = User::query()->count();

        $response = $this->asPortalUser($context['admin'])
            ->postWithCsrf(route('think-tank.team-access.store'), [
                'name' => 'Should Not Be Created',
                'email' => $existing->email,
                'access_level' => User::THINK_TANK_ACCESS_FINANCE,
            ]);

        $response
            ->assertRedirect()
            ->assertOnlyInvalid(['email'])
            ->assertSessionHasErrors(['email' => 'This email cannot be used.'])
            ->assertSessionHas('errors', function ($errors): bool {
                if (! $errors instanceof ViewErrorBag) {
                    return false;
                }

                return $errors->getBag('default')->getMessages() === [
                    'email' => ['This email cannot be used.'],
                ];
            });

        $this->assertSame($usersBefore, User::query()->count(), 'The duplicate-email request created a user.');
    }

    private function assertProcurementPlanWriteBoundaries(array $context, User $procurementOfficer): void
    {
        $title = 'Member Scoped Procurement Plan '.Str::upper(Str::random(5));

        $this->asPortalUser($procurementOfficer)
            ->postWithCsrf(route('think-tank.procurement-plans.store'), [
                'think_tank_member_id' => $context['otherMember']->id,
                'title' => $title,
                'fiscal_year' => '2026',
                'estimated_budget' => 18000,
                'currency' => 'USD',
                'planned_publish_date' => now()->addMonth()->toDateString(),
                'description' => 'Created through the focused procurement-plan workflow smoke test.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $plan = ThinkTankProcurementPlan::query()->where('title', $title)->first();
        $this->assertTrue((bool) $plan, 'The procurement officer could not create a procurement plan.');
        $this->assertSame(
            (string) $context['member']->id,
            (string) $plan->think_tank_member_id,
            'The procurement plan was not bound to the authenticated officer\'s membership.'
        );
        $this->assertSame(
            (string) $context['member']->consortium_id,
            (string) $plan->consortium_id,
            'The procurement plan was not bound to the authenticated officer\'s consortium.'
        );
        $this->assertSame(
            (string) $procurementOfficer->id,
            (string) $plan->created_by,
            'The procurement plan did not retain its creator.'
        );

        $plansBeforeDeniedRequests = ThinkTankProcurementPlan::query()->count();

        foreach ([$context['meOfficer'], $context['financeOfficer']] as $unauthorizedUser) {
            $this->asPortalUser($unauthorizedUser)
                ->postWithCsrf(route('think-tank.procurement-plans.store'), [
                    'title' => 'Unauthorized Plan '.Str::upper(Str::random(5)),
                    'estimated_budget' => 100,
                ])
                ->assertForbidden();
        }

        $this->assertSame(
            $plansBeforeDeniedRequests,
            ThinkTankProcurementPlan::query()->count(),
            'An unauthorized specialist created a procurement plan.'
        );
    }

    private function assertReportUploadWriteBoundaries(array $context, string $disk): void
    {
        $title = 'Member Scoped Activity Report '.Str::upper(Str::random(5));

        $this->asPortalUser($context['meOfficer'])
            ->postWithCsrf(route('think-tank.report-uploads.store'), [
                'think_tank_member_id' => $context['otherMember']->id,
                'workplan_id' => $context['workplan']->id,
                'title' => $title,
                'reporting_period_start' => now()->startOfMonth()->toDateString(),
                'reporting_period_end' => now()->endOfMonth()->toDateString(),
                'progress_percent' => 72,
                'funds_spent' => 2400,
                'summary' => 'Member-scoped M&E activity report upload.',
                'achievements' => 'The assigned reporting milestone was completed.',
                'challenges' => 'No material blockers.',
                'next_steps' => 'Continue the next reporting period.',
                'evidence_title' => 'Workflow evidence',
                'evidence_file' => UploadedFile::fake()->create('workflow-evidence.pdf', 16, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $report = ConsortiumActivityReport::query()
            ->with('evidence')
            ->where('title', $title)
            ->first();
        $this->assertTrue((bool) $report, 'The M&E officer could not upload an activity report.');
        $this->assertSame(
            (string) $context['member']->id,
            (string) $report->think_tank_member_id,
            'The report was not bound to the authenticated officer\'s membership.'
        );
        $this->assertSame(
            (string) $context['member']->consortium_id,
            (string) $report->consortium_id,
            'The report was not bound to the authenticated officer\'s consortium.'
        );
        $this->assertSame(
            (string) $context['workplan']->id,
            (string) $report->workplan_id,
            'The report did not retain the valid consortium workplan.'
        );
        $this->assertSame(
            (string) $context['meOfficer']->id,
            (string) $report->submitted_by,
            'The report did not retain its submitting M&E officer.'
        );

        $evidence = $report->evidence->first();
        $this->assertTrue((bool) $evidence, 'The report evidence record was not created.');
        $this->assertTrue(
            Storage::disk($disk)->exists((string) $evidence->file_path),
            'The uploaded report evidence was not written to the fake disk.'
        );

        $reportsBeforeForeignWorkplan = ConsortiumActivityReport::query()->count();
        $this->asPortalUser($context['meOfficer'])
            ->postWithCsrf(route('think-tank.report-uploads.store'), [
                'workplan_id' => $context['otherWorkplan']->id,
                'title' => 'Foreign Workplan Report '.Str::upper(Str::random(5)),
                'summary' => 'This write must fail consortium-scoped validation.',
            ])
            ->assertRedirect()
            ->assertOnlyInvalid(['workplan_id'])
            ->assertSessionHasErrors(['workplan_id']);

        $this->assertSame(
            $reportsBeforeForeignWorkplan,
            ConsortiumActivityReport::query()->count(),
            'A report was created with a workplan from another consortium.'
        );
    }

    private function assertCrossMemberFinanceDetailIsDenied(array $context): void
    {
        $this->asPortalUser($context['financeOfficer'])
            ->get(route('think-tank.purchase-orders.show', $context['otherPurchaseOrder']))
            ->assertForbidden();
    }

    private function assertAdminCannotRemoveOwnAccess(array $context): void
    {
        $admin = $context['admin'];

        $this->asPortalUser($admin)
            ->putWithCsrf(route('think-tank.team-access.update', $admin), [
                'access_level' => User::THINK_TANK_ACCESS_FINANCE,
                'is_disabled' => true,
            ])
            ->assertRedirect()
            ->assertOnlyInvalid(['access_level'])
            ->assertSessionHasErrors([
                'access_level' => 'You cannot remove your own think tank administrator access.',
            ]);

        $admin->refresh();
        $this->assertSame(
            User::THINK_TANK_ACCESS_ADMIN,
            $admin->think_tank_access_level,
            'The administrator removed their own administrator access.'
        );
        $this->assertTrue(! $admin->is_disabled, 'The administrator disabled their own portal account.');
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

    private function postWithCsrf(string $uri, array $data = [])
    {
        $token = Str::random(40);

        return $this->withSession(['_token' => $token])
            ->post($uri, ['_token' => $token, ...$data]);
    }

    private function putWithCsrf(string $uri, array $data = [])
    {
        $token = Str::random(40);

        return $this->withSession(['_token' => $token])
            ->put($uri, ['_token' => $token, ...$data]);
    }

    private function email(string $prefix): string
    {
        return $prefix.'-'.Str::lower(Str::random(10)).'@example.test';
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    private function assertSame($expected, $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message);
        }
    }
}

(new ThinkTankPortalWorkflowsSmoke($app))->run();
