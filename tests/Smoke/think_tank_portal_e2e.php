<?php

use App\Models\Consortium;
use App\Models\ConsortiumFundAllocation;
use App\Models\ConsortiumThinkTank;
use App\Models\Funder;
use App\Models\FormSubmission;
use App\Models\Permission;
use App\Models\Procurement;
use App\Models\Role;
use App\Models\ThinkTankProcurementPlan;
use App\Models\ThinkTankResearchOutput;
use App\Models\User;
use App\Models\UserLoginOtp;
use Database\Seeders\ConsortiumOperationsPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithExceptionHandling;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

class ThinkTankPortalSmoke
{
    use MakesHttpRequests;
    use InteractsWithExceptionHandling;
    use InteractsWithAuthentication;
    use InteractsWithSession;

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function run(): void
    {
        $this->withoutExceptionHandling();
        Mail::fake();
        Storage::fake('local');

        Artisan::call('db:seed', ['--class' => ConsortiumOperationsPermissionsSeeder::class]);

        DB::beginTransaction();

        try {
            $data = $this->prepareData();

            $this->postWithCsrf('/login', [
                'email' => $data['thinkTankUser']->email,
                'password' => 'Password123!',
            ])->assertRedirect(route('security.otp.show'));

            $otp = UserLoginOtp::where('user_id', $data['thinkTankUser']->id)->latest()->first();
            $this->assertTrue((bool) $otp, 'Think tank login did not generate an OTP.');

            $this->asThinkTank($data['thinkTankUser'])
                ->get('/think-tank/dashboard')
                ->assertOk()
                ->assertSee('Dashboard')
                ->assertSee('Allocated');

            $this->asThinkTank($data['thinkTankUser'])
                ->postWithCsrf('/think-tank/research', [
                    'title' => 'E2E Agricultural Policy Research ' . Str::random(5),
                    'output_type' => 'research',
                    'published_on' => now()->toDateString(),
                    'abstract' => 'End to end research output submitted by the think tank.',
                    'file' => UploadedFile::fake()->create('research.pdf', 120, 'application/pdf'),
                ])
                ->assertRedirect();

            $this->assertTrue(
                ThinkTankResearchOutput::where('think_tank_member_id', $data['member']->id)->exists(),
                'Research output was not created.'
            );

            $this->asThinkTank($data['thinkTankUser'])
                ->postWithCsrf('/think-tank/procurement/plans', [
                    'title' => 'E2E Procurement Plan ' . Str::random(5),
                    'fiscal_year' => '2026',
                    'estimated_budget' => 25000,
                    'currency' => 'USD',
                    'planned_publish_date' => now()->addDays(5)->toDateString(),
                    'description' => 'End to end procurement plan.',
                ])
                ->assertRedirect();

            $plan = ThinkTankProcurementPlan::where('think_tank_member_id', $data['member']->id)->latest()->first();
            $this->assertTrue((bool) $plan, 'Procurement plan was not created.');

            $this->asThinkTank($data['thinkTankUser'])
                ->postWithCsrf('/think-tank/procurement', [
                    'think_tank_procurement_plan_id' => $plan->id,
                    'title' => 'E2E Procurement Opportunity ' . Str::random(5),
                    'reference_no' => 'E2E-' . Str::upper(Str::random(5)),
                    'description' => 'Public opportunity created from think tank portal.',
                    'fiscal_year' => '2026',
                    'estimated_budget' => 17500,
                    'application_start_date' => now()->subDay()->toDateString(),
                    'application_end_date' => now()->addDays(20)->toDateString(),
                    'status' => 'published',
                ])
                ->assertRedirect();

            $procurement = Procurement::where('think_tank_member_id', $data['member']->id)->latest()->first();
            $this->assertTrue((bool) $procurement, 'Procurement opportunity was not created.');

            $this->get(route('public.procurement.show', $procurement))
                ->assertOk()
                ->assertSee($procurement->title);

            $this->postWithCsrf(route('public.procurement.apply', $procurement), [
                'official_name' => 'E2E Vendor',
                'official_email' => 'e2e-vendor-' . Str::lower(Str::random(6)) . '@example.test',
                'organization_profile' => UploadedFile::fake()->create('profile.pdf', 80, 'application/pdf'),
                'technical_proposal' => UploadedFile::fake()->create('technical.pdf', 80, 'application/pdf'),
                'financial_proposal' => UploadedFile::fake()->create('financial.pdf', 80, 'application/pdf'),
                'quoted_amount' => 15500,
                'relevant_experience' => 'Relevant experience submitted for e2e test.',
            ])->assertRedirect();

            $submission = FormSubmission::where('procurement_id', $procurement->id)->latest()->first();
            $this->assertTrue((bool) $submission, 'Public procurement application was not created.');

            $this->asThinkTank($data['thinkTankUser'])
                ->get(route('think-tank.procurement.submissions', $procurement))
                ->assertOk()
                ->assertSee('Applications')
                ->assertSee('E2E Vendor');

            $this->asThinkTank($data['thinkTankUser'])
                ->postWithCsrf(route('think-tank.procurement.submissions.review', [$procurement, $submission]), [
                    'technical_score' => 82,
                    'financial_score' => 76,
                    'recommendation' => 'recommended',
                    'comments' => 'Strong submission.',
                ])
                ->assertRedirect();

            $this->asThinkTank($data['thinkTankUser'])
                ->postWithCsrf(route('think-tank.procurement.submissions.select', [$procurement, $submission]))
                ->assertRedirect();

            $procurement->refresh();
            $this->assertSame($submission->id, $procurement->awarded_submission_id, 'Winning submission was not selected.');

            $this->asThinkTank($data['thinkTankUser'])
                ->postWithCsrf('/think-tank/reports', [
                    'title' => 'E2E Monthly Activity Report',
                    'reporting_period_start' => now()->startOfMonth()->toDateString(),
                    'reporting_period_end' => now()->endOfMonth()->toDateString(),
                    'progress_percent' => 67,
                    'funds_spent' => 5100,
                    'summary' => 'End to end activity progress.',
                    'achievements' => 'Research and procurement completed.',
                    'challenges' => 'No blockers.',
                    'next_steps' => 'Continue implementation.',
                    'evidence_title' => 'Evidence Pack',
                    'evidence_file' => UploadedFile::fake()->create('evidence.pdf', 100, 'application/pdf'),
                ])
                ->assertRedirect();

            $this->asAdmin($data['adminUser'])
                ->get(route('consortium-operations.show', $data['consortium']))
                ->assertOk()
                ->assertSee('Think Tank Portal Oversight')
                ->assertSee($procurement->title)
                ->assertSee('Research Submitted');

            $this->asAdmin($data['adminUser'])
                ->get(route('think-tank.dashboard', ['think_tank_member_id' => $data['member']->id]))
                ->assertOk()
                ->assertSee('Dashboard')
                ->assertSee($data['member']->name);

            $this->asAdmin($data['adminUser'])
                ->get(route('think-tank.procurement.submissions', $procurement))
                ->assertOk()
                ->assertSee('Applications')
                ->assertSee('E2E Vendor');

            $this->asAdmin($data['adminUser'])
                ->get(route('partner.runtime-overview'))
                ->assertOk()
                ->assertSee('Runtime Partner Overview')
                ->assertSee('Research Outputs');

            $this->asPartner($data['partnerUser'])
                ->get(route('partner.runtime-overview'))
                ->assertOk()
                ->assertSee('Runtime Partner Overview')
                ->assertSee('Research Outputs')
                ->assertSee('Applications');

            echo "THINK_TANK_E2E_OK\n";
        } finally {
            DB::rollBack();
        }
    }

