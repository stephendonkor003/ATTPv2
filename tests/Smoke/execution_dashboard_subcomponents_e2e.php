<?php

use App\Models\Activity;
use App\Models\ActivityAllocation;
use App\Models\BudgetCommitment;
use App\Models\ProcurementDisbursement;
use App\Models\Program;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Role;
use App\Models\Sector;
use App\Models\SubActivity;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

class ExecutionDashboardSubcomponentsSmoke
{
    use InteractsWithAuthentication;
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
            [$admin, $sector, $program, $component] = $this->portfolioFixture();
            [$firstSubComponent, $secondSubComponent, $secondSubActivity] = $this->subComponentFixture($component);
            $this->financialFixture($component, $firstSubComponent, $secondSubActivity);

            $filters = [
                'sector_id' => $sector->id,
                'program_id' => $program->id,
                'project_id' => $component->id,
            ];

            $webResponse = $this->actingAs($admin)
                ->get(route('finance.execution.dashboard', $filters));

            $webResponse
                ->assertOk()
                ->assertSeeInOrder([
                    'Selected Component - Global Execution Performance',
                    'Sub-component Execution Performance Breakdown',
                ])
                ->assertSee('Sub-component')
                ->assertSee($firstSubComponent->name)
                ->assertSee($secondSubComponent->name)
                ->assertSee('Component-level / Unassigned Balance')
                ->assertSee('100,000.00')
                ->assertSee('200,000.00')
                ->assertSee('30,000.00');

            $pdfResponse = $this->actingAs($admin)
                ->get(route('finance.execution.dashboard.export.pdf', $filters));

            $this->assertResponseStatus($pdfResponse, 200, 'The selected-component PDF did not generate.');
            $this->assertTrue(
                str_starts_with((string) $pdfResponse->headers->get('Content-Type'), 'application/pdf'),
                'The selected-component export was not returned as a PDF.'
            );
            $this->assertTrue(
                (int) $pdfResponse->headers->get('Content-Length') > 1_000,
                'The selected-component PDF was unexpectedly empty.'
            );

            echo "EXECUTION_DASHBOARD_SUBCOMPONENTS_E2E_OK\n";
        } finally {
            DB::rollBack();
        }
    }

    private function portfolioFixture(): array
    {
        $suffix = Str::lower(Str::random(8));
        $role = Role::create([
            'name' => 'Execution Dashboard Smoke '.Str::upper($suffix),
            'description' => 'Temporary execution dashboard test role.',
        ]);
        $admin = User::create([
            'name' => 'Execution Dashboard Test Admin',
            'email' => 'execution-dashboard-'.$suffix.'@example.test',
            'password' => Hash::make('Password123!'),
            'user_type' => 'admin',
            'role_id' => $role->id,
            'must_change_password' => false,
        ]);
        $sector = Sector::create([
            'name' => 'Execution Dashboard Test Sector '.Str::upper($suffix),
            'currency' => 'USD',
        ]);
        $program = Program::create([
            'sector_id' => $sector->id,
            'name' => 'Execution Dashboard Test Program '.Str::upper($suffix),
            'currency' => 'USD',
            'start_year' => 2025,
            'end_year' => 2025,
            'total_years' => 1,
            'total_budget' => 500_000,
        ]);
        $component = Project::create([
            'program_id' => $program->id,
            'name' => 'COMPONENT 91: Execution Dashboard Test',
            'description' => 'Selected component used to verify activity-level reporting.',
            'currency' => 'USD',
            'start_year' => 2025,
            'end_year' => 2025,
            'total_years' => 1,
            'total_budget' => 500_000,
        ]);
        ProjectAllocation::create([
            'project_id' => $component->id,
            'year' => 2025,
            'year_number' => 1,
            'actual_year' => 2025,
            'amount' => 500_000,
        ]);

        return [$admin, $sector, $program, $component];
    }

    private function subComponentFixture(Project $component): array
    {
        $first = Activity::create([
            'project_id' => $component->id,
            'name' => 'Sub-Component A: Research Platform',
            'description' => 'First selected-component activity.',
        ]);
        $second = Activity::create([
            'project_id' => $component->id,
            'name' => 'Sub-Component B: Policy Network',
            'description' => 'Second selected-component activity.',
        ]);
        $secondSubActivity = SubActivity::create([
            'activity_id' => $second->id,
            'name' => 'Policy network implementation',
        ]);

        ActivityAllocation::create([
            'activity_id' => $first->id,
            'year' => 2025,
            'amount' => 100_000,
        ]);
        ActivityAllocation::create([
            'activity_id' => $second->id,
            'year' => 2025,
            'amount' => 200_000,
        ]);

        return [$first, $second, $secondSubActivity];
    }

    private function financialFixture(
        Project $component,
        Activity $firstSubComponent,
        SubActivity $secondSubActivity
    ): void {
        foreach ([
            ['project', $component->id, 25_000],
            ['activity', $firstSubComponent->id, 50_000],
            ['sub_activity', $secondSubActivity->id, 75_000],
        ] as [$level, $allocationId, $amount]) {
            BudgetCommitment::create([
                'allocation_level' => $level,
                'allocation_id' => $allocationId,
                'commitment_amount' => $amount,
                'commitment_year' => 2025,
                'status' => BudgetCommitment::STATUS_APPROVED,
                'description' => 'Execution dashboard sub-component test commitment.',
            ]);
        }

        ProcurementDisbursement::create([
            'sub_activity_id' => $secondSubActivity->id,
            'reference_no' => 'EXEC-SMOKE-'.Str::upper(Str::random(8)),
            'amount' => 30_000,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now()->setDate(2025, 6, 30),
            'notes' => 'Direct sub-activity payment for execution dashboard smoke coverage.',
        ]);
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

(new ExecutionDashboardSubcomponentsSmoke($app))->run();
