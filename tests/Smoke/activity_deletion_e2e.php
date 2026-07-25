<?php

use App\Models\Activity;
use App\Models\ActivityAllocation;
use App\Models\GovernanceLevel;
use App\Models\GovernanceNode;
use App\Models\Permission;
use App\Models\Program;
use App\Models\ProgramBudgetAllocation;
use App\Models\Project;
use App\Models\Role;
use App\Models\Sector;
use App\Models\SubActivity;
use App\Models\SubActivityAllocation;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

class ActivityDeletionSmoke
{
    use MakesHttpRequests;
    use InteractsWithAuthentication;
    use InteractsWithSession;

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function run(): void
    {
        DB::beginTransaction();

        try {
            [$project, $node] = $this->projectFixture();
            $deletePermission = Permission::where('name', 'activities.delete')->firstOrFail();
            $viewPermission = Permission::where('name', 'activities.view')->firstOrFail();
            $role = Role::create([
                'name' => 'Activity Deletion Smoke ' . Str::upper(Str::random(6)),
                'description' => 'Temporary activity deletion test role.',
            ]);
            $user = $this->userFixture('activity-delete-user', $role, $node, 'staff');
            $user->permissions()->attach($viewPermission->id);

            [$activity, $subActivities] = $this->activityFixture($project, 2);

            $indexResponse = $this->actingAsVerified($user)
                ->get(route('budget.activities.index'));
            $this->assertResponseStatus($indexResponse, 200, 'The activity index did not load for a viewer.');
            $this->assertTrue(
                ! str_contains($indexResponse->getContent(), 'data-bs-target="#deleteActivityModal"'),
                'The delete control was visible without delete permission.'
            );

            $this->actingAsVerified($user)
                ->deleteWithCsrf(route('budget.activities.destroy', $activity), [
                    'confirmed_activity_id' => $activity->id,
                ])
                ->assertForbidden();
            $this->assertTrue(Activity::whereKey($activity->id)->exists(), 'An unauthorized user deleted an activity.');

            $user->permissions()->attach($deletePermission->id);
            $user->unsetRelation('permissions');

            $this->actingAsVerified($user)
                ->get(route('budget.activities.index'))
                ->assertOk()
                ->assertSee('Delete')
                ->assertSee('data-bs-target="#deleteActivityModal"', false)
                ->assertSee('Yes, delete activity')
                ->assertSee('associated sub-activities');

            $this->actingAsVerified($user)
                ->deleteWithCsrf(route('budget.activities.destroy', $activity))
                ->assertRedirect()
                ->assertSessionHas('error');
            $this->assertTrue(Activity::whereKey($activity->id)->exists(), 'An unconfirmed activity deletion was accepted.');

            $this->actingAsVerified($user)
                ->deleteWithCsrf(route('budget.activities.destroy', $activity), [
                    'confirmed_activity_id' => $activity->id,
                ])
                ->assertRedirect()
                ->assertSessionHas('success');

            $this->assertDeletedHierarchy($activity, $subActivities);

            [$linkedActivity, $linkedSubActivities] = $this->activityFixture($project, 1);
            DB::table('myb_budget_commitments')->insert([
                'id' => (string) Str::uuid(),
                'allocation_level' => 'activity',
                'allocation_id' => $linkedActivity->id,
                'commitment_amount' => 1000,
                'commitment_year' => 2025,
                'status' => 'draft',
            ]);

            $this->actingAsVerified($user)
                ->deleteWithCsrf(route('budget.activities.destroy', $linkedActivity), [
                    'confirmed_activity_id' => $linkedActivity->id,
                ])
                ->assertRedirect()
                ->assertSessionHas('error');
            $this->assertTrue(
                Activity::whereKey($linkedActivity->id)->exists()
                    && SubActivity::whereIn('id', $linkedSubActivities->pluck('id'))->exists(),
                'An activity with financial dependencies was partially deleted.'
            );

            [$superAdminActivity, $superAdminSubActivities] = $this->activityFixture($project, 1);
            $superAdminRole = Role::create([
                'name' => 'Activity Deletion Super Admin ' . Str::upper(Str::random(6)),
                'description' => 'Temporary super administrator test role.',
            ]);
            $superAdmin = $this->userFixture('activity-delete-super', $superAdminRole, $node, 'admin');

            $this->actingAsVerified($superAdmin)
                ->deleteWithCsrf(route('budget.activities.destroy', $superAdminActivity), [
                    'confirmed_activity_id' => $superAdminActivity->id,
                ])
                ->assertRedirect()
                ->assertSessionHas('success');

            $this->assertDeletedHierarchy($superAdminActivity, $superAdminSubActivities);

            echo "ACTIVITY_DELETION_E2E_OK\n";
        } finally {
            DB::rollBack();
        }
    }

