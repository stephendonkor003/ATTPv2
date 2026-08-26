<?php

use App\Models\DynamicForm;
use App\Models\EvaluationAssignment;
use App\Models\FormSubmission;
use App\Models\GovernanceLevel;
use App\Models\GovernanceNode;
use App\Models\Procurement;
use App\Models\ProcurementAuditLog;
use App\Models\ProcurementDocument;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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
            Storage::fake('local');
            Storage::fake('public');

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
            $superAdminRole = Role::query()->firstOrCreate(
                ['name' => 'Super Admin'],
                ['description' => 'Protected super administrator']
            );
            $superAdmin = $this->userFixture(
                $superAdminRole,
                $node,
                'procurement-delete-super-admin'
            );
            $staffRole = Role::create([
                'name' => 'Procurement Deletion Test '.Str::upper($suffix),
                'description' => 'Temporary procurement deletion smoke role.',
            ]);
            $staff = $this->userFixture($staffRole, $node, 'procurement-delete-staff');

            $procurements = collect();
            foreach (['submitted', 'rejected', 'approved', 'published', 'closed', 'awarded'] as $status) {
                $procurements->put(
                    $status,
                    $this->procurementFixture(
                        $node,
                        $admin,
                        ucfirst($status).' procurement',
                        $status
                    )
                );
            }

            // Create the draft last so it is guaranteed to appear on the first
            // registry page while role-specific button visibility is checked.
            $authorizationTarget = $this->procurementFixture(
                $node,
                $admin,
                'Exact System Admin authorization target'
            );
            $procurements->put('draft', $authorizationTarget);

            $adminIndex = $this->actingAsVerified($admin)->get(route('procurements.index'));
            $this->assertResponseStatus($adminIndex, 200, 'The System Admin registry did not load.');
            $this->assertResponseContains(
                $adminIndex,
                $authorizationTarget->title,
                'The System Admin registry did not contain the authorization target.'
            );
            $this->assertResponseContains(
                $adminIndex,
                'title="Soft delete procurement"',
                'The System Admin registry did not render the soft-delete action.'
            );

            $staffIndex = $this->actingAsVerified($staff)->get(route('procurements.index'));
            $this->assertResponseStatus($staffIndex, 200, 'The staff registry did not load.');
            $this->assertResponseContains(
                $staffIndex,
                $authorizationTarget->title,
                'The staff registry did not contain the authorization target.'
            );
            $this->assertResponseDoesNotContain(
                $staffIndex,
                'title="Soft delete procurement"',
                'The staff registry exposed the System Admin-only soft-delete action.'
            );

            $superAdminIndex = $this->actingAsVerified($superAdmin)->get(route('procurements.index'));
            $this->assertResponseStatus($superAdminIndex, 200, 'The Super Admin registry did not load.');
            $this->assertResponseContains(
                $superAdminIndex,
                $authorizationTarget->title,
                'The Super Admin registry did not contain the authorization target.'
            );
            $this->assertResponseDoesNotContain(
                $superAdminIndex,
                'title="Soft delete procurement"',
                'The Super Admin registry exposed the exact System Admin-only soft-delete action.'
            );

            $staffDelete = $this->actingAsVerified($staff)
                ->deleteWithCsrf(route('procurements.destroy', $authorizationTarget));
            $this->assertResponseStatus($staffDelete, 403, 'Staff deletion was not forbidden.');
            $superAdminDelete = $this->actingAsVerified($superAdmin)
                ->deleteWithCsrf(route('procurements.destroy', $authorizationTarget));
            $this->assertResponseStatus(
                $superAdminDelete,
                403,
                'A role merely named Super Admin was allowed to delete the procurement.'
            );
            $this->assertTrue(
                Procurement::query()->whereKey($authorizationTarget->id)->exists(),
                'A user without the exact System Admin role deleted a procurement.'
            );

            $linkedProcurement = $procurements->get('published');
            $form = DynamicForm::create([
                'name' => 'Preserved procurement form',
                'applies_to' => 'procurement',
                'status' => 'approved',
                'is_active' => true,
                'created_by' => $admin->id,
                'procurement_id' => $linkedProcurement->id,
            ]);
            $submission = FormSubmission::create([
                'procurement_id' => $linkedProcurement->id,
                'form_id' => $form->id,
                'submitted_by' => $staff->id,
                'status' => FormSubmission::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ]);
            $assignment = EvaluationAssignment::create([
                'procurement_id' => $linkedProcurement->id,
                'form_submission_id' => $submission->id,
                'user_id' => $staff->id,
                'assigned_by' => $admin->id,
                'assigned_at' => now(),
                'status' => 'assigned',
            ]);
            $localDocumentPath = "procurements/{$linkedProcurement->id}/documents/terms.txt";
            $coverImagePath = "procurement-covers/{$linkedProcurement->id}.jpg";
            Storage::disk('local')->put($localDocumentPath, 'preserved procurement document');
            Storage::disk('public')->put($coverImagePath, 'preserved procurement cover');
            $linkedProcurement->update(['cover_image_path' => $coverImagePath]);
            $document = ProcurementDocument::create([
                'procurement_id' => $linkedProcurement->id,
                'document_name' => 'Terms of reference',
                'original_name' => 'terms.txt',
                'file_path' => $localDocumentPath,
                'mime_type' => 'text/plain',
                'file_size' => strlen('preserved procurement document'),
                'uploaded_by' => $admin->id,
            ]);

            foreach ($procurements as $status => $procurement) {
                $deleteResponse = $this->actingAsVerified($admin)
                    ->deleteWithCsrf(route('procurements.destroy', $procurement));
                $this->assertResponseStatus(
                    $deleteResponse,
                    302,
                    "The {$status} procurement deletion did not redirect."
                );
                $this->assertTrue(
                    $deleteResponse->headers->get('Location') === route('procurements.index'),
                    "The {$status} procurement deletion redirected to the wrong page."
                );
                $this->assertTrue(
                    session()->has('success'),
                    "The {$status} procurement deletion did not flash a success message."
                );

                $this->assertTrue(
                    ! Procurement::query()->whereKey($procurement->id)->exists(),
                    "The {$status} procurement remains in the active query after deletion."
                );

                $trashed = Procurement::withTrashed()->whereKey($procurement->id)->first();
                $this->assertTrue(
                    $trashed?->trashed() === true,
                    "The {$status} procurement was not soft deleted."
                );
                $this->assertTrue(
                    (string) $trashed->deleted_by === (string) $admin->id,
                    "The {$status} procurement did not record the deleting System Admin."
                );

                $audit = ProcurementAuditLog::query()
                    ->where('procurement_id', $procurement->id)
                    ->where('action', 'Soft deleted procurement')
                    ->latest('created_at')
                    ->first();
                $this->assertTrue(
                    $audit
                    && (string) $audit->user_id === (string) $admin->id
                    && ($audit->metadata['status'] ?? null) === $status,
                    "The {$status} procurement soft deletion was not audited correctly."
                );

                $showResponse = $this->actingAsVerified($admin)
                    ->get(route('procurements.show', $trashed));
                $this->assertResponseStatus(
                    $showResponse,
                    404,
                    "The {$status} soft-deleted procurement remained route-bindable."
                );
            }

            $activeRegistry = $this->actingAsVerified($admin)->get(route('procurements.index'));
            $this->assertResponseStatus($activeRegistry, 200, 'The active registry did not load after deletion.');
            foreach ($procurements as $procurement) {
                $this->assertResponseDoesNotContain(
                    $activeRegistry,
                    $procurement->title,
                    'A soft-deleted procurement remained visible in the active registry.'
                );
            }

            $this->assertTrue(
                (string) DynamicForm::query()->whereKey($form->id)->value('procurement_id')
                    === (string) $linkedProcurement->id,
                'The linked form was detached during soft deletion.'
            );
            $this->assertTrue(
                (string) FormSubmission::query()->whereKey($submission->id)->value('procurement_id')
                    === (string) $linkedProcurement->id,
                'The linked form submission was removed or detached during soft deletion.'
            );
            $this->assertTrue(
                (string) EvaluationAssignment::query()->whereKey($assignment->id)->value('procurement_id')
                    === (string) $linkedProcurement->id,
                'The linked evaluator assignment was removed or detached during soft deletion.'
            );
            $this->assertTrue(
                (string) ProcurementDocument::query()->whereKey($document->id)->value('procurement_id')
                    === (string) $linkedProcurement->id,
                'The linked procurement document row was removed during soft deletion.'
            );
            $this->assertTrue(
                Storage::disk('local')->exists($localDocumentPath),
                'The private procurement document file was removed during soft deletion.'
            );
            $this->assertTrue(
                Storage::disk('public')->exists($coverImagePath),
                'The procurement cover file was removed during soft deletion.'
            );

            $historicalProcurement = FormSubmission::query()
                ->findOrFail($submission->id)
                ->procurement;
            $this->assertTrue(
                $historicalProcurement
                && (string) $historicalProcurement->id === (string) $linkedProcurement->id
                && $historicalProcurement->trashed(),
                'A child record did not resolve its soft-deleted procurement through withTrashed().'
            );

            $auditProcurement = ProcurementAuditLog::query()
                ->where('procurement_id', $linkedProcurement->id)
                ->where('action', 'Soft deleted procurement')
                ->latest('created_at')
                ->firstOrFail()
                ->procurement;
            $this->assertTrue(
                $auditProcurement?->trashed() === true,
                'The audit relation did not resolve the soft-deleted procurement.'
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

    private function assertResponseStatus($response, int $expected, string $message): void
    {
        $actual = $response->getStatusCode();
        if ($actual !== $expected) {
            throw new RuntimeException("{$message} Expected {$expected}, received {$actual}.");
        }
    }

    private function assertResponseContains($response, string $needle, string $message): void
    {
        $this->assertTrue(str_contains((string) $response->getContent(), $needle), $message);
    }

    private function assertResponseDoesNotContain($response, string $needle, string $message): void
    {
        $this->assertTrue(! str_contains((string) $response->getContent(), $needle), $message);
    }
}

(new ProcurementRegistryDeletionSmoke($app))->run();
