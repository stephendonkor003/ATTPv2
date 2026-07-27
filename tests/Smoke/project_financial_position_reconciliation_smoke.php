<?php

use App\Http\Controllers\BudgetReportController;
use App\Http\Controllers\MasterDashboard;
use App\Models\BudgetCommitment;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPurchaseOrder;
use App\Models\Program;
use App\Models\ProgramFunding;
use App\Models\Project;
use App\Models\ProjectAllocation;
use App\Models\Role;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

class ProjectFinancialPositionReconciliationSmoke
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
            [$admin, $program] = $this->fixture();
            $this->actingAs($admin);

            $query = ['program_id' => $program->id];
            $dashboard = $this->executionDashboardPayload($query, $admin);
            $report = $this->financialPositionPayload($query, $admin);
            $totals = $report['position']['totals'];
            $controls = $report['position']['controls'];

            $this->assertAmount($dashboard['totalAllocation'], $totals['budget'], 'Budget envelope');
            $this->assertAmount($dashboard['totalCommitment'], $totals['committed'], 'Commitments');
            $this->assertAmount($dashboard['totalDisbursements'], $totals['disbursed'], 'Paid disbursements');
            $this->assertAmount($dashboard['executionRate'], $totals['commitment_rate'], 'Commitment rate');
            $this->assertAmount($dashboard['disbursementRate'], $totals['disbursement_rate'], 'Disbursement rate');

            $this->assertAmount(60_000, $totals['invoiced'], 'Recorded invoices');
            $this->assertAmount(10_000, $controls['invoice_gap'], 'Invoice coverage gap');
            $this->assertAmount(30_000, $controls['unlinked_disbursement_amount'], 'Payments without a linked invoice');
            $this->assertTrue($controls['unlinked_disbursement_count'] === 1, 'Expected one unlinked paid record.');
            $this->assertTrue($controls['invoice_exception_count'] === 1, 'Expected one purchase-order invoice exception.');
            $this->assertTrue($report['position']['dashboard_aligned'] === true, 'The life-to-date report should be dashboard aligned.');

            $webResponse = $this->get(route('budget.reports.project-financial-position', $query));
            $webResponse
                ->assertOk()
                ->assertSee('Executive dashboard reconciliation passed')
                ->assertSee('Accounting Integrity')
                ->assertSee('Invoice linkage exceptions')
                ->assertSee('500,000.00')
                ->assertSee('250,000.00')
                ->assertSee('70,000.00')
                ->assertSee('14.00%');

            $pdfResponse = $this->get(route('budget.reports.project-financial-position.export.pdf', $query));
            $this->assertTrue($pdfResponse->getStatusCode() === 200, 'The financial-position PDF did not generate.');
            $this->assertTrue(
                str_starts_with((string) $pdfResponse->headers->get('Content-Type'), 'application/pdf'),
                'The financial-position export was not returned as a PDF.'
            );
            $this->assertTrue(
                (int) $pdfResponse->headers->get('Content-Length') > 1_000,
                'The financial-position PDF was unexpectedly empty.'
            );

            echo "PROJECT_FINANCIAL_POSITION_RECONCILIATION_SMOKE_OK\n";
        } finally {
            DB::rollBack();
        }
    }

    private function fixture(): array
    {
        $suffix = Str::lower(Str::random(8));
        $role = Role::create([
            'name' => 'Financial Position Smoke '.Str::upper($suffix),
            'description' => 'Temporary project financial position test role.',
        ]);
        $admin = User::create([
            'name' => 'Financial Position Test Admin',
            'email' => 'financial-position-'.$suffix.'@example.test',
            'password' => Hash::make('Password123!'),
            'user_type' => 'admin',
            'role_id' => $role->id,
            'must_change_password' => false,
        ]);
        $sector = Sector::create([
            'name' => 'Financial Position Test Sector '.Str::upper($suffix),
            'currency' => 'USD',
        ]);
        $program = Program::create([
            'sector_id' => $sector->id,
            'name' => 'Financial Position Test Program '.Str::upper($suffix),
            'currency' => 'USD',
            'start_year' => 2025,
            'end_year' => 2025,
            'total_years' => 1,
            'total_budget' => 500_000,
        ]);
        $project = Project::create([
            'program_id' => $program->id,
            'name' => 'Financial Position Test Project '.Str::upper($suffix),
            'currency' => 'USD',
            'start_year' => 2025,
            'end_year' => 2025,
            'total_years' => 1,
            'total_budget' => 500_000,
        ]);
        ProjectAllocation::create([
            'project_id' => $project->id,
            'year' => 2025,
            'year_number' => 1,
            'actual_year' => 2025,
            'amount' => 500_000,
        ]);
        $funding = ProgramFunding::create([
            'program_id' => $program->id,
            'program_name' => $program->name,
            'funding_type' => 'grant',
            'approved_amount' => 500_000,
            'currency' => 'USD',
            'start_year' => 2025,
            'end_year' => 2025,
            'status' => 'approved',
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'created_by' => $admin->id,
        ]);

        $approvedCommitment = BudgetCommitment::create([
            'program_funding_id' => $funding->id,
            'allocation_level' => 'project',
            'allocation_id' => $project->id,
            'commitment_amount' => 200_000,
            'commitment_year' => 2025,
            'status' => BudgetCommitment::STATUS_APPROVED,
            'description' => 'Approved financial-position smoke commitment.',
            'created_by' => $admin->id,
        ]);
        $submittedCommitment = BudgetCommitment::create([
            'program_funding_id' => $funding->id,
            'allocation_level' => 'project',
            'allocation_id' => $project->id,
            'commitment_amount' => 50_000,
            'commitment_year' => 2025,
            'status' => BudgetCommitment::STATUS_SUBMITTED,
            'description' => 'Submitted commitment included by the executive dashboard.',
            'created_by' => $admin->id,
        ]);
        BudgetCommitment::create([
            'program_funding_id' => $funding->id,
            'allocation_level' => 'project',
            'allocation_id' => $project->id,
            'commitment_amount' => 10_000,
            'commitment_year' => 2025,
            'status' => BudgetCommitment::STATUS_DRAFT,
            'description' => 'Draft commitment excluded from both reports.',
            'created_by' => $admin->id,
        ]);

        $invoice = ProcurementInvoice::create([
            'invoice_month' => '2025-05-01',
            'reference_no' => 'FP-INV-'.Str::upper($suffix),
            'amount' => 60_000,
            'currency' => 'USD',
            'status' => 'approved',
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now()->setDate(2025, 5, 15),
        ]);
        $invoicedPurchaseOrder = ProcurementPurchaseOrder::create([
            'invoice_id' => $invoice->id,
            'budget_commitment_id' => $approvedCommitment->id,
            'reference_no' => 'FP-PO-INV-'.Str::upper($suffix),
            'amount' => 200_000,
            'currency' => 'USD',
            'status' => 'issued',
            'created_by' => $admin->id,
            'issued_at' => now()->setDate(2025, 4, 1),
        ]);
        $uninvoicedPurchaseOrder = ProcurementPurchaseOrder::create([
            'budget_commitment_id' => $submittedCommitment->id,
            'reference_no' => 'FP-PO-GAP-'.Str::upper($suffix),
            'amount' => 50_000,
            'currency' => 'USD',
            'status' => 'issued',
            'created_by' => $admin->id,
            'issued_at' => now()->setDate(2025, 4, 1),
        ]);

        ProcurementDisbursement::create([
            'purchase_order_id' => $invoicedPurchaseOrder->id,
            'reference_no' => 'FP-PAY-INV-'.Str::upper($suffix),
            'amount' => 40_000,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now()->setDate(2025, 6, 30),
            'created_by' => $admin->id,
        ]);
        ProcurementDisbursement::create([
            'purchase_order_id' => $uninvoicedPurchaseOrder->id,
            'reference_no' => 'FP-PAY-GAP-'.Str::upper($suffix),
            'amount' => 30_000,
            'currency' => 'USD',
            'status' => 'paid',
            'paid_at' => now()->setDate(2025, 7, 31),
            'created_by' => $admin->id,
        ]);
        ProcurementDisbursement::create([
            'purchase_order_id' => $uninvoicedPurchaseOrder->id,
            'reference_no' => 'FP-PAY-PENDING-'.Str::upper($suffix),
            'amount' => 5_000,
            'currency' => 'USD',
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        return [$admin, $program];
    }

    private function executionDashboardPayload(array $query, User $admin): array
    {
        $request = Request::create('/finance/execution/dashboard', 'GET', $query);
        $request->setUserResolver(fn () => $admin);
        $method = new ReflectionMethod(MasterDashboard::class, 'executionDashboardPayload');

        return $method->invoke($this->app->make(MasterDashboard::class), $request);
    }

    private function financialPositionPayload(array $query, User $admin): array
    {
        $request = Request::create('/budget/reports/project-financial-position', 'GET', $query);
        $request->setUserResolver(fn () => $admin);
        $method = new ReflectionMethod(BudgetReportController::class, 'buildProjectFinancialPositionReportData');

        return $method->invoke($this->app->make(BudgetReportController::class), $request);
    }

    private function assertAmount(float|int|string $expected, float|int|string $actual, string $label): void
    {
        if (abs((float) $expected - (float) $actual) > 0.01) {
            throw new RuntimeException(
                "{$label} did not reconcile. Expected {$expected}, received {$actual}."
            );
        }
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }
}

(new ProjectFinancialPositionReconciliationSmoke($app))->run();