    private function projectFixture(): array
    {
        $suffix = Str::lower(Str::random(8));
        $level = GovernanceLevel::create([
            'key' => 'activity-delete-' . $suffix,
            'name' => 'Activity Delete Test Level',
            'sort_order' => 999,
        ]);
        $node = GovernanceNode::create([
            'level_id' => $level->id,
            'name' => 'Activity Delete Test Node',
            'code' => 'ADT-' . Str::upper($suffix),
            'status' => 'active',
        ]);
        $sector = Sector::create([
            'name' => 'Activity Delete Test Sector',
            'governance_node_id' => $node->id,
        ]);
        $program = Program::create([
            'program_id' => (string) Str::uuid(),
            'sector_id' => $sector->id,
            'governance_node_id' => $node->id,
            'name' => 'Activity Delete Test Program',
            'currency' => 'USD',
            'start_year' => 2025,
            'end_year' => 2026,
            'total_years' => 2,
            'total_budget' => 100000,
        ]);
        $project = Project::create([
            'program_id' => $program->id,
            'project_id' => (string) Str::uuid(),
            'governance_node_id' => $node->id,
            'name' => 'Activity Delete Test Project',
            'currency' => 'USD',
            'start_year' => 2025,
            'end_year' => 2026,
            'total_years' => 2,
            'total_budget' => 100000,
        ]);

        return [$project, $node];
    }

    private function activityFixture(Project $project, int $subActivityCount): array
    {
        $activity = Activity::create([
            'project_id' => $project->id,
            'governance_node_id' => $project->governance_node_id,
            'name' => 'Deletable Activity ' . Str::upper(Str::random(5)),
            'description' => 'Activity deletion smoke fixture.',
        ]);

        foreach ([2025 => 30000, 2026 => 30000] as $year => $amount) {
            ActivityAllocation::create([
                'activity_id' => $activity->id,
                'year' => $year,
                'amount' => $amount,
            ]);
        }

        $subActivities = collect();

        for ($index = 1; $index <= $subActivityCount; $index++) {
            $subActivity = SubActivity::create([
                'activity_id' => $activity->id,
                'governance_node_id' => $project->governance_node_id,
                'name' => "Deletable Sub-Activity {$index}",
            ]);
            SubActivityAllocation::create([
                'sub_activity_id' => $subActivity->id,
                'year' => 2025,
                'amount' => 5000,
            ]);
            ProgramBudgetAllocation::create([
                'project_id' => $project->id,
                'activity_id' => $activity->id,
                'sub_activity_id' => $subActivity->id,
                'year' => 2025,
                'allocated_amount' => 5000,
            ]);
            $subActivities->push($subActivity);
        }

        return [$activity, $subActivities];
    }

    private function userFixture(
        string $emailPrefix,
        Role $role,
        GovernanceNode $node,
        string $userType
    ): User {
        return User::create([
            'name' => 'Activity Deletion Test User',
            'email' => $emailPrefix . '-' . Str::lower(Str::random(8)) . '@example.test',
            'password' => Hash::make('Password123!'),
            'user_type' => $userType,
            'role_id' => $role->id,
            'governance_node_id' => $node->id,
            'must_change_password' => false,
        ]);
    }

    private function assertDeletedHierarchy(Activity $activity, $subActivities): void
    {
        $subActivityIds = $subActivities->pluck('id');

        $this->assertTrue(! Activity::whereKey($activity->id)->exists(), 'The activity was not deleted.');
        $this->assertTrue(
            ! ActivityAllocation::where('activity_id', $activity->id)->exists(),
            'Activity allocations were not deleted.'
        );
        $this->assertTrue(
            ! SubActivity::whereIn('id', $subActivityIds)->exists(),
            'Associated sub-activities were not deleted.'
        );
        $this->assertTrue(
            ! SubActivityAllocation::whereIn('sub_activity_id', $subActivityIds)->exists(),
            'Associated sub-activity allocations were not deleted.'
        );
        $this->assertTrue(
            ! ProgramBudgetAllocation::where('activity_id', $activity->id)->exists(),
            'Associated program budget allocations were not deleted.'
        );
    }

    private function deleteWithCsrf(string $uri, array $data = [])
    {
        $token = Str::random(40);

        return $this->withSession(['_token' => $token])
            ->delete($uri, ['_token' => $token, ...$data]);
    }

    private function actingAsVerified(User $user): self
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

    private function assertResponseStatus($response, int $expected, string $message): void
    {
        $actual = $response->getStatusCode();

        if ($actual !== $expected) {
            $location = (string) $response->headers->get('Location');
            throw new RuntimeException("{$message} Expected {$expected}, received {$actual}. Location: {$location}");
        }
    }
}

(new ActivityDeletionSmoke($app))->run();
