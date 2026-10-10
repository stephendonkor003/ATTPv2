<?php

use App\Models\Activity;
use App\Models\ActivityAllocation;
use App\Models\GovernanceLevel;
use App\Models\GovernanceNode;
use App\Models\Program;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Role;
use App\Models\Sector;
use App\Models\SubActivity;
use App\Models\SubActivityAllocation;
use App\Models\User;
use App\Services\FinancialHierarchyDeletionGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

class HierarchyFinancialDeletionGuardSmoke
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
        DB::beginTransaction();

        try {
            [$sector, $node] = $this->portfolioFixture();
            $administrator = $this->administratorFixture($node);
            [$program, $project, $activity, $subActivity] = $this->hierarchyFixture($sector, $node);

            $this->attachEveryProtectedDependency(
                $program,
                $project,
                $activity,
                $subActivity,
                $administrator,
            );

            $guard = $this->app->make(FinancialHierarchyDeletionGuard::class);
            $activityDependencies = $guard->activityDependencies($activity);
            foreach ([
                'budget commitment(s)',
                'purchase request(s)',
                'purchase order(s)',
                'invoice(s)',
                'disbursement(s)',
                'program budget allocation(s)',
                'procurement plan(s)',
                'activity report(s)',
                'vendor purchase request(s)',
                'vendor assignment(s)',
            ] as $label) {
                $this->assertTrue(
                    ($activityDependencies[$label] ?? 0) === 1,
                    "The activity guard did not detect the linked {$label}."
                );
            }
            $this->assertTrue(
                $guard->projectDependencies($project)['budget commitment(s)'] === 2,
                'The project guard did not detect both its direct and descendant commitments.'
            );
            $this->assertTrue(
                $guard->programDependencies($program)['budget commitment(s)'] === 3,
                'The program guard did not detect its direct and descendant commitments.'
            );

            $this->assertModelDeleteHookBlocks($activity, 'activity');
            $this->assertModelDeleteHookBlocks($project, 'project');
            $this->assertModelDeleteHookBlocks($program, 'program');

            $this->assertBlockedDeletion(
                $administrator,
                route('budget.activities.destroy', $activity),
                ['confirmed_activity_id' => $activity->id],
                $activity,
                'activity',
            );
            $this->assertBlockedDeletion(
                $administrator,
                route('budget.projects.destroy', $project),
                [],
                $project,
                'project',
            );
            $this->assertBlockedDeletion(
                $administrator,
                route('budget.programs.destroy', $program),
                [],
                $program,
                'program',
            );

            $this->assertTrue(
                SubActivity::whereKey($subActivity->id)->exists(),
                'A blocked parent deletion removed its protected sub-activity.'
            );
            $this->assertTrue(
                DB::table('procurement_disbursements')
                    ->where('sub_activity_id', $subActivity->id)
                    ->exists(),
                'A blocked parent deletion nulled or removed a posted disbursement classification.'
            );

            $this->assertUnusedActivityCanBeDeleted($administrator, $sector, $node);
            $this->assertUnusedProjectCanBeDeleted($administrator, $sector, $node);
            $this->assertUnusedProgramCanBeDeleted($administrator, $sector, $node);

            echo "HIERARCHY_FINANCIAL_DELETION_GUARD_SMOKE_OK\n";
        } finally {
            DB::rollBack();
        }
    }

    private function assertUnusedActivityCanBeDeleted(
        User $administrator,
        Sector $sector,
        GovernanceNode $node,
    ): void {
        [, , $activity, $subActivity] = $this->hierarchyFixture($sector, $node, true);

        $this->deleteWithCsrf(
            $administrator,
            route('budget.activities.destroy', $activity),
            ['confirmed_activity_id' => $activity->id],
        );

        $this->assertTrue(
            ! Activity::whereKey($activity->id)->exists()
                && ! SubActivity::whereKey($subActivity->id)->exists(),
            'A genuinely unused activity hierarchy was not deleted.'
        );
    }

    private function assertUnusedProjectCanBeDeleted(
        User $administrator,
        Sector $sector,
        GovernanceNode $node,
    ): void {
        [, $project, $activity, $subActivity] = $this->hierarchyFixture($sector, $node, true);

        $this->deleteWithCsrf(
            $administrator,
            route('budget.projects.destroy', $project),
        );

        $this->assertTrue(
            ! Project::whereKey($project->id)->exists()
                && ! Activity::whereKey($activity->id)->exists()
                && ! SubActivity::whereKey($subActivity->id)->exists(),
            'A genuinely unused project hierarchy was not deleted.'
        );
    }

    private function assertUnusedProgramCanBeDeleted(
        User $administrator,
        Sector $sector,
        GovernanceNode $node,
    ): void {
        [$program, $project, $activity, $subActivity] = $this->hierarchyFixture($sector, $node, true);

        $this->deleteWithCsrf(
            $administrator,
            route('budget.programs.destroy', $program),
        );

        $this->assertTrue(
            ! Program::whereKey($program->id)->exists()
                && ! Project::whereKey($project->id)->exists()
                && ! Activity::whereKey($activity->id)->exists()
                && ! SubActivity::whereKey($subActivity->id)->exists(),
            'A genuinely unused program hierarchy was not deleted.'
        );
    }

    private function assertBlockedDeletion(
        User $administrator,
        string $uri,
        array $data,
        $model,
        string $hierarchyType,
    ): void {
        $response = $this->deleteWithCsrf($administrator, $uri, $data);

        $this->assertTrue(
            $response->getStatusCode() === 302,
            "The protected {$hierarchyType} deletion did not redirect safely."
        );
        $this->assertTrue(
            str_contains((string) session('error'), "This {$hierarchyType} cannot be deleted"),
            "The protected {$hierarchyType} deletion did not explain the dependency. Error: "
                .(string) session('error')
                .'; redirect: '.(string) $response->headers->get('Location')
        );
        $this->assertTrue(
            $model->newQuery()->whereKey($model->getKey())->exists(),
            "The protected {$hierarchyType} was deleted."
        );
    }

    private function assertModelDeleteHookBlocks($model, string $hierarchyType): void
    {
        $blocked = false;

        try {
            $freshModel = $model->fresh();
            $this->assertTrue($freshModel !== null, "The {$hierarchyType} fixture disappeared before deletion.");
            $freshModel->delete();
        } catch (DomainException $exception) {
            $blocked = str_contains(
                $exception->getMessage(),
                "This {$hierarchyType} cannot be deleted",
            );
        }

        $this->assertTrue(
            $blocked && $model->newQuery()->whereKey($model->getKey())->exists(),
            "The {$hierarchyType} model deletion hook did not preserve financial history."
        );
    }

    private function deleteWithCsrf(User $administrator, string $uri, array $data = [])
    {
        session()->forget(['error', 'success']);
        $token = Str::random(40);

        return $this->actingAs($administrator)
            ->withSession([
                '_token' => $token,
                'otp_verified' => true,
                'otp_verified_user_id' => (string) $administrator->id,
                'otp_verified_at' => now()->toIso8601String(),
            ])
            ->delete($uri, ['_token' => $token, ...$data]);
    }

    private function attachEveryProtectedDependency(
        Program $program,
        Project $project,
        Activity $activity,
        SubActivity $subActivity,
        User $vendor,
    ): void {
        $now = now();
        $fundingId = (string) Str::uuid();
        $purchaseRequestId = (string) Str::uuid();
        $consortiumId = (string) Str::uuid();

        DB::table('myb_program_fundings')->insert([
            'id' => $fundingId,
            'program_id' => $program->id,
            'approved_amount' => 1000,
            'currency' => 'USD',
            'start_year' => 2025,
            'end_year' => 2026,
            'status' => 'approved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('myb_purchase_requests')->insert([
            'id' => $purchaseRequestId,
            'reference_no' => 'HFD-PR-'.Str::upper(Str::random(10)),
            'program_funding_id' => $fundingId,
            'allocation_level' => 'sub_activity',
            'allocation_id' => $subActivity->id,
            'start_year' => 2025,
            'currency' => 'USD',
            'total_amount' => 1000,
            'status' => 'approved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('myb_budget_commitments')->insert([
            [
                'id' => (string) Str::uuid(),
                'program_funding_id' => $fundingId,
                'purchase_request_id' => $purchaseRequestId,
                'allocation_level' => 'sub_activity',
                'allocation_id' => $subActivity->id,
                'commitment_amount' => 1000,
                'commitment_year' => 2025,
                'status' => 'approved',
            ],
            [
                'id' => (string) Str::uuid(),
                'program_funding_id' => $fundingId,
                'purchase_request_id' => null,
                'allocation_level' => 'project',
                'allocation_id' => $project->id,
                'commitment_amount' => 500,
                'commitment_year' => 2025,
                'status' => 'approved',
            ],
            [
                'id' => (string) Str::uuid(),
                'program_funding_id' => $fundingId,
                'purchase_request_id' => null,
                'allocation_level' => 'program',
                'allocation_id' => $program->id,
                'commitment_amount' => 250,
                'commitment_year' => 2025,
                'status' => 'approved',
            ],
        ]);
        DB::table('procurement_purchase_orders')->insert([
            'id' => (string) Str::uuid(),
            'sub_activity_id' => $subActivity->id,
            'reference_no' => 'HFD-PO-'.Str::upper(Str::random(10)),
            'amount' => 1000,
            'currency' => 'USD',
            'status' => 'issued',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('procurement_invoices')->insert([
            'id' => (string) Str::uuid(),
            'sub_activity_id' => $subActivity->id,
            'reference_no' => 'HFD-INV-'.Str::upper(Str::random(10)),
            'amount' => 1000,
            'currency' => 'USD',
            'status' => 'approved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('procurement_disbursements')->insert([
            'id' => (string) Str::uuid(),
            'sub_activity_id' => $subActivity->id,
            'reference_no' => 'HFD-DIS-'.Str::upper(Str::random(10)),
            'amount' => 1000,
            'currency' => 'USD',
            'status' => 'completed',
            'paid_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('program_budget_allocations')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $project->id,
            'activity_id' => $activity->id,
            'sub_activity_id' => $subActivity->id,
            'year' => 2025,
            'allocated_amount' => 1000,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('myb_procurement_plans')->insert([
            'id' => (string) Str::uuid(),
            'procurement_code' => 'HFD-PLAN-'.Str::upper(Str::random(10)),
            'title' => 'Hierarchy deletion guard plan',
            'activity_id' => $activity->id,
            'sub_activity_id' => $subActivity->id,
            'estimated_budget' => 1000,
            'currency' => 'USD',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('attp_consortia')->insert([
            'id' => $consortiumId,
            'code' => 'HFD-CONS-'.Str::upper(Str::random(10)),
            'name' => 'Hierarchy deletion guard consortium',
            'currency' => 'USD',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('attp_activity_reports')->insert([
            'id' => (string) Str::uuid(),
            'consortium_id' => $consortiumId,
            'activity_id' => $activity->id,
            'sub_activity_id' => $subActivity->id,
            'title' => 'Hierarchy deletion guard report',
            'status' => 'submitted',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('vendor_purchase_requests')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $vendor->id,
            'sub_activity_id' => $subActivity->id,
            'reference_no' => 'HFD-VPR-'.Str::upper(Str::random(10)),
            'title' => 'Hierarchy deletion guard vendor request',
            'requested_amount' => 1000,
            'currency' => 'USD',
            'status' => 'submitted',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('vendor_sub_activity_assignments')->insert([
            'id' => (string) Str::uuid(),
            'vendor_id' => $vendor->id,
            'program_id' => $program->id,
            'project_id' => $project->id,
            'activity_id' => $activity->id,
            'sub_activity_id' => $subActivity->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array{Program, Project, Activity, SubActivity}
     */
    private function hierarchyFixture(
        Sector $sector,
        GovernanceNode $node,
        bool $withPlanningAllocations = false,
    ): array {
        $program = Program::create([
            'program_id' => 'HFD-PROG-'.Str::upper(Str::random(8)),
            'sector_id' => $sector->id,
            'governance_node_id' => $node->id,
            'name' => 'Hierarchy Guard Program '.Str::upper(Str::random(5)),
            'currency' => 'USD',
            'start_year' => 2025,
            'end_year' => 2026,
            'total_years' => 2,
            'total_budget' => 100000,
        ]);
        $project = Project::create([
            'program_id' => $program->id,
            'project_id' => 'HFD-PROJ-'.Str::upper(Str::random(8)),
            'governance_node_id' => $node->id,
            'name' => 'Hierarchy Guard Project '.Str::upper(Str::random(5)),
            'currency' => 'USD',
            'start_year' => 2025,
            'end_year' => 2026,
            'total_years' => 2,
            'total_budget' => 100000,
        ]);
        $activity = Activity::create([
            'project_id' => $project->id,
            'governance_node_id' => $node->id,
            'name' => 'Hierarchy Guard Activity '.Str::upper(Str::random(5)),
        ]);
        $subActivity = SubActivity::create([
            'activity_id' => $activity->id,
            'governance_node_id' => $node->id,
            'name' => 'Hierarchy Guard Sub-Activity '.Str::upper(Str::random(5)),
        ]);

        if ($withPlanningAllocations) {
            ProjectAllocation::create([
                'project_id' => $project->id,
                'year' => 2025,
                'year_number' => 1,
                'actual_year' => 2025,
                'amount' => 1000,
            ]);
            ActivityAllocation::create([
                'activity_id' => $activity->id,
                'year' => 2025,
                'amount' => 1000,
            ]);
            SubActivityAllocation::create([
                'sub_activity_id' => $subActivity->id,
                'year' => 2025,
                'amount' => 1000,
            ]);
        }

        return [$program, $project, $activity, $subActivity];
    }

    /**
     * @return array{Sector, GovernanceNode}
     */
    private function portfolioFixture(): array
    {
        $suffix = Str::lower(Str::random(8));
        $level = GovernanceLevel::create([
            'key' => 'hierarchy-delete-'.$suffix,
            'name' => 'Hierarchy Delete Test Level',
            'sort_order' => 999,
        ]);
        $node = GovernanceNode::create([
            'level_id' => $level->id,
            'name' => 'Hierarchy Delete Test Node',
            'code' => 'HFD-'.Str::upper($suffix),
            'status' => 'active',
        ]);
        $sector = Sector::create([
            'name' => 'Hierarchy Delete Test Sector '.Str::upper($suffix),
            'governance_node_id' => $node->id,
        ]);

        return [$sector, $node];
    }

    private function administratorFixture(GovernanceNode $node): User
    {
        $role = Role::create([
            'name' => 'Hierarchy Delete Admin '.Str::upper(Str::random(8)),
            'description' => 'Temporary hierarchy deletion smoke role.',
        ]);

        $user = User::create([
            'name' => 'Hierarchy Delete Test Administrator',
            'email' => 'hierarchy-delete-'.Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('Password123!'),
            'user_type' => 'admin',
            'role_id' => $role->id,
            'governance_node_id' => $node->id,
            'must_change_password' => false,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}

(new HierarchyFinancialDeletionGuardSmoke($app))->run();
