<?php

use App\Models\DynamicForm;
use App\Models\FormSubmission;
use App\Models\GovernanceLevel;
use App\Models\GovernanceNode;
use App\Models\Procurement;
use App\Models\Role;
use App\Models\User;
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

class ProcurementRegistryDeletionSmoke
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
            $suffix = Str::lower(Str::random(8));
            $level = GovernanceLevel::create([
                'key' => "procurement-delete-{$suffix}",
                'name' => 'Procurement Deletion Test Level',
                'sort_order' => 999,
            ]);
            $node = GovernanceNode::create([
                'level_id' => $level->id,
                'name' => 'Procurement Deletion Test Node',
                'code' => 'PDT-'.Str::upper($suffix),
                'status' => 'active',
            ]);

            $admin = $this->userFixture(
                Role::query()->where('name', 'System Admin')->firstOrFail(),
                $node,
                'procurement-delete-admin'
            );
            $staffRole = Role::create([
                'name' => 'Procurement Deletion Test '.Str::upper($suffix),
                'description' => 'Temporary procurement deletion smoke role.',
            ]);
            $staff = $this->userFixture($staffRole, $node, 'procurement-delete-staff');

            $unauthorized = $this->procurementFixture($node, $admin, 'Unauthorized draft');
            $this->actingAsVerified($staff)
                ->deleteWithCsrf(route('procurements.destroy', $unauthorized))
                ->assertForbidden();
            $this->assertTrue(
                Procurement::query()->whereKey($unauthorized->id)->exists(),
                'A non-admin deleted a procurement.'
            );

            $submitted = $this->procurementFixture($node, $admin, 'Submitted procurement', 'submitted');
            $this->actingAsVerified($admin)
                ->deleteWithCsrf(route('procurements.destroy', $submitted))
                ->assertRedirect()
                ->assertSessionHas('error');
            $this->assertTrue(
                Procurement::query()->whereKey($submitted->id)->exists(),
                'A non-draft procurement was deleted.'
            );

            $publishedHistory = $this->procurementFixture($node, $admin, 'Previously published draft');
            DB::table('procurement_audit_logs')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $admin->id,
                'action' => 'Published procurement',
                'procurement_id' => $publishedHistory->id,
                'created_at' => now(),
            ]);
            $this->actingAsVerified($admin)
                ->deleteWithCsrf(route('procurements.destroy', $publishedHistory))
                ->assertRedirect()
                ->assertSessionHas('error');
            $this->assertTrue(
                Procurement::query()->whereKey($publishedHistory->id)->exists(),
                'A procurement with publication history was deleted.'
            );

            $blocked = $this->procurementFixture($node, $admin, 'Procurement with submission');
            FormSubmission::create([
                'procurement_id' => $blocked->id,
                'submitted_by' => $staff->id,
                'status' => FormSubmission::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);
            $this->actingAsVerified($admin)
                ->deleteWithCsrf(route('procurements.destroy', $blocked))
                ->assertRedirect()
                ->assertSessionHas('error');
            $this->assertTrue(
                Procurement::query()->whereKey($blocked->id)->exists(),
                'A procurement with a submission was deleted.'
            );

            $deletable = $this->procurementFixture($node, $admin, 'Unused draft procurement');
            $form = DynamicForm::create([
                'name' => 'Detachable procurement form',
                'applies_to' => 'procurement',
                'status' => 'draft',
                'is_active' => false,
                'created_by' => $admin->id,
                'procurement_id' => $deletable->id,
            ]);
            $assignmentId = (string) Str::uuid();
            DB::table('evaluation_assignments')->insert([
                'id' => $assignmentId,
                'procurement_id' => $deletable->id,
                'user_id' => $staff->id,
                'assigned_by' => $admin->id,
                'assigned_at' => now(),
                'status' => 'assigned',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $adminIndex = $this->actingAsVerified($admin)->get(route('procurements.index'));
            $adminIndex->assertOk()->assertSee(route('procurements.destroy', $deletable), false);

            $this->actingAsVerified($admin)
                ->deleteWithCsrf(route('procurements.destroy', $deletable))
                ->assertRedirect(route('procurements.index'))
                ->assertSessionHas('success');

            $this->assertTrue(
                ! Procurement::query()->whereKey($deletable->id)->exists(),
                'The unused draft procurement was not deleted.'
            );
            $this->assertTrue(
                DynamicForm::query()->whereKey($form->id)->value('procurement_id') === null,
                'The reusable form was not detached before procurement deletion.'
            );
            $this->assertTrue(
                ! DB::table('evaluation_assignments')->where('id', $assignmentId)->exists(),
                'The evaluator assignment setup was not removed with the procurement.'
            );

            echo "PROCUREMENT_REGISTRY_DELETION_SMOKE_OK\n";
        } finally {
            DB::rollBack();
        }
    }

    private function procurementFixture(
        GovernanceNode $node,
        User $creator,
        string $title,
        string $status = 'draft'
    ): Procurement {
        return Procurement::create([
            'title' => $title.' '.Str::upper(Str::random(5)),
            'description' => 'Procurement registry deletion smoke fixture.',
            'fiscal_year' => 2026,
            'status' => $status,
            'governance_node_id' => $node->id,
            'created_by' => $creator->id,
        ]);
    }

    private function userFixture(Role $role, GovernanceNode $node, string $emailPrefix): User
    {
        return User::create([
            'name' => 'Procurement Deletion Test User',
            'email' => $emailPrefix.'-'.Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('Password123!'),
            'user_type' => 'staff',
            'role_id' => $role->id,
            'governance_node_id' => $node->id,
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'otp_verified_at' => now(),
        ]);
    }

    private function deleteWithCsrf(string $uri)
    {
        $token = Str::random(40);

        return $this->withSession(['_token' => $token])
            ->delete($uri, ['_token' => $token]);
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
}

(new ProcurementRegistryDeletionSmoke($app))->run();
