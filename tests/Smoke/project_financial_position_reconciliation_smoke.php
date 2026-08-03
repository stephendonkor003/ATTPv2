<?php

use App\Http\Controllers\BudgetReportController;
use App\Http\Controllers\MasterDashboard;
use App\Http\Controllers\Procurement\ProcurementDisbursementController;
use App\Http\Controllers\Procurement\ProcurementPurchaseOrderController;
use App\Models\Activity;
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
use App\Models\SubActivity;
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
            $this->assertTrue(
                $dashboard['executionChartData']['snapshot_hash'] === $report['position']['execution_dashboard_snapshot'],
                'The financial position should retain the exact financial execution snapshot.'
            );
            $this->assertTrue(
                $dashboard['executionBreakdownTotals'] === $report['position']['execution_dashboard_totals'],
                'The financial position should consume the financial execution totals without recalculating them.'
            );

            $this->assertAmount(70_000, $dashboard['totalDisbursements'], 'Dashboard recognized paid disbursements');
            $this->assertAmount(70_000, $dashboard['componentBreakdownRows']->first()['disbursement'], 'Dashboard component disbursements');
            $this->assertAmount(70_000, $report['position']['all_rows']->first()['disbursed'], 'Financial-position project disbursements');
            $this->assertAmount(
                0,
                $report['position']['all_rows']->first()['children']->first()['children']->first()['disbursed'],
                'Source-less sub-activity payment exclusion'
            );
            $this->assertAmount(60_000, $totals['invoiced'], 'Recognized invoices');
            $this->assertAmount(1, $report['position']['counts']['invoices'], 'Recognized invoice count');

            $disbursementSummaryMethod = new ReflectionMethod(
                ProcurementDisbursementController::class,
                'buildDisbursementSummary'
            );
            $disbursementSummary = $disbursementSummaryMethod->invoke(
                $this->app->make(ProcurementDisbursementController::class),
                ProcurementDisbursement::query()->where('created_by', $admin->id)
            );
            $this->assertAmount(70_000, $disbursementSummary['total_paid_amount'], 'Disbursement index recognized paid amount');
            $this->assertAmount(5_000, $disbursementSummary['pending_amount'], 'Disbursement index genuinely pending amount');

            foreach ([
                'sector' => ['sector_id' => $program->sector_id],
                'project' => ['project_id' => $program->projects()->value('id')],
            ] as $scope => $scopeQuery) {
                $scopedDashboard = $this->executionDashboardPayload($scopeQuery, $admin);
                $this->assertAmount(
                    70_000,
                    $scopedDashboard['totalDisbursements'],
                    ucfirst($scope).' dashboard recognized paid disbursements'
                );
            }

            $this->assertAmount(500_000, $totals['approved_funding'], 'Approved funding');
            $this->assertAmount(500_000, $totals['scheduled_allocation'], 'Scheduled allocation');
            $this->assertAmount(250_000, $totals['purchase_orders'], 'Purchase orders');
            $this->assertAmount(250_000, $totals['funding_utilization_gap'], 'Approved funding less commitments');
            $this->assertAmount(0, $totals['unprocessed_purchase_requests'], 'Unprocessed purchase requests');
            $this->assertAmount(180_000, $totals['unpaid_commitments'], 'Purchase orders less disbursements');
            $this->assertAmount(180_000, $totals['commitment_pipeline_balance'], 'Unpaid commitments plus unprocessed purchase requests');
            $this->assertAmount(0, $controls['commitment_processing_rate'], 'Unprocessed purchase requests ratio');
            $this->assertAmount(100, $controls['commitment_realization_rate'], 'PO coverage of commitments');
            $this->assertAmount(72, $controls['disbursement_backlog_rate'], 'Unpaid commitments ratio');
            $this->assertAmount(28, $controls['disbursement_efficiency_rate'], 'PO-to-disbursement conversion rate');
            $this->assertAmount(36, $controls['funding_utilization_integrity_gap_rate'], 'Idle committed funds ratio');
            $this->assertAmount(0, $controls['procurement_pipeline_utilization_gap'], 'Commitment structural gap');
            $this->assertTrue($report['position']['dashboard_aligned'] === true, 'The life-to-date report should be dashboard aligned.');

            $selectedProject = $program->projects()->firstOrFail();
            $projectQuery = [
                'program_id' => $program->id,
                'project_id' => $selectedProject->id,
            ];
            $projectReport = $this->financialPositionPayload($projectQuery, $admin);
            $projectDashboard = $this->executionDashboardPayload($projectQuery, $admin);
            $this->assertAmount(
                $selectedProject->total_budget,
                $projectReport['position']['totals']['approved_funding'],
                'Project approved funding'
            );
            $this->assertAmount(
                70_000,
                $projectReport['position']['totals']['funding_utilization_gap'],
                'Project approved funding less commitments'
            );
            $this->assertTrue(
                $projectReport['position']['dashboard_aligned'] === false,
                'A project-filtered report should use the project drill-down calculations.'
            );
            $this->assertTrue(
                ($projectReport['executionDashboard']['scopeType'] ?? null) === 'project',
                'The integrated execution graphs did not switch to the selected component scope.'
            );
            $this->assertTrue(
                ($projectReport['executionDashboard']['executionFilters']['project'] ?? null) === $selectedProject->name,
                'The integrated execution graph scope does not identify the selected component.'
            );
            $this->assertTrue(
                ($projectReport['executionDashboard']['executionChartData']['snapshot_hash'] ?? null)
                    === ($projectDashboard['executionChartData']['snapshot_hash'] ?? null),
                'The integrated graphs do not use the same audited snapshot as the component-scoped execution dataset.'
            );
            $this->assertTrue(
                ($projectReport['executionDashboard']['executionChartData']['snapshot_hash'] ?? null)
                    !== ($report['executionDashboard']['executionChartData']['snapshot_hash'] ?? null),
                'The component filter left the integrated graphs on the programme-wide snapshot.'
            );
            $this->assertTrue(
                count(array_filter($projectReport['executionDashboard']['executionChartImages'] ?? [])) === 7,
                'The component-scoped financial position did not render all seven execution graphs.'
            );
            $this->assertTrue(
                ($projectReport['executionDashboard']['componentBreakdownLevel'] ?? null) === 'sub_component'
                    && $projectReport['executionDashboard']['componentBreakdownRows']->isNotEmpty(),
                'The selected component graphs did not expose their sub-component breakdown.'
            );

            $reserveProject = $program->projects()
                ->where('id', '!=', $selectedProject->id)
                ->firstOrFail();
            $reserveReport = $this->financialPositionPayload([
                'program_id' => $program->id,
                'project_id' => $reserveProject->id,
            ], $admin);
            $this->assertAmount(
                $reserveProject->total_budget,
                $reserveReport['position']['totals']['approved_funding'],
                'Reserve project approved funding'
            );

            $webResponse = $this->get(route('budget.reports.project-financial-position', $query));
            $webResponse
                ->assertOk()
                ->assertSee('Financial execution dataset active')
                ->assertSee('loaded directly from the reconciled financial execution dataset')
                ->assertSee('Accounting Integrity')
                ->assertSee('Funding Utilization Gap')
                ->assertSee('Purchase Request Total')
                ->assertSee('Commitment Processing')
                ->assertSee('Disbursement Efficiency')
                ->assertSee('Funding Utilization Integrity Gap')
                ->assertSee('Procurement Pipeline Utilization')
                ->assertDontSee('Recorded Invoices')
                ->assertDontSee('Invoice linkage exceptions')
                ->assertSee('500,000.00')
                ->assertSee('250,000.00')
                ->assertSee('70,000.00')
                ->assertSee('180,000.00')
                ->assertSee('14.00%');

            $legacyWebResponse = $this->get(route('finance.execution.dashboard', [
                'program_id' => $program->id,
            ]));
            $this->assertTrue(
                $legacyWebResponse->isRedirect()
                    && str_contains((string) $legacyWebResponse->headers->get('Location'), '/budget/reports/project-financial-position'),
                'The legacy financial execution URL should redirect to Project Financial Position.'
            );

            $legacyPdfResponse = $this->get(route('finance.execution.dashboard.export.pdf', [
                'program_id' => $program->id,
            ]));
            $this->assertTrue(
                $legacyPdfResponse->isRedirect()
                    && str_contains((string) $legacyPdfResponse->headers->get('Location'), '/budget/reports/project-financial-position/export/pdf'),
                'The legacy financial execution PDF URL should redirect to the combined Project Financial Position PDF.'
            );

            $projectWebResponse = $this->get(route('budget.reports.project-financial-position', $projectQuery));
            $projectWebResponse
                ->assertOk()
                ->assertSee('Selected project budget allocation')
                ->assertSee('Sub-Component Execution Breakdown')
                ->assertSee('320,000.00');

            $webHtml = (string) $webResponse->getContent();
            $pdfHtml = view('budgetreport.project-financial-position-pdf', $report)->render();
            $projectPdfHtml = view('budgetreport.project-financial-position-pdf', $projectReport)->render();
            $this->assertTrue(
                ! str_contains($pdfHtml, 'Execution Dashboard')
                    && ! str_contains($pdfHtml, '<h2>Financial Execution Analytics</h2>'),
                'The combined PDF should not contain a separate execution dashboard heading.'
            );
            $this->assertTrue(
                str_contains($projectPdfHtml, 'Selected project budget allocation')
                    && str_contains($projectPdfHtml, 'Sub-Component Execution Breakdown')
                    && str_contains($projectPdfHtml, '320,000.00'),
                'The project-filtered PDF should display the selected project budget and component-scoped execution breakdown.'
            );
            $sharedReportInformation = [
                'Report Context',
                'Financial Control Summary',
                'Approved Funding',
                'Scheduled Allocation',
                'Funding Utilization Gap',
                'Purchase Request Total',
                'Unpaid Commitments',
                'Financial execution dataset active',
                'Executive Controls',
                'Commitment utilization of Approved Funding',
                'Accounting Integrity',
                'Commitment Processing',
                'Unprocessed purchase requests ÷ committed',
                'Disbursement Efficiency',
                'Funding Utilization Integrity Gap',
                'Idle Committed Funds Ratio',
                '(Unpaid commitments + purchase requests) ÷ approved funding',
                'Procurement Pipeline Utilization',
                'Commitment Structural Gap',
                'Committed − (purchase orders + purchase requests)',
                'Scheduled Allocation vs Commitments vs Disbursements',
                'Program Control Split',
                'Full Program Balance Sheet',
                'Scheduled total',
                'Integrated financial execution analytics',
                'Financial Execution Analytics',
                'Execution Mix',
                'Rate Movement',
                'Cumulative Momentum',
                'Cumulative Financial Profile',
                'Variance Control',
                'Execution Quality Radar',
                'Exposure Concentration',
                'Year-by-Year Execution',
                'Component Execution Breakdown',
                'Execution Insights',
                'Generated in your local time',
                'Official financial control report',
                '500,000.00',
                '250,000.00',
                '70,000.00',
                '180,000.00',
                '14.00%',
            ];

            foreach ($sharedReportInformation as $information) {
                $this->assertTrue(
                    str_contains($webHtml, $information),
                    "Web financial position is missing shared report information: {$information}"
                );
                $this->assertTrue(
                    str_contains($pdfHtml, $information),
                    "PDF financial position is missing shared report information: {$information}"
                );
            }

            \Carbon\Carbon::setTestNow('2026-07-29T12:34:56.000Z');
            try {
                $localTimeReport = $this->financialPositionPayload([
                    ...$query,
                    'report_timezone' => 'Africa/Nairobi',
                ], $admin);
            } finally {
                \Carbon\Carbon::setTestNow();
            }
            $this->assertTrue(
                $localTimeReport['reportTimezone'] === 'Africa/Nairobi',
                'The report did not retain the browser IANA timezone.'
            );
            $this->assertTrue(
                $localTimeReport['reportGeneratedAt']->format('Y-m-d H:i:s T') === '2026-07-29 15:34:56 EAT',
                'The report timestamp was not converted to the user timezone.'
            );
            $localTimeWebHtml = view('budgetreport.project-financial-position', $localTimeReport)->render();
            $localTimePdfHtml = view('budgetreport.project-financial-position-pdf', $localTimeReport)->render();

            foreach ([
                '29 Jul 2026, 15:34:56 EAT (Africa/Nairobi)',
                'Generated in your local time',
                'ATTP · Project Financial Position',
            ] as $localFooterInformation) {
                $this->assertTrue(
                    str_contains($localTimeWebHtml, $localFooterInformation),
                    "Web report is missing local timestamp/footer information: {$localFooterInformation}"
                );
                $this->assertTrue(
                    str_contains($localTimePdfHtml, $localFooterInformation),
                    "PDF report is missing local timestamp/footer information: {$localFooterInformation}"
                );
            }

            $this->assertTrue(
                str_contains($localTimeWebHtml, "searchParams.set('report_timezone', browserTimezone)"),
                'The PDF export link does not capture the current browser timezone.'
            );
            $this->assertTrue(
                str_contains($localTimePdfHtml, 'class="page-number"'),
                'The redesigned PDF footer is missing its page counter.'
            );

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

            $paidPurchaseOrder = ProcurementPurchaseOrder::query()
                ->whereHas('budgetCommitment.programFunding', fn ($query) => $query->where('program_id', $program->id))
                ->whereHas('disbursements', fn ($query) => $query->recognizedPayment())
                ->firstOrFail();
            $paidPurchaseOrderId = $paidPurchaseOrder->id;
            $deleteResponse = $this->app
                ->make(ProcurementPurchaseOrderController::class)
                ->destroy($paidPurchaseOrder);

            $this->assertTrue($deleteResponse->isRedirect(), 'A protected PO deletion should redirect with an error.');
            $this->assertTrue(
                ProcurementPurchaseOrder::whereKey($paidPurchaseOrderId)->exists(),
                'A purchase order with recorded payments was deleted.'
            );
            $this->assertTrue(
                ProcurementDisbursement::where('purchase_order_id', $paidPurchaseOrderId)
                    ->recognizedPayment()
                    ->exists(),
                'The protected PO payment lost its source link.'
            );
            $this->assertTrue(
                $deleteResponse->getSession()?->get('errors')?->has('purchase_order') === true,
                'The protected PO deletion did not explain how to reverse its payments.'
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
            'total_budget' => 320_000,
        ]);
        $activity = Activity::create([
            'project_id' => $project->id,
            'name' => 'Financial Position Test Activity '.Str::upper($suffix),
        ]);
        $subActivity = SubActivity::create([
            'activity_id' => $activity->id,
            'name' => 'Financial Position Test Direct Payment '.Str::upper($suffix),
        ]);
        ProjectAllocation::create([
            'project_id' => $project->id,
            'year' => 2025,
            'year_number' => 1,
            'actual_year' => 2025,
            'amount' => 320_000,
        ]);
        $reserveProject = Project::create([
            'program_id' => $program->id,
            'name' => 'Financial Position Test Reserve '.Str::upper($suffix),
            'currency' => 'USD',
            'start_year' => 2025,
            'end_year' => 2025,
            'total_years' => 1,
            'total_budget' => 180_000,
        ]);
        ProjectAllocation::create([
            'project_id' => $reserveProject->id,
            'year' => 2025,
            'year_number' => 1,
            'actual_year' => 2025,
            'amount' => 180_000,
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
        ProcurementInvoice::create([
            'sub_activity_id' => $subActivity->id,
            'invoice_month' => '2025-08-01',
            'reference_no' => 'FP-INV-ORPHAN-'.Str::upper($suffix),
            'amount' => 109_400,
            'currency' => 'USD',
            'status' => 'paid',
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now()->setDate(2025, 8, 31),
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
        ProcurementDisbursement::create([
            'sub_activity_id' => $subActivity->id,
            'reference_no' => 'FP-PAY-ORPHAN-'.Str::upper($suffix),
            'amount' => 109_400,
            'currency' => 'USD',
            'status' => 'completed',
            'paid_at' => now()->setDate(2025, 8, 31),
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