    private function prepareData(): array
    {
        $adminRole = Role::firstOrCreate(['name' => 'System Admin'], ['description' => 'System administrator']);
        $partnerRole = Role::firstOrCreate(['name' => 'Funding Partner'], ['description' => 'Funding partner']);
        $thinkTankRole = Role::firstOrCreate(['name' => 'Think Tank User'], ['description' => 'Think tank user']);

        $adminRole->permissions()->syncWithoutDetaching(Permission::pluck('id')->all());

        $partnerRuntimePermission = Permission::where('name', 'partner.runtime_overview.view')->firstOrFail();
        $partnerRole->permissions()->syncWithoutDetaching([$partnerRuntimePermission->id]);

        $thinkTankPermissions = Permission::whereIn('name', [
            'think_tank.portal.access',
            'think_tank.reports.submit',
            'think_tank.research.submit',
            'think_tank.procurement.manage',
            'think_tank.procurement.evaluate',
            'think_tank.procurement.select',
        ])->pluck('id')->all();
        $thinkTankRole->permissions()->syncWithoutDetaching($thinkTankPermissions);

        $adminUser = User::create([
            'name' => 'E2E Secretariat Admin',
            'email' => 'e2e-admin-' . Str::lower(Str::random(6)) . '@example.test',
            'password' => Hash::make('Password123!'),
            'user_type' => 'admin',
            'role_id' => $adminRole->id,
            'must_change_password' => false,
        ]);

        $partnerUser = User::create([
            'name' => 'E2E World Bank Partner',
            'email' => 'e2e-partner-' . Str::lower(Str::random(6)) . '@example.test',
            'password' => Hash::make('Password123!'),
            'user_type' => 'funding_partner',
            'role_id' => $partnerRole->id,
            'must_change_password' => false,
        ]);

        $thinkTankUser = User::create([
            'name' => 'E2E Think Tank User',
            'email' => 'e2e-thinktank-' . Str::lower(Str::random(6)) . '@example.test',
            'password' => Hash::make('Password123!'),
            'user_type' => 'think_tank',
            'role_id' => $thinkTankRole->id,
            'must_change_password' => false,
            'otp_verified_at' => now(),
        ]);

        $funder = Funder::create([
            'name' => 'E2E World Bank',
            'type' => 'donor',
            'currency' => 'USD',
            'has_portal_access' => true,
            'user_id' => $partnerUser->id,
            'partnership_status' => 'active',
        ]);

        $consortium = Consortium::create([
            'code' => 'E2E-CONS-' . Str::upper(Str::random(6)),
            'name' => 'E2E Consortium ' . Str::upper(Str::random(4)),
            'funder_id' => $funder->id,
            'secretariat_manager_id' => $adminUser->id,
            'country' => 'Ghana',
            'region' => 'West Africa',
            'approved_budget' => 100000,
            'currency' => 'USD',
            'status' => 'active',
            'mandate' => 'End to end smoke test consortium.',
        ]);

        $member = ConsortiumThinkTank::create([
            'consortium_id' => $consortium->id,
            'portal_user_id' => $thinkTankUser->id,
            'name' => 'E2E Policy Think Tank',
            'country' => 'Ghana',
            'email' => $thinkTankUser->email,
            'role' => 'lead',
            'budget_allocated' => 45000,
            'status' => 'active',
            'joined_at' => now()->toDateString(),
        ]);

        ConsortiumFundAllocation::create([
            'consortium_id' => $consortium->id,
            'think_tank_member_id' => $member->id,
            'budget_line' => 'Research implementation',
            'currency' => 'USD',
            'amount_allocated' => 45000,
            'amount_disbursed' => 15000,
            'amount_spent' => 3500,
        ]);

        return compact('adminUser', 'partnerUser', 'thinkTankUser', 'funder', 'consortium', 'member');
    }

    private function asThinkTank(User $user): self
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

    private function asAdmin(User $user): self
    {
        $this->actingAs($user);

        return $this;
    }

    private function asPartner(User $user): self
    {
        $this->actingAs($user);

        return $this;
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

(new ThinkTankPortalSmoke($app))->run();
