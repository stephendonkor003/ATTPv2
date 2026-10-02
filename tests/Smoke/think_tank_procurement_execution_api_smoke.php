<?php

use App\Models\Consortium;
use App\Models\ConsortiumThinkTank;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\Role;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Models\ThinkTankProcurementEvent;
use App\Models\ThinkTankProcurementStatusNotification;
use App\Models\User;
use App\Services\ProcurementOpportunityExpiryService;
use App\Services\ThinkTank\ThinkTankSessionService;
use App\Services\ThinkTankProcurementApiService;
use App\Mail\VendorProcurementLifecycleMail;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Standalone smoke scripts do not enter PHPUnit's normal CLI bootstrap.
(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

final class ThinkTankProcurementExecutionApiSmoke
{
    use InteractsWithAuthentication;
    use InteractsWithSession;
    use MakesHttpRequests;

    protected $app;

    private string $csrfToken;

    public function __construct($app)
    {
        $this->app = $app;
        $this->csrfToken = Str::random(40);
    }

    public function run(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName(), 'This smoke must run against PostgreSQL.');

        config([
            'mail.default' => 'array',
            'queue.default' => 'sync',
            'cache.default' => 'array',
            'sanctum.stateful' => ['localhost:3100'],
            'think_tank_portal.allowed_origins' => ['http://localhost:3100'],
        ]);
        Mail::fake();
        Bus::fake();
        Storage::fake('local');
        Storage::fake('public');
        DB::beginTransaction();

        try {
            [$owner, $other, $plan, $item] = $this->context();
            $this->asThinkTank($owner);

            $eligible = $this->getJson('/api/v1/think-tank/procurement/executions/eligible-items');
            $this->assertSame(200, $eligible->getStatusCode(), 'Eligible-items endpoint did not return 200.');
            $this->assertNoStore($eligible);
            $eligibleItem = collect((array) $eligible->json('data.items'))
                ->firstWhere('id', (string) $item->id);
            $this->assertTrue(is_array($eligibleItem), 'The no-objection-ready item was not returned as eligible.');
            $this->assertSame('rfq', data_get($eligibleItem, 'procurementMethod.code'), 'The inherited method was not normalized to RFQ.');

            $create = $this->multipart('POST', '/api/v1/think-tank/procurement/executions', [
                'item_id' => (string) $item->id,
                'lock_token' => (string) $eligibleItem['lockToken'],
                'application_start_date' => now()->toDateString(),
                'application_end_date' => now()->addDays(7)->toDateString(),
                'visibility_type' => 'public',
                'documents' => [[
                    'name' => 'Request for quotations instructions',
                    'audience' => 'bidder',
                    'file' => UploadedFile::fake()->create('rfq-instructions.pdf', 12, 'application/pdf'),
                ]],
                'cover_image' => UploadedFile::fake()->image('opportunity-cover.png', 40, 40),
            ]);
            $this->assertSame(201, $create->getStatusCode(), 'Draft creation failed: '.$create->getContent());
            $this->assertNoStore($create);
            $this->assertSame('draft', $create->json('data.status'), 'Creation must produce a draft, not publish immediately.');
            $this->assertSame((string) $item->id, $create->json('data.sourceItem.id'), 'The execution lost its source-item link.');
            $this->assertSame('rfq', $create->json('data.procurementMethod.code'), 'Execution did not inherit its method.');
            $this->assertTrue(
                collect((array) $create->json('data.form.fields'))->contains(fn (array $field): bool => $field['key'] === 'signed_quotation'),
                'The method-appropriate RFQ form was not created.'
            );
            $this->assertTrue(
                collect((array) $create->json('data.documents'))->contains(fn (array $document): bool => $document['audience'] === 'internal'),
                'Supporting planning evidence was not retained as internal.'
            );
            $this->assertTrue(
                collect((array) $create->json('data.documents'))->contains(fn (array $document): bool => $document['audience'] === 'bidder'),
                'No bidder-facing procurement document was created.'
            );

            $executionId = (string) $create->json('data.id');
            $documentUrl = (string) data_get($create->json(), 'data.documents.0.downloadUrl');
            $coverUrl = (string) $create->json('data.coverImageUrl');
            $this->assertSame(
                "/api/v1/think-tank/procurement/executions/{$executionId}/cover",
                $coverUrl,
                'The cover must use the tenant-authorized API endpoint.'
            );
            $cover = $this->get($coverUrl);
            $this->assertSame(200, $cover->getStatusCode(), 'Authorized cover preview failed.');
            $this->assertNoStore($cover);
            $this->assertTrue(str_starts_with((string) $cover->headers->get('Content-Type'), 'image/'), 'Cover preview is not served as an image.');
            $document = $this->get($documentUrl);
            $this->assertSame(200, $document->getStatusCode(), 'Authorized document download failed.');
            $this->assertNoStore($document);

            $freshItemToken = app(ThinkTankProcurementApiService::class)->lockToken($item->fresh());
            $duplicate = $this->multipart('POST', '/api/v1/think-tank/procurement/executions', [
                'item_id' => (string) $item->id,
                'lock_token' => $freshItemToken,
            ]);
            $this->assertSame(422, $duplicate->getStatusCode(), 'A second execution for one item was not rejected.');
            $this->assertSame(
                1,
                Procurement::query()->where('think_tank_procurement_plan_id', $plan->id)->count(),
                'Duplicate prevention did not preserve exactly one execution.'
            );

            $this->asThinkTank($other);
            $crossTenant = $this->getJson("/api/v1/think-tank/procurement/executions/{$executionId}");
            $this->assertSame(404, $crossTenant->getStatusCode(), 'A different Think Tank could read the execution.');
            $crossTenantCover = $this->get($coverUrl);
            $this->assertSame(404, $crossTenantCover->getStatusCode(), 'A different Think Tank could read the cover image.');
            $crossTenantDocument = $this->get($documentUrl);
            $this->assertSame(404, $crossTenantDocument->getStatusCode(), 'A different Think Tank could download an execution document.');
            $crossTenantFormUpdate = $this->putJson("/api/v1/think-tank/procurement/executions/{$executionId}/form", [
                'lock_token' => $create->json('data.lockToken'),
                'name' => 'Cross-tenant mutation attempt',
                'fields' => [[
                    'key' => 'cross_tenant_field',
                    'label' => 'Cross tenant field',
                    'type' => 'text',
                    'required' => false,
                ]],
            ]);
            $this->assertSame(404, $crossTenantFormUpdate->getStatusCode(), 'A different Think Tank could mutate the application form.');

            $this->asThinkTank($owner);
            $latest = $this->getJson("/api/v1/think-tank/procurement/executions/{$executionId}");
            $formSavePayload = [
                'lock_token' => $latest->json('data.lockToken'),
                'name' => 'Execution smoke bidder form',
                'fields' => [[
                    'key' => 'delivery_location',
                    'label' => 'Delivery location',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['Nairobi', 'Washington, D.C.'],
                    'sort_order' => 110,
                ]],
            ];
            $formSave = $this->putJson(
                "/api/v1/think-tank/procurement/executions/{$executionId}/form",
                $formSavePayload,
            );
            $this->assertSame(200, $formSave->getStatusCode(), 'A valid form save failed: '.$formSave->getContent());
            $this->assertSame(
                ['Nairobi', 'Washington, D.C.'],
                collect((array) $formSave->json('data.form.fields'))->firstWhere('key', 'delivery_location')['options'] ?? [],
                'A punctuation-bearing choice did not round trip exactly.',
            );
            $savedKeys = collect((array) $formSave->json('data.form.fields'))->pluck('key');
            $this->assertTrue($savedKeys->contains('official_name') && $savedKeys->contains('official_email'), 'Protected identity fields were not retained.');
            $this->assertTrue(
                $latest->json('data.lockToken') !== $formSave->json('data.lockToken'),
                'A successful form save did not advance the execution lock.',
            );
            $staleFormSave = $this->putJson(
                "/api/v1/think-tank/procurement/executions/{$executionId}/form",
                $formSavePayload,
            );
            $this->assertSame(409, $staleFormSave->getStatusCode(), 'The pre-save lock token allowed a stale form overwrite.');
            $this->assertSame('STALE_WRITE', $staleFormSave->json('code'), 'The stale form overwrite did not return the strict API code.');

            $publish = $this->postJson("/api/v1/think-tank/procurement/executions/{$executionId}/publish", [
                'lock_token' => $formSave->json('data.lockToken'),
                'confirmation' => true,
            ]);
            $this->assertSame(200, $publish->getStatusCode(), 'Publication failed: '.$publish->getContent());
            $this->assertSame('published', $publish->json('data.status'), 'Execution was not published.');
            $this->assertSame(
                ThinkTankProcurementItem::STATUS_PUBLISHED,
                $item->fresh()->status,
                'The source item did not enter published execution state.'
            );
            $this->assertTrue(
                collect((array) $publish->json('data.checklist'))->every(fn (array $step): bool => (bool) $step['complete']),
                'The published record contains an incomplete publication checklist.'
            );
            $publishedFormUpdate = $this->putJson("/api/v1/think-tank/procurement/executions/{$executionId}/form", [
                'lock_token' => $publish->json('data.lockToken'),
                'name' => 'Published mutation attempt',
                'fields' => [[
                    'key' => 'published_field',
                    'label' => 'Published field',
                    'type' => 'text',
                    'required' => false,
                ]],
            ]);
            $this->assertSame(422, $publishedFormUpdate->getStatusCode(), 'A published application form remained editable.');

            $vendor = User::query()->create([
                'name' => 'Execution Smoke Applicant',
                'email' => 'execution-smoke-applicant-'.Str::lower(Str::random(10)).'@example.test',
                'password' => Str::random(40),
                'user_type' => 'vendor',
                'must_change_password' => false,
                'password_changed_at' => now(),
                'is_disabled' => false,
                'is_blacklisted' => false,
            ]);
            $submission = FormSubmission::query()->create([
                'procurement_id' => $executionId,
                'form_id' => $publish->json('data.form.id'),
                'submitted_by' => $vendor->id,
                'status' => FormSubmission::STATUS_SUBMITTED,
                'submitted_at' => now(),
                'publication_version' => 1,
            ]);

            $recall = $this->postJson("/api/v1/think-tank/procurement/executions/{$executionId}/recall", [
                'lock_token' => $publish->json('data.lockToken'),
                'recall_reason' => 'The closing instructions require a controlled correction before applications continue.',
            ]);
            $this->assertSame(200, $recall->getStatusCode(), 'Recall failed: '.$recall->getContent());
            $this->assertSame('recalled', $recall->json('data.status'), 'Recall did not move the execution to recalled.');
            $this->assertSame(1, $recall->json('notificationsQueued'), 'Recall did not queue one applicant lifecycle email.');
            $this->assertSame(FormSubmission::STATUS_REVISION_REQUESTED, $submission->fresh()->status, 'Recall did not preserve the application for revision.');
            Mail::assertQueued(VendorProcurementLifecycleMail::class, fn (VendorProcurementLifecycleMail $mail): bool =>
                $mail->event === 'recalled' && (string) $mail->vendor->id === (string) $vendor->id
            );

            $republish = $this->postJson("/api/v1/think-tank/procurement/executions/{$executionId}/republish", [
                'lock_token' => $recall->json('data.lockToken'),
                'application_start_date' => now()->toDateString(),
                'application_end_date' => now()->addDays(10)->toDateString(),
            ]);
            $this->assertSame(200, $republish->getStatusCode(), 'Republication failed: '.$republish->getContent());
            $this->assertSame('published', $republish->json('data.status'), 'Republication did not restore published state.');
            $this->assertSame(2, $republish->json('data.publicationVersion'), 'Republication did not increment the version.');
            $this->assertSame(1, $republish->json('notificationsQueued'), 'Republication did not queue one applicant lifecycle email.');
            Mail::assertQueued(VendorProcurementLifecycleMail::class, fn (VendorProcurementLifecycleMail $mail): bool =>
                $mail->event === 'republished' && (string) $mail->vendor->id === (string) $vendor->id
            );

            $execution = Procurement::query()->findOrFail($executionId);
            $execution->forceFill(['application_end_date' => now()->subDay()->toDateString()])->save();
            $expiry = app(ProcurementOpportunityExpiryService::class);
            $this->assertTrue($expiry->closeOne($executionId), 'The expired published execution was not closed.');
            $this->assertSame('closed', $execution->fresh()->status, 'Expiry did not persist the closed status.');

            $closureEvents = ThinkTankProcurementEvent::query()
                ->where('item_id', $item->id)
                ->where('action', 'item_publication_closed')
                ->get();
            $this->assertSame(1, $closureEvents->count(), 'Expiry did not create exactly one closure workflow event.');
            $closureEvent = $closureEvents->first();
            $this->assertSame('published', $closureEvent->from_status, 'Closure event did not record the published source state.');
            $this->assertSame('closed', $closureEvent->to_status, 'Closure event did not record the closed target state.');
            $this->assertSame(
                1,
                ThinkTankProcurementStatusNotification::query()
                    ->where('event_id', $closureEvent->id)
                    ->where('recipient_email', Str::lower($owner->email))
                    ->count(),
                'Expiry did not stage exactly one durable closure notice for the Think Tank administrator.'
            );

            $this->assertTrue(! $expiry->closeOne($executionId), 'A second expiry pass was not idempotent.');
            $this->assertSame(
                1,
                ThinkTankProcurementEvent::query()
                    ->where('item_id', $item->id)
                    ->where('action', 'item_publication_closed')
                    ->count(),
                'A second expiry pass duplicated the closure workflow event.'
            );

            Mail::assertNothingSent();
            Mail::assertQueued(VendorProcurementLifecycleMail::class, 2);
            Bus::assertNothingDispatched();

            echo "THINK_TANK_PROCUREMENT_EXECUTION_API_OK\n";
        } finally {
            DB::rollBack();
            $this->app['auth']->forgetGuards();
        }
    }

    /** @return array{User, User, ThinkTankProcurementPlan, ThinkTankProcurementItem} */
    private function context(): array
    {
        $token = Str::lower(Str::random(12));
        $role = Role::query()->firstOrCreate(
            ['name' => 'Think Tank User'],
            ['description' => 'Think tank portal user'],
        );
        [$owner, $ownerMember] = $this->tenant($role, $token.'-owner');
        [$other] = $this->tenant($role, $token.'-other');

        $plan = ThinkTankProcurementPlan::query()->create([
            'consortium_id' => $ownerMember->consortium_id,
            'think_tank_member_id' => $ownerMember->id,
            'plan_code' => 'EXEC-'.Str::upper($token),
            'title' => 'Procurement execution API smoke plan',
            'fiscal_year' => '2026',
            'estimated_budget' => 9600,
            'currency' => 'USD',
            'status' => ThinkTankProcurementPlan::STATUS_APPROVED,
            'version' => 1,
            'created_by' => $owner->id,
            'approved_at' => now(),
        ]);
        $item = $plan->items()->create([
            'item_code' => $plan->plan_code.'-001',
            'title' => 'Bridge field equipment and supplies',
            'description' => 'Supply and deliver field equipment in accordance with the approved specifications.',
            'procurement_category' => 'goods',
            'procurement_method' => 'RFQ',
            'estimated_amount' => 9600,
            'currency' => 'USD',
            'status' => ThinkTankProcurementItem::STATUS_NO_OBJECTION,
            'source_activity_status' => ThinkTankProcurementItem::ACTIVITY_STATUS_WORLD_BANK_APPROVED,
            'no_objection_reference' => 'WB-NO-'.Str::upper($token),
            'no_objection_date' => now()->subDay()->toDateString(),
            'no_objection_recorded_at' => now()->subDay(),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        foreach ([
            ['type' => 'tor', 'name' => 'Terms of Reference', 'file' => 'terms-of-reference.pdf'],
            ['type' => 'supporting', 'name' => 'Internal budget evidence', 'file' => 'budget-evidence.pdf'],
        ] as $document) {
            $path = "think-tank-procurement/{$plan->id}/{$item->id}/{$document['file']}";
            Storage::disk('local')->put($path, "%PDF-1.4\nprocurement execution smoke\n");
            $item->documents()->create([
                'document_type' => $document['type'],
                'document_name' => $document['name'],
                'original_name' => $document['file'],
                'file_path' => $path,
                'mime_type' => 'application/pdf',
                'file_size' => Storage::disk('local')->size($path),
                'uploaded_by' => $owner->id,
            ]);
        }

        return [$owner, $other, $plan, $item];
    }

    /** @return array{User, ConsortiumThinkTank} */
    private function tenant(Role $role, string $token): array
    {
        $user = User::query()->create([
            'name' => 'Execution Smoke '.Str::headline($token),
            'email' => "execution-smoke-{$token}@example.test",
            'password' => Str::random(40),
            'user_type' => 'think_tank',
            'role_id' => $role->id,
            'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
            'must_change_password' => false,
            'password_changed_at' => now(),
            'otp_verified_at' => now(),
            'is_disabled' => false,
            'is_blacklisted' => false,
        ]);
        $consortium = Consortium::query()->create([
            'code' => 'EX-'.$token,
            'name' => 'Execution Smoke Consortium '.$token,
            'country' => 'Kenya',
            'region' => 'Eastern Africa',
            'approved_budget' => 25000,
            'currency' => 'USD',
            'status' => 'active',
        ]);
        $member = ConsortiumThinkTank::query()->create([
            'consortium_id' => $consortium->id,
            'portal_user_id' => $user->id,
            'name' => 'Execution Smoke Think Tank '.$token,
            'country' => 'Kenya',
            'email' => $user->email,
            'role' => 'lead',
            'budget_allocated' => 25000,
            'status' => 'active',
            'joined_at' => now()->toDateString(),
        ]);
        $user->forceFill(['think_tank_member_id' => $member->id])->save();

        return [$user->fresh(), $member];
    }

    private function asThinkTank(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs($user);
        $session = $this->app->make('session.store');
        $request = Request::create('/');
        $request->setLaravelSession($session);
        app(ThinkTankSessionService::class)->bindCurrentSession($user, $request);
        $this->withSession([
            '_token' => $this->csrfToken,
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
            'think_tank_security_stamp' => $session->get('think_tank_security_stamp'),
        ]);
        $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => 'http://localhost:3100',
            'Referer' => 'http://localhost:3100/',
            'X-CSRF-TOKEN' => $this->csrfToken,
        ]);

        return $this;
    }

    private function multipart(string $method, string $uri, array $parameters, array $files = [])
    {
        return $this->call(
            $method,
            $uri,
            $parameters,
            [],
            $files,
            $this->transformHeadersToServerVars([
                'Accept' => 'application/json',
                'Origin' => 'http://localhost:3100',
                'Referer' => 'http://localhost:3100/',
                'X-CSRF-TOKEN' => $this->csrfToken,
                'Content-Type' => 'multipart/form-data; boundary=attp-execution-smoke',
            ]),
        );
    }

    private function assertNoStore($response): void
    {
        $this->assertTrue(
            str_contains(Str::lower((string) $response->headers->get('Cache-Control')), 'no-store'),
            'Sensitive API response is missing Cache-Control: no-store.'
        );
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    private function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message.' Expected '.var_export($expected, true).', got '.var_export($actual, true).'.');
        }
    }
}

(new ThinkTankProcurementExecutionApiSmoke($app))->run();
