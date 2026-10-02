<?php

use App\Models\Consortium;
use App\Models\ConsortiumThinkTank;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\Role;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Models\User;
use App\Models\VendorCategory;
use App\Services\ThinkTank\ThinkTankSessionService;
use App\Services\ThinkTankProcurementApiService;
use App\Services\ThinkTankVendorDirectoryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

final class ThinkTankVendorDirectoryApiSmoke
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
        $this->assertTrue(
            DB::getSchemaBuilder()->hasTable('attp_think_tank_vendor_user'),
            'The tenant vendor directory migration is not applied.'
        );

        config([
            'mail.default' => 'array',
            'queue.default' => 'sync',
            'cache.default' => 'array',
            'think_tank_portal.email_lock_store' => 'array',
            'sanctum.stateful' => ['localhost:3100'],
            'think_tank_portal.allowed_origins' => ['http://localhost:3100'],
        ]);
        Mail::fake();
        Notification::fake();
        Bus::fake();
        Storage::fake('local');
        Storage::fake('public');
        DB::beginTransaction();

        try {
            $unsafeAutomaticMemberships = DB::table('attp_think_tank_vendor_user as membership')
                ->whereNull('membership.invited_by')
                ->where(function ($query): void {
                    $query->whereExists(function ($staff): void {
                        $staff->selectRaw('1')->from('users')
                            ->whereColumn('users.id', 'membership.vendor_user_id')
                            ->whereColumn('users.think_tank_member_id', 'membership.think_tank_member_id');
                    })->orWhereExists(function ($payee): void {
                        $payee->selectRaw('1')->from('attp_consortium_think_tanks as member')
                            ->whereColumn('member.id', 'membership.think_tank_member_id')
                            ->whereColumn('member.vendor_user_id', 'membership.vendor_user_id');
                    });
                })
                ->count();
            $this->assertSame(0, $unsafeAutomaticMemberships, 'Unsafe staff/payee auto-memberships remain after the corrective migration.');

            $token = Str::lower(Str::random(12));
            [$ownerA, $tenantA] = $this->tenant($token.'-a');
            [$ownerB, $tenantB] = $this->tenant($token.'-b');

            $this->asThinkTank($ownerA);
            $categoryAResponse = $this->postJson('/api/v1/think-tank/procurement/vendor-categories', [
                'name' => 'Professional Services',
                'description' => 'Tenant A professional service providers.',
            ]);
            $this->assertSame(201, $categoryAResponse->getStatusCode(), 'Tenant A could not create its category.');
            $categoryA = (string) $categoryAResponse->json('data.id');

            $this->asThinkTank($ownerB);
            $categoryBResponse = $this->postJson('/api/v1/think-tank/procurement/vendor-categories', [
                'name' => 'Professional Services',
                'description' => 'Tenant B category with the same display name.',
            ]);
            $this->assertSame(201, $categoryBResponse->getStatusCode(), 'Tenant B could not reuse the category name.');
            $categoryB = (string) $categoryBResponse->json('data.id');
            $this->assertTrue($categoryA !== $categoryB, 'Two tenants received the same category identifier.');

            $sharedEmail = "shared-vendor-{$token}@example.test";
            $this->asThinkTank($ownerA);
            $vendorAResponse = $this->postJson('/api/v1/think-tank/procurement/vendors', [
                'name' => 'Shared Vendor Original Name',
                'email' => $sharedEmail,
                'category_ids' => [$categoryA],
            ]);
            $this->assertSame(201, $vendorAResponse->getStatusCode(), 'Tenant A could not create a vendor.');
            $this->assertSame(true, $vendorAResponse->json('data.newAccount'), 'First vendor creation was not identified as a new account.');
            $this->assertSame(true, $vendorAResponse->json('data.credentialsSent'), 'Faked setup notification was not reported as delivered.');
            $this->assertSame(true, $vendorAResponse->json('data.setupPending'), 'Origin tenant did not retain pending-setup authority.');
            $sharedVendorId = (string) $vendorAResponse->json('data.id');

            $this->asThinkTank($ownerB);
            $vendorBResponse = $this->postJson('/api/v1/think-tank/procurement/vendors', [
                'name' => 'Attempted Cross-Tenant Rename',
                'email' => Str::upper($sharedEmail),
                'category_ids' => [$categoryB],
            ]);
            $this->assertSame(200, $vendorBResponse->getStatusCode(), 'Existing vendor was not associated with Tenant B.');
            $this->assertSame(false, $vendorBResponse->json('data.newAccount'), 'Existing identity was duplicated for Tenant B.');
            $this->assertSame(false, $vendorBResponse->json('data.setupPending'), 'A second tenant gained setup-token authority for a shared vendor.');
            $this->assertSame($sharedVendorId, (string) $vendorBResponse->json('data.id'), 'Same email resolved to a different vendor user.');
            $this->assertSame('Shared Vendor Original Name', User::query()->findOrFail($sharedVendorId)->name, 'A second tenant changed the global vendor identity.');
            $this->assertSame(
                2,
                DB::table('attp_think_tank_vendor_user')->where('vendor_user_id', $sharedVendorId)->count(),
                'Shared vendor did not receive two independent tenant memberships.'
            );
            $crossTenantResend = $this->postEmptyJson(
                "/api/v1/think-tank/procurement/vendors/{$sharedVendorId}/invitation"
            );
            $this->assertSame(404, $crossTenantResend->getStatusCode(), 'A second tenant could replace the origin tenant setup token.');

            $tenantBOnly = $this->postJson('/api/v1/think-tank/procurement/vendors', [
                'name' => 'Tenant B Only Vendor',
                'email' => "b-only-{$token}@example.test",
                'category_ids' => [$categoryB],
            ]);
            $this->assertSame(201, $tenantBOnly->getStatusCode(), 'Tenant B-only vendor creation failed.');
            $tenantBOnlyId = (string) $tenantBOnly->json('data.id');

            $this->asThinkTank($ownerA);
            $directoryA = $this->getJson('/api/v1/think-tank/procurement/vendor-directory');
            $this->assertSame(200, $directoryA->getStatusCode(), 'Tenant A directory failed.');
            $this->assertSame([$categoryA], collect($directoryA->json('data.categories'))->pluck('id')->all(), 'Tenant A category list leaked Tenant B data.');
            $this->assertTrue(
                collect($directoryA->json('data.vendors'))->pluck('id')->contains($sharedVendorId),
                'Tenant A lost its shared vendor.'
            );
            $this->assertSame(
                true,
                collect($directoryA->json('data.vendors'))->firstWhere('id', $sharedVendorId)['setupPending'] ?? null,
                'Setup authority did not survive a directory reload for the origin tenant.'
            );
            $this->assertTrue(
                ! collect($directoryA->json('data.vendors'))->pluck('id')->contains($tenantBOnlyId),
                'Tenant A listed Tenant B-only vendor.'
            );
            $crossVendorUpdate = $this->patchJson("/api/v1/think-tank/procurement/vendors/{$tenantBOnlyId}", [
                'status' => 'disabled',
            ]);
            $this->assertSame(404, $crossVendorUpdate->getStatusCode(), 'Tenant A updated Tenant B-only vendor.');

            $duplicateEmail = "duplicate-vendor-{$token}@example.test";
            foreach (['First duplicate', 'Second duplicate'] as $duplicateName) {
                User::query()->create([
                    'name' => $duplicateName,
                    'email' => $duplicateEmail,
                    'password' => Str::random(64),
                    'user_type' => 'vendor',
                    'must_change_password' => true,
                    'is_disabled' => false,
                    'is_blacklisted' => false,
                ]);
            }
            $duplicateIdentity = $this->postJson('/api/v1/think-tank/procurement/vendors', [
                'name' => 'Must not link',
                'email' => Str::upper($duplicateEmail),
                'category_ids' => [],
            ]);
            $this->assertSame(409, $duplicateIdentity->getStatusCode(), 'Duplicate normalized vendor identity did not fail closed.');
            $this->assertSame('IDENTITY_RECONCILIATION_REQUIRED', $duplicateIdentity->json('code'), 'Duplicate identity returned the wrong conflict code.');

            [$plan, $item] = $this->approvedItem($tenantA, $ownerA, $token);
            $lockToken = app(ThinkTankProcurementApiService::class)->lockToken($item->fresh());
            $crossCategoryExecution = $this->multipart('POST', '/api/v1/think-tank/procurement/executions', [
                'item_id' => (string) $item->id,
                'lock_token' => $lockToken,
                'visibility_type' => 'vendor_group',
                'tenant_vendor_category_ids' => [$categoryB],
            ]);
            $this->assertSame(422, $crossCategoryExecution->getStatusCode(), 'Tenant A used Tenant B category in an execution.');
            $crossVendorExecution = $this->multipart('POST', '/api/v1/think-tank/procurement/executions', [
                'item_id' => (string) $item->id,
                'lock_token' => app(ThinkTankProcurementApiService::class)->lockToken($item->fresh()),
                'visibility_type' => 'vendor_group',
                'tenant_vendor_ids' => [$tenantBOnlyId],
            ]);
            $this->assertSame(422, $crossVendorExecution->getStatusCode(), 'Tenant A targeted Tenant B-only vendor.');

            $directAResponse = $this->postJson('/api/v1/think-tank/procurement/vendors', [
                'name' => 'Tenant A Direct Vendor',
                'email' => "a-direct-{$token}@example.test",
                'category_ids' => [],
            ]);
            $this->assertSame(201, $directAResponse->getStatusCode(), 'Tenant A direct vendor creation failed.');
            $directAId = (string) $directAResponse->json('data.id');

            $validExecution = $this->multipart('POST', '/api/v1/think-tank/procurement/executions', [
                'item_id' => (string) $item->id,
                'lock_token' => app(ThinkTankProcurementApiService::class)->lockToken($item->fresh()),
                'description' => '<h2>Safe scope</h2><p onclick="alert(1)">Deliver equipment<script>alert(2)</script></p>',
                'application_start_date' => now()->toDateString(),
                'application_end_date' => now()->addWeek()->toDateString(),
                'visibility_type' => 'vendor_group',
                'tenant_vendor_category_ids' => [$categoryA],
                'tenant_vendor_ids' => [$directAId],
            ]);
            $this->assertSame(201, $validExecution->getStatusCode(), 'Valid tenant-targeted execution failed: '.$validExecution->getContent());
            $this->assertSame([$categoryA], $validExecution->json('data.tenantVendorCategoryIds'), 'Category UUID target was not persisted.');
            $this->assertSame([$directAId], $validExecution->json('data.tenantVendorIds'), 'Direct vendor UUID target was not persisted.');
            $this->assertTrue(! str_contains((string) $validExecution->json('data.description'), 'script'), 'Unsafe script reached the API description DTO.');
            $execution = Procurement::query()->findOrFail((string) $validExecution->json('data.id'));

            $bidderPath = "procurements/{$execution->id}/documents/vendor-directory-smoke.pdf";
            Storage::disk('local')->put($bidderPath, "%PDF-1.4\nTenant vendor directory smoke\n");
            $bidderDocument = ProcurementDocument::query()->create([
                'procurement_id' => $execution->id,
                'document_name' => 'Bidder instructions',
                'original_name' => 'vendor-directory-smoke.pdf',
                'file_path' => $bidderPath,
                'mime_type' => 'application/pdf',
                'file_size' => Storage::disk('local')->size($bidderPath),
                'audience' => ProcurementDocument::AUDIENCE_BIDDER,
                'uploaded_by' => $ownerA->id,
            ]);

            $disableDirectBeforePublish = $this->patchJson("/api/v1/think-tank/procurement/vendors/{$directAId}", [
                'status' => 'disabled',
            ]);
            $this->assertSame(200, $disableDirectBeforePublish->getStatusCode(), 'Could not stage inactive-target publication check.');
            $draftForBlockedPublish = $this->getJson("/api/v1/think-tank/procurement/executions/{$execution->id}");
            $blockedPublish = $this->postJson("/api/v1/think-tank/procurement/executions/{$execution->id}/publish", [
                'lock_token' => $draftForBlockedPublish->json('data.lockToken'),
                'confirmation' => true,
            ]);
            $this->assertSame(422, $blockedPublish->getStatusCode(), 'Execution published with an inactive directly targeted vendor.');
            $enableDirect = $this->patchJson("/api/v1/think-tank/procurement/vendors/{$directAId}", [
                'status' => 'active',
            ]);
            $this->assertSame(200, $enableDirect->getStatusCode(), 'Could not restore direct vendor membership.');
            $draftForPublish = $this->getJson("/api/v1/think-tank/procurement/executions/{$execution->id}");
            $publish = $this->postJson("/api/v1/think-tank/procurement/executions/{$execution->id}/publish", [
                'lock_token' => $draftForPublish->json('data.lockToken'),
                'confirmation' => true,
            ]);
            $this->assertSame(200, $publish->getStatusCode(), 'Valid tenant-targeted execution could not be published: '.$publish->getContent());
            $execution = $execution->fresh();

            $directory = app(ThinkTankVendorDirectoryService::class);
            $sharedVendor = User::query()->findOrFail($sharedVendorId);
            $directVendor = User::query()->findOrFail($directAId);
            $otherTenantVendor = User::query()->findOrFail($tenantBOnlyId);
            $this->assertTrue($directory->vendorCanAccess($sharedVendor, $execution), 'Tenant category member cannot access its execution.');
            $this->assertTrue($directory->vendorCanAccess($directVendor, $execution), 'Directly targeted tenant vendor cannot access its execution.');
            $this->assertTrue(! $directory->vendorCanAccess($otherTenantVendor, $execution), 'Another tenant vendor accessed Tenant A execution.');

            $this->asVendor($otherTenantVendor);
            $vendorIndex = $this->get('/vendor/procurements');
            $this->assertSame(200, $vendorIndex->getStatusCode(), 'Vendor procurement index failed.');
            $this->assertTrue(
                ! str_contains((string) $vendorIndex->getContent(), 'Tenant-targeted field supplies'),
                'Vendor index leaked another tenant execution.'
            );
            $this->assertSame(
                404,
                $this->get("/vendor/procurements/{$execution->slug}")->getStatusCode(),
                'Vendor show route did not deny another tenant vendor.'
            );
            $this->assertSame(
                404,
                $this->get("/vendor/procurements/{$execution->slug}/documents/{$bidderDocument->id}/download")->getStatusCode(),
                'Vendor document route did not deny another tenant vendor.'
            );
            $this->assertSame(
                404,
                $this->post("/vendor/procurements/{$execution->slug}", [])->getStatusCode(),
                'Vendor submission route did not deny another tenant vendor.'
            );

            $this->asVendor($directVendor);
            $this->assertSame(
                200,
                $this->get("/vendor/procurements/{$execution->slug}")->getStatusCode(),
                'Directly targeted vendor could not open the execution.'
            );

            $directVendor->forceFill([
                'must_change_password' => false,
                'password_changed_at' => now(),
            ])->save();
            $this->asThinkTank($ownerA);
            $establishedResend = $this->postEmptyJson(
                "/api/v1/think-tank/procurement/vendors/{$directAId}/invitation",
            );
            $this->assertSame(422, $establishedResend->getStatusCode(), 'Established vendor received a new setup-token flow.');

            $tenantBExecution = Procurement::query()->create([
                'consortium_id' => $tenantB->consortium_id,
                'think_tank_member_id' => $tenantB->id,
                'procurement_owner_type' => 'think_tank',
                'title' => 'Tenant B category-only opportunity',
                'description' => 'Tenant B scope',
                'status' => 'published',
                'visibility_type' => 'vendor_group',
                'tenant_vendor_category_ids' => [$categoryB],
                'tenant_vendor_ids' => null,
                'vendor_categories' => ['Professional Services'],
                'created_by' => $ownerB->id,
            ]);
            $this->assertTrue($directory->vendorCanAccess($sharedVendor, $tenantBExecution), 'Shared vendor category assignment in Tenant B was ignored.');

            $this->asThinkTank($ownerB);
            $disableB = $this->patchJson("/api/v1/think-tank/procurement/vendors/{$sharedVendorId}", [
                'status' => 'disabled',
                'category_ids' => [],
            ]);
            $this->assertSame(200, $disableB->getStatusCode(), 'Tenant B could not disable its vendor membership.');
            $this->assertTrue(! $directory->vendorCanAccess($sharedVendor->fresh(), $tenantBExecution->fresh()), 'Disabled Tenant B membership still grants access.');
            $this->assertTrue($directory->vendorCanAccess($sharedVendor->fresh(), $execution->fresh()), 'Tenant B disablement revoked Tenant A access.');
            $this->assertSame(
                'active',
                DB::table('attp_think_tank_vendor_user')
                    ->where('think_tank_member_id', $tenantA->id)
                    ->where('vendor_user_id', $sharedVendorId)
                    ->value('status'),
                'Tenant B status update changed Tenant A membership.'
            );

            $globalCategory = VendorCategory::query()->create([
                'name' => 'Legacy '.Str::upper($token),
                'description' => 'Secretariat-wide legacy category.',
                'is_active' => true,
                'created_by' => $ownerA->id,
            ]);
            $legacyVendor = User::query()->create([
                'name' => 'Legacy Secretariat Vendor',
                'email' => "legacy-vendor-{$token}@example.test",
                'password' => Str::random(64),
                'user_type' => 'vendor',
                'vendor_category' => $globalCategory->name,
                'must_change_password' => false,
                'is_disabled' => false,
                'is_blacklisted' => false,
            ]);
            $legacyProcurement = Procurement::query()->create([
                'title' => 'Legacy Secretariat opportunity',
                'description' => 'Legacy global category flow.',
                'status' => 'published',
                'visibility_type' => 'vendor_group',
                'vendor_categories' => [$globalCategory->name],
                'procurement_owner_type' => 'secretariat',
                'created_by' => $ownerA->id,
            ]);
            $this->assertTrue($directory->vendorCanAccess($legacyVendor, $legacyProcurement), 'Legacy Secretariat category access regressed.');

            Notification::assertSentTo($sharedVendor, \App\Notifications\ApplicationAccountSetupNotification::class, 1);
            Mail::assertNothingSent();
            Bus::assertNothingDispatched();

            echo "THINK_TANK_VENDOR_DIRECTORY_API_OK\n";
        } finally {
            DB::rollBack();
            $this->app['auth']->forgetGuards();
        }
    }

    /** @return array{User, ConsortiumThinkTank} */
    private function tenant(string $token): array
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'Think Tank User'],
            ['description' => 'Think tank staff account'],
        );
        $user = User::query()->create([
            'name' => 'Vendor Directory Owner '.Str::headline($token),
            'email' => "vendor-directory-owner-{$token}@example.test",
            'password' => Str::random(64),
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
            'code' => 'VD-'.$token,
            'name' => 'Vendor Directory Consortium '.$token,
            'country' => 'Kenya',
            'region' => 'Eastern Africa',
            'approved_budget' => 50000,
            'currency' => 'USD',
            'status' => 'active',
        ]);
        $tenant = ConsortiumThinkTank::query()->create([
            'consortium_id' => $consortium->id,
            'portal_user_id' => $user->id,
            'name' => 'Vendor Directory Think Tank '.$token,
            'country' => 'Kenya',
            'email' => $user->email,
            'role' => 'lead',
            'budget_allocated' => 50000,
            'status' => 'active',
            'joined_at' => now()->toDateString(),
        ]);
        $user->forceFill(['think_tank_member_id' => $tenant->id])->save();

        return [$user->fresh(), $tenant];
    }

    /** @return array{ThinkTankProcurementPlan, ThinkTankProcurementItem} */
    private function approvedItem(ConsortiumThinkTank $tenant, User $owner, string $token): array
    {
        $plan = ThinkTankProcurementPlan::query()->create([
            'consortium_id' => $tenant->consortium_id,
            'think_tank_member_id' => $tenant->id,
            'plan_code' => 'VD-PLAN-'.Str::upper($token),
            'title' => 'Vendor directory execution smoke plan',
            'fiscal_year' => '2026',
            'estimated_budget' => 8000,
            'currency' => 'USD',
            'status' => ThinkTankProcurementPlan::STATUS_APPROVED,
            'version' => 1,
            'created_by' => $owner->id,
            'approved_at' => now(),
        ]);
        $item = $plan->items()->create([
            'item_code' => $plan->plan_code.'-001',
            'title' => 'Tenant-targeted field supplies',
            'description' => 'Supply approved field materials.',
            'procurement_category' => 'goods',
            'procurement_method' => 'RFQ',
            'estimated_amount' => 8000,
            'currency' => 'USD',
            'status' => ThinkTankProcurementItem::STATUS_NO_OBJECTION,
            'source_activity_status' => ThinkTankProcurementItem::ACTIVITY_STATUS_WORLD_BANK_APPROVED,
            'no_objection_reference' => 'WB-VD-'.Str::upper($token),
            'no_objection_date' => now()->subDay()->toDateString(),
            'no_objection_recorded_at' => now()->subDay(),
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        return [$plan, $item];
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

    private function asVendor(User $user): self
    {
        $user->forceFill([
            'must_change_password' => false,
            'password_changed_at' => $user->password_changed_at ?: now(),
            'email_verified_at' => $user->email_verified_at ?: now(),
            'otp_verified_at' => now(),
        ])->save();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs($user->fresh());
        $this->withSession([
            '_token' => $this->csrfToken,
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);

        return $this;
    }

    private function multipart(string $method, string $uri, array $parameters)
    {
        return $this->call(
            $method,
            $uri,
            $parameters,
            [],
            [],
            $this->transformHeadersToServerVars([
                'Accept' => 'application/json',
                'Origin' => 'http://localhost:3100',
                'Referer' => 'http://localhost:3100/',
                'X-CSRF-TOKEN' => $this->csrfToken,
                'Content-Type' => 'multipart/form-data; boundary=attp-vendor-directory-smoke',
            ]),
        );
    }

    private function postEmptyJson(string $uri)
    {
        return $this->call(
            'POST',
            $uri,
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Accept' => 'application/json',
                'Origin' => 'http://localhost:3100',
                'Referer' => 'http://localhost:3100/',
                'X-CSRF-TOKEN' => $this->csrfToken,
                'Content-Type' => 'application/json',
            ]),
            '{}',
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

(new ThinkTankVendorDirectoryApiSmoke($app))->run();
