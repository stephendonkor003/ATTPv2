<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Throwable;

use App\Models\Sector;
use App\Models\Program;
use App\Models\Project;
use App\Models\BudgetCommitment;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Services\ExecutionDashboardChartBuilder;
use App\Services\ExecutionInsightBuilder;

class MasterDashboard extends Controller
{
    use ScopesAssignedPortfolios;

    /**
     * ============================================================
     * EXECUTION DASHBOARD (MASTER)
     * ============================================================
     */
    public function executionDashboard(Request $request)
    {
        return view('finance.execution.dashboard', $this->executionDashboardPayload($request));
    }

    public function exportExecutionDashboardPdf(Request $request)
    {
        $downloadToken = $this->executionDashboardDownloadToken($request);
        $this->storeExecutionDashboardDownloadStatus($request, $downloadToken, [
            'status' => 'processing',
            'message' => 'The complete Execution Dashboard report is being generated.',
        ]);

        try {
            $data = $this->executionDashboardPayload($request);
            $expectedSnapshot = strtolower(trim((string) $request->query('dashboard_snapshot', '')));
            $currentSnapshot = (string) ($data['executionChartData']['snapshot_hash'] ?? '');
            if (! $this->executionDashboardSnapshotMatches($expectedSnapshot, $currentSnapshot)) {
                $message = 'The dashboard figures changed after this page was opened. Refresh the dashboard before downloading so the webpage and PDF figures remain identical.';
                $this->storeExecutionDashboardDownloadStatus($request, $downloadToken, [
                    'status' => 'failed',
                    'code' => 'dashboard_data_changed',
                    'message' => $message,
                ]);

                return response($message, 409, [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }

            $data['executionChartImages'] = app(ExecutionDashboardChartBuilder::class)
                ->buildFromDataset($data['executionChartData'], $data['currency']);
            $filename = 'execution-dashboard-' . now()->format('Ymd-His') . '.pdf';
            $output = Pdf::loadView('finance.execution.dashboard_pdf', $data)
                ->setPaper('a4', 'landscape')
                ->output();

            $this->storeExecutionDashboardDownloadStatus($request, $downloadToken, [
                'status' => 'ready',
                'message' => 'The report is ready and has been handed to your download manager.',
                'filename' => $filename,
                'bytes' => strlen($output),
                'snapshot_hash' => $data['executionChartData']['snapshot_hash'] ?? null,
            ]);

            return response($output, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($output),
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_ATTACHMENT,
                    $filename
                ),
                'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Content-Type-Options' => 'nosniff',
                'X-Execution-Dashboard-Snapshot' => (string) ($data['executionChartData']['snapshot_hash'] ?? ''),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $reference = $downloadToken ? substr($downloadToken, -8) : null;
            $message = 'The server could not generate the PDF. Please try again.';
            if ($reference) {
                $message .= ' Reference: ' . $reference . '.';
            }

            $this->storeExecutionDashboardDownloadStatus($request, $downloadToken, [
                'status' => 'failed',
                'message' => $message,
                'reference' => $reference,
            ]);

            return response($message, 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }
    }

    public function executionDashboardExportStatus(Request $request)
    {
        $downloadToken = $this->executionDashboardDownloadToken($request, true);
        try {
            $status = Cache::get(
                $this->executionDashboardDownloadStatusKey($request, $downloadToken),
                [
                    'status' => 'pending',
                    'message' => 'Waiting for the report generator to start.',
                ]
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'unknown',
                'message' => 'The report status service is temporarily unavailable. The download request remains active.',
            ], 503)->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
                'Retry-After' => '2',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->json($status)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function executionDashboardPayload(Request $request): array
    {
        /* ============================================================
         * 1. FILTER INPUTS
         * ============================================================ */
        $sectorId  = $request->get('sector_id');
        $programId = $request->get('program_id');
        $projectId = $request->get('project_id');
        $currentUser = $request->user();
        $hasPortfolioScope = $this->userHasAssignedPortfolioScope($currentUser);

        /* ============================================================
         * 2. FILTER DATA (FOR DROPDOWNS)
         * ============================================================ */
        $sectorQuery = Sector::query()->orderBy('name');
        if ($hasPortfolioScope) {
            $this->applyAssignedPortfolioScopeToSectors($sectorQuery, $currentUser);
        }
        $sectors = $sectorQuery->get();

        $programQuery = Program::query()->orderBy('name');
        if ($sectorId) {
            $programQuery->where('sector_id', $sectorId);
        }
        if ($hasPortfolioScope) {
            $this->applyAssignedPortfolioScopeToPrograms($programQuery, $currentUser);
        }
        $programs = $programQuery->get();

        $projectQuery = Project::query()->orderBy('name');
        if ($programId) {
            $projectQuery->where('program_id', $programId);
        }
        if ($hasPortfolioScope) {
            $this->applyAssignedPortfolioScopeToProjects($projectQuery, $currentUser);
        }
        $projects = $programId ? $projectQuery->get() : collect();
        $executionFilters = [
            'sector' => $sectorId
                ? (string) ($sectors->first(
                    fn (Sector $sector) => (string) $sector->id === (string) $sectorId
                )?->name ?? 'Selected sector')
                : 'All Sectors',
            'program' => $programId
                ? (string) ($programs->first(
                    fn (Program $program) => (string) $program->id === (string) $programId
                )?->name ?? 'Selected program')
                : 'All Programs',
            'project' => $projectId
                ? (string) ($projects->first(
                    fn (Project $project) => (string) $project->id === (string) $projectId
                )?->name ?? 'Selected project')
                : 'All Projects',
        ];

        /* ============================================================
         * 3. RESOLVE EXECUTION SCOPE
         * ============================================================ */
        if ($projectId) {
            $scopeType = 'project';
            $scope = Project::with('program')->findOrFail($projectId);
            $this->assertExecutionProjectScope($scope, $currentUser);
            $years = $scope->years();

        } elseif ($programId) {
            $scopeType = 'program';
            $scope = Program::findOrFail($programId);
            $this->assertExecutionProgramScope($scope, $currentUser);
            $years = $scope->years();

        } elseif ($sectorId) {
            $scopeType = 'sector';
            $scope = Sector::findOrFail($sectorId);
            $this->assertExecutionSectorScope($scope, $currentUser);

            $rangeQuery = Program::where('sector_id', $sectorId);
            if ($hasPortfolioScope) {
                $this->applyAssignedPortfolioScopeToPrograms($rangeQuery, $currentUser);
            }
            $range = $rangeQuery
                ->select(
                    DB::raw('MIN(start_year) as start'),
                    DB::raw('MAX(end_year) as end')
                )->first();

            $years = $this->normaliseExecutionYears($range?->start, $range?->end);

        } else {
            $scopeType = 'global';
            $scope = null;

            $rangeQuery = Program::query();
            if ($hasPortfolioScope) {
                $this->applyAssignedPortfolioScopeToPrograms($rangeQuery, $currentUser);
            }
            $range = $rangeQuery->select(
                DB::raw('MIN(start_year) as start'),
                DB::raw('MAX(end_year) as end')
            )->first();

            $years = $this->normaliseExecutionYears($range?->start, $range?->end);
        }

        /* ============================================================
         * 4. YEARLY ALLOCATIONS (ESTIMATED)
         * ============================================================ */
        $allocationByYear = [];

        foreach ($years as $year) {
            $allocationByYear[$year] = $this->resolveAllocation(
                $scopeType,
                $scope,
                $year
            );
        }

        /* ============================================================
         * 5. YEARLY COMMITMENTS (ACTUAL)
         * ============================================================ */
        $commitmentQuery = BudgetCommitment::whereIn(
                'status',
                [
                    BudgetCommitment::STATUS_SUBMITTED,
                    BudgetCommitment::STATUS_APPROVED
                ]
            );
        if ($hasPortfolioScope) {
            $this->applyAssignedPortfolioScopeToCommitments($commitmentQuery, $currentUser);
        }

        $commitmentByYear = $commitmentQuery
            ->when($scopeType === 'program', function ($q) use ($scope) {
                $q->where(function ($scopeQuery) use ($scope) {
                    $scopeQuery
                        ->whereHas('programFunding', fn ($qq) => $qq->where('program_id', $scope->id))
                        ->orWhereHas('purchaseRequest.programFunding', fn ($qq) => $qq->where('program_id', $scope->id));
                });
            })
            ->when($scopeType === 'sector', function ($q) use ($scope) {
                $q->where(function ($scopeQuery) use ($scope) {
                    $scopeQuery
                        ->whereHas('programFunding.program', fn ($qq) => $qq->where('sector_id', $scope->id))
                        ->orWhereHas('purchaseRequest.programFunding.program', fn ($qq) => $qq->where('sector_id', $scope->id));
                });
            })
            ->when($scopeType === 'project', function ($q) use ($scope) {
                $projectScope = $this->projectExecutionScopeIds($scope);

                $q->where(function ($scopeQuery) use ($projectScope) {
                    $this->applyProjectExecutionAllocationScope($scopeQuery, $projectScope);

                    $scopeQuery->orWhereHas('purchaseRequest', function ($requestQuery) use ($projectScope) {
                        $this->applyProjectExecutionAllocationScope($requestQuery, $projectScope);
                    });
                });
            })
            ->select(
                'commitment_year',
                DB::raw('SUM(commitment_amount) as total')
            )
            ->groupBy('commitment_year')
            ->pluck('total', 'commitment_year')
            ->toArray();

        /* ============================================================
         * 6. YEARLY DISBURSEMENTS (PAID)
         * ============================================================ */
        $yearStart = collect($years)->min();
        $yearEnd = collect($years)->max();

        $disbursementQuery = ProcurementDisbursement::query()
            ->whereNotNull('paid_at')
            ->whereIn('status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES)
            ->when($yearStart && $yearEnd, function ($q) use ($yearStart, $yearEnd) {
                $q->whereBetween('paid_at', [
                    Carbon::create((int) $yearStart, 1, 1)->startOfDay(),
                    Carbon::create((int) $yearEnd, 12, 31)->endOfDay(),
                ]);
            })
            ->when($scopeType !== 'global', function ($q) use ($scopeType, $scope) {
                $q->whereHas('purchaseOrder', function ($poQuery) use ($scopeType, $scope) {
                    $this->applyExecutionScopeToPurchaseOrderQuery($poQuery, $scopeType, $scope);
                });
            });
        if ($hasPortfolioScope) {
            $this->applyAssignedPortfolioScopeToDisbursements($disbursementQuery, $currentUser);
        }

        $disbursementByYear = (clone $disbursementQuery)
            ->get(['paid_at', 'amount'])
            ->groupBy(fn ($disbursement) => Carbon::parse($disbursement->paid_at)->year)
            ->map(fn ($rows) => round((float) $rows->sum('amount'), 2))
            ->toArray();

        foreach ($years as $year) {
            $commitmentByYear[$year] = round((float) ($commitmentByYear[$year] ?? 0), 2);
            $disbursementByYear[$year] = round((float) ($disbursementByYear[$year] ?? 0), 2);
        }

        /* ============================================================
         * 7. KPI CALCULATIONS
         * ============================================================ */
        $scheduledAllocation = round((float) array_sum($allocationByYear), 2);
        $budgetEnvelope = $this->resolveBudgetEnvelope(
            $scopeType,
            $scope,
            $currentUser,
            $hasPortfolioScope,
            $scheduledAllocation
        );
        $totalAllocation = round($budgetEnvelope, 2);
        $unallocatedEnvelope = round($totalAllocation - $scheduledAllocation, 2);
        $totalCommitment = array_sum($commitmentByYear);
        $totalDisbursements = array_sum($disbursementByYear);

        $executionRate = $totalAllocation > 0
            ? round(($totalCommitment / $totalAllocation) * 100, 2)
            : 0;

        $disbursementRate = $totalAllocation > 0
            ? round(($totalDisbursements / $totalAllocation) * 100, 2)
            : 0;

        $variance = $totalAllocation - $totalCommitment;

        /* ============================================================
         * 8. LINE CHART DATA
         * ============================================================ */
        $lineChart = [
            'labels' => $years,
            'allocation' => array_values($allocationByYear),
            'commitment' => array_map(
                fn ($y) => $commitmentByYear[$y] ?? 0,
                $years
            ),
            'disbursement' => array_map(
                fn ($y) => $disbursementByYear[$y] ?? 0,
                $years
            ),
        ];

        /* ============================================================
         * 9. HEAT MAP DATA
         * ============================================================ */
        $heatmap = collect($years)->map(function ($year) use (
            $allocationByYear,
            $commitmentByYear,
            $disbursementByYear
        ) {
            $alloc = $allocationByYear[$year] ?? 0;
            $commit = $commitmentByYear[$year] ?? 0;
            $disbursed = $disbursementByYear[$year] ?? 0;

            return [
                'year' => $year,
                'allocation' => $alloc,
                'commitment' => $commit,
                'disbursement' => $disbursed,
                'execution_rate' => $alloc > 0
                    ? round(($commit / $alloc) * 100, 1)
                    : 0,
                'disbursement_rate' => $alloc > 0
                    ? round(($disbursed / $alloc) * 100, 1)
                    : 0,
            ];
        });

        $executionBreakdownRows = $heatmap->map(function (array $row) {
            return [
                'year' => $row['year'],
                'allocation' => round((float) ($row['allocation'] ?? 0), 2),
                'commitment' => round((float) ($row['commitment'] ?? 0), 2),
                'disbursement' => round((float) ($row['disbursement'] ?? 0), 2),
                'remaining' => round((float) ($row['allocation'] ?? 0) - (float) ($row['commitment'] ?? 0), 2),
                'execution_rate' => max(0, round((float) ($row['execution_rate'] ?? 0), 1)),
                'disbursement_rate' => max(0, round((float) ($row['disbursement_rate'] ?? 0), 1)),
            ];
        })->values();

        $executionBreakdownTotals = [
            'allocation' => round($totalAllocation, 2),
            'commitment' => round($totalCommitment, 2),
            'disbursement' => round($totalDisbursements, 2),
            'remaining' => round($totalAllocation - $totalCommitment, 2),
            'execution_rate' => max(0, round($executionRate, 1)),
            'disbursement_rate' => max(0, round($disbursementRate, 1)),
        ];

        $componentBreakdownRows = $this->componentExecutionBreakdown(
            $scopeType,
            $scope,
            $currentUser,
            $hasPortfolioScope,
            $yearStart,
            $yearEnd,
            $totalAllocation
        );

        $peakCommitmentRow = $executionBreakdownRows
            ->sortByDesc('commitment')
            ->first() ?? [];
        $latestExecutionRow = $executionBreakdownRows->last() ?? [];
        $unpaidCommitments = round(max($totalCommitment - $totalDisbursements, 0), 2);
        $overCommitment = round(max($totalCommitment - $totalAllocation, 0), 2);
        $currency = $this->resolveExecutionCurrency($scopeType, $scope);

        $executionSummary = [
            'currency' => $currency,
            'budget_envelope' => round($totalAllocation, 2),
            'scheduled_allocation' => $scheduledAllocation,
            'unallocated_envelope' => $unallocatedEnvelope,
            'committed' => round($totalCommitment, 2),
            'disbursed' => round($totalDisbursements, 2),
            'remaining_allocation' => $executionBreakdownTotals['remaining'],
            'unpaid_commitments' => $unpaidCommitments,
            'over_commitment' => $overCommitment,
            'active_years' => count($years),
            'latest_year' => $latestExecutionRow['year'] ?? null,
            'latest_execution_rate' => $latestExecutionRow['execution_rate'] ?? 0,
            'peak_commitment_year' => $peakCommitmentRow['year'] ?? null,
            'peak_commitment' => $peakCommitmentRow['commitment'] ?? 0,
        ];

        /* ============================================================
         * 10. RADAR METRICS
         * ============================================================ */
        $totalYears = count($years);

        $executedYears = collect($years)->filter(function ($y) use ($commitmentByYear) {
            return ($commitmentByYear[$y] ?? 0) > 0;
        })->count();

        $budgetUtilization = $executionRate;
        $timeliness = ($executedYears / max(1, $totalYears)) * 100;

        $consistency = 100 - (
            collect($years)->map(function ($y) use ($allocationByYear, $commitmentByYear) {
                return abs(
                    ($allocationByYear[$y] ?? 0) -
                    ($commitmentByYear[$y] ?? 0)
                );
            })->avg() / max(1, $totalAllocation) * 100
        );

        $riskYears = collect($years)->filter(function ($y) use ($allocationByYear, $commitmentByYear) {
            return ($commitmentByYear[$y] ?? 0) > ($allocationByYear[$y] ?? 0);
        })->count();

        $riskExposure = ($riskYears / max(1, $totalYears)) * 100;

        $radarMetrics = [
            'budget_utilization' => min(100, max(0, round($budgetUtilization, 1))),
            'timeliness' => min(100, max(0, round($timeliness, 1))),
            'consistency' => min(100, max(0, round($consistency, 1))),
            'coverage' => min(100, max(0, round($timeliness, 1))),
            'risk_exposure' => min(100, max(0, round(100 - $riskExposure, 1))),
        ];
        $executionChartData = app(ExecutionDashboardChartBuilder::class)->dataset(
            $executionBreakdownRows,
            $executionBreakdownTotals,
            $radarMetrics
        );

        /* ============================================================
         * 11. AI PAYLOAD & INSIGHTS
         * ============================================================ */
        $aiPayload = [
            'scope' => $scopeType,
            'total_allocation' => $totalAllocation,
            'total_commitment' => $totalCommitment,
            'total_disbursements' => $totalDisbursements,
            'execution_rate' => $executionRate,
            'disbursement_rate' => $disbursementRate,
            'variance' => $variance,
            'yearly' => $heatmap->values()->toArray(),
        ];

        $aiInsights = ExecutionInsightBuilder::build($aiPayload);

        /* ============================================================
         * 12. RETURN VIEW
         * ============================================================ */
        return compact(
            'sectors',
            'programs',
            'projects',
            'scopeType',
            'scope',
            'years',
            'allocationByYear',
            'commitmentByYear',
            'disbursementByYear',
            'totalAllocation',
            'totalCommitment',
            'totalDisbursements',
            'executionRate',
            'disbursementRate',
            'variance',
            'lineChart',
            'heatmap',
            'executionBreakdownRows',
            'executionBreakdownTotals',
            'componentBreakdownRows',
            'executionSummary',
            'executionFilters',
            'executionChartData',
            'currency',
            'radarMetrics',
            'aiInsights'
        );
    }

    private function executionDashboardDownloadToken(Request $request, bool $required = false): ?string
    {
        $token = trim((string) $request->query('download_token', ''));
        if ($token === '' && ! $required) {
            return null;
        }

        abort_unless(
            preg_match('/\A[A-Za-z0-9_-]{20,100}\z/', $token) === 1,
            422,
            'The download tracking token is invalid.'
        );

        return $token;
    }

    private function executionDashboardSnapshotMatches(
        string $expectedSnapshot,
        string $currentSnapshot
    ): bool {
        if ($expectedSnapshot === '') {
            return true;
        }

        return preg_match('/\A[a-f0-9]{64}\z/', $expectedSnapshot) === 1
            && preg_match('/\A[a-f0-9]{64}\z/', $currentSnapshot) === 1
            && hash_equals($currentSnapshot, $expectedSnapshot);
    }

    private function executionDashboardDownloadStatusKey(Request $request, string $downloadToken): string
    {
        $userId = (string) ($request->user()?->id ?? 'guest');

        return 'execution-dashboard-download:' . hash('sha256', $userId . '|' . $downloadToken);
    }

    private function storeExecutionDashboardDownloadStatus(
        Request $request,
        ?string $downloadToken,
        array $status
    ): void {
        if (! $downloadToken) {
            return;
        }

        try {
            Cache::put(
                $this->executionDashboardDownloadStatusKey($request, $downloadToken),
                array_merge($status, ['updated_at' => now()->toIso8601String()]),
                now()->addMinutes(10)
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function componentExecutionBreakdown(
        string $scopeType,
        $scope,
        $currentUser,
        bool $hasPortfolioScope,
        ?int $yearStart,
        ?int $yearEnd,
        float $budgetEnvelope
    ) {
        $componentQuery = Project::query()
            ->select(['id', 'program_id', 'name', 'total_budget']);

        if ($scopeType === 'project') {
            $componentQuery->where('id', $scope->id);
        } elseif ($scopeType === 'program') {
            $componentQuery->where('program_id', $scope->id);
        } elseif ($scopeType === 'sector') {
            $componentQuery->whereHas('program', fn ($q) => $q->where('sector_id', $scope->id));
        }

        if ($hasPortfolioScope) {
            $this->applyAssignedPortfolioScopeToProjects($componentQuery, $currentUser);
        }

        $components = $componentQuery
            ->get()
            ->sortBy(fn ($component) => $this->executionComponentSortKey((string) $component->name))
            ->values();

        $componentIds = $components
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (empty($componentIds)) {
            return collect();
        }

        $allocationByComponent = DB::table('myb_project_allocations')
            ->whereIn('project_id', $componentIds)
            ->when($yearStart && $yearEnd, fn ($q) => $q->whereBetween('year', [$yearStart, $yearEnd]))
            ->select('project_id', DB::raw('SUM(amount) as total'))
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        $commitmentByComponent = $this->commitmentsByExecutionComponent($componentIds);
        $disbursementByComponent = $this->disbursementsByExecutionComponent($componentIds, $yearStart, $yearEnd);

        $rows = $components->map(function (Project $component) use (
            $allocationByComponent,
            $commitmentByComponent,
            $disbursementByComponent
        ) {
            $componentId = (string) $component->id;
            $allocation = round((float) $allocationByComponent->get($componentId, 0), 2);
            $commitment = round((float) $commitmentByComponent->get($componentId, 0), 2);
            $disbursement = round((float) $disbursementByComponent->get($componentId, 0), 2);
            [$label, $description] = $this->executionComponentLabel((string) $component->name);

            return [
                'component_id' => $componentId,
                'label' => $label,
                'description' => $description,
                'name' => (string) $component->name,
                'allocation' => $allocation,
                'commitment' => $commitment,
                'disbursement' => $disbursement,
                'remaining' => round($allocation - $commitment, 2),
                'execution_rate' => $allocation > 0
                    ? max(0, round(($commitment / $allocation) * 100, 1))
                    : 0,
                'disbursement_rate' => $allocation > 0
                    ? max(0, round(($disbursement / $allocation) * 100, 1))
                    : 0,
            ];
        })->values();

        $unallocatedEnvelope = round($budgetEnvelope - (float) $rows->sum('allocation'), 2);
        if (abs($unallocatedEnvelope) > 0.01) {
            $rows->push([
                'component_id' => null,
                'label' => $unallocatedEnvelope > 0
                    ? 'Unallocated Programme Balance'
                    : 'Envelope Reconciliation',
                'description' => $unallocatedEnvelope > 0
                    ? 'Approved budget not yet distributed across component yearly allocations.'
                    : 'Component yearly allocations exceed the approved budget envelope.',
                'name' => 'Programme envelope reconciliation',
                'allocation' => $unallocatedEnvelope,
                'commitment' => 0.0,
                'disbursement' => 0.0,
                'remaining' => $unallocatedEnvelope,
                'execution_rate' => 0.0,
                'disbursement_rate' => 0.0,
            ]);
        }

        return $rows->values();
    }

    private function commitmentsByExecutionComponent(array $componentIds)
    {
        $projectExpression = "
            CASE
                WHEN c.allocation_level = 'project' THEN c.allocation_id
                WHEN c.allocation_level = 'activity' THEN c_activity.project_id
                WHEN c.allocation_level = 'sub_activity' THEN c_sub_activity_project.project_id
                WHEN pr.allocation_level = 'project' THEN pr.allocation_id
                WHEN pr.allocation_level = 'activity' THEN pr_activity.project_id
                WHEN pr.allocation_level = 'sub_activity' THEN pr_sub_activity_project.project_id
                ELSE NULL
            END
        ";

        return DB::table('myb_budget_commitments as c')
            ->leftJoin('myb_purchase_requests as pr', 'pr.id', '=', 'c.purchase_request_id')
            ->leftJoin('myb_activities as c_activity', function ($join) {
                $join->on('c_activity.id', '=', 'c.allocation_id')
                    ->where('c.allocation_level', '=', 'activity');
            })
            ->leftJoin('myb_sub_activities as c_sub_activity', function ($join) {
                $join->on('c_sub_activity.id', '=', 'c.allocation_id')
                    ->where('c.allocation_level', '=', 'sub_activity');
            })
            ->leftJoin('myb_activities as c_sub_activity_project', 'c_sub_activity_project.id', '=', 'c_sub_activity.activity_id')
            ->leftJoin('myb_activities as pr_activity', function ($join) {
                $join->on('pr_activity.id', '=', 'pr.allocation_id')
                    ->where('pr.allocation_level', '=', 'activity');
            })
            ->leftJoin('myb_sub_activities as pr_sub_activity', function ($join) {
                $join->on('pr_sub_activity.id', '=', 'pr.allocation_id')
                    ->where('pr.allocation_level', '=', 'sub_activity');
            })
            ->leftJoin('myb_activities as pr_sub_activity_project', 'pr_sub_activity_project.id', '=', 'pr_sub_activity.activity_id')
            ->whereIn('c.status', [
                BudgetCommitment::STATUS_SUBMITTED,
                BudgetCommitment::STATUS_APPROVED,
            ])
            ->whereIn(DB::raw("({$projectExpression})"), $componentIds)
            ->selectRaw("{$projectExpression} as component_id")
            ->selectRaw('SUM(c.commitment_amount) as total')
            ->groupByRaw($projectExpression)
            ->pluck('total', 'component_id');
    }

    private function disbursementsByExecutionComponent(array $componentIds, ?int $yearStart, ?int $yearEnd)
    {
        $purchaseRequestProjectExpression = fn (string $alias, string $activityAlias, string $subActivityProjectAlias) => "
            CASE
                WHEN {$alias}.allocation_level = 'project' THEN {$alias}.allocation_id
                WHEN {$alias}.allocation_level = 'activity' THEN {$activityAlias}.project_id
                WHEN {$alias}.allocation_level = 'sub_activity' THEN {$subActivityProjectAlias}.project_id
                ELSE NULL
            END
        ";

        $commitmentProjectExpression = "
            CASE
                WHEN bc.allocation_level = 'project' THEN bc.allocation_id
                WHEN bc.allocation_level = 'activity' THEN bc_activity.project_id
                WHEN bc.allocation_level = 'sub_activity' THEN bc_sub_activity_project.project_id
                ELSE NULL
            END
        ";

        $prProjectExpression = $purchaseRequestProjectExpression('pr', 'pr_activity', 'pr_sub_activity_project');
        $bcPrProjectExpression = $purchaseRequestProjectExpression('bc_pr', 'bc_pr_activity', 'bc_pr_sub_activity_project');

        $projectExpression = "
            COALESCE(
                d_sub_activity_project.project_id,
                po_sub_activity_project.project_id,
                {$prProjectExpression},
                {$commitmentProjectExpression},
                {$bcPrProjectExpression}
            )
        ";

        return DB::table('procurement_disbursements as d')
            ->leftJoin('procurement_purchase_orders as po', 'po.id', '=', 'd.purchase_order_id')
            ->leftJoin('myb_purchase_requests as pr', 'pr.id', '=', 'po.purchase_request_id')
            ->leftJoin('myb_budget_commitments as bc', 'bc.id', '=', 'po.budget_commitment_id')
            ->leftJoin('myb_purchase_requests as bc_pr', 'bc_pr.id', '=', 'bc.purchase_request_id')
            ->leftJoin('myb_sub_activities as d_sub_activity', 'd_sub_activity.id', '=', 'd.sub_activity_id')
            ->leftJoin('myb_activities as d_sub_activity_project', 'd_sub_activity_project.id', '=', 'd_sub_activity.activity_id')
            ->leftJoin('myb_sub_activities as po_sub_activity', 'po_sub_activity.id', '=', 'po.sub_activity_id')
            ->leftJoin('myb_activities as po_sub_activity_project', 'po_sub_activity_project.id', '=', 'po_sub_activity.activity_id')
            ->leftJoin('myb_activities as pr_activity', function ($join) {
                $join->on('pr_activity.id', '=', 'pr.allocation_id')
                    ->where('pr.allocation_level', '=', 'activity');
            })
            ->leftJoin('myb_sub_activities as pr_sub_activity', function ($join) {
                $join->on('pr_sub_activity.id', '=', 'pr.allocation_id')
                    ->where('pr.allocation_level', '=', 'sub_activity');
            })
            ->leftJoin('myb_activities as pr_sub_activity_project', 'pr_sub_activity_project.id', '=', 'pr_sub_activity.activity_id')
            ->leftJoin('myb_activities as bc_activity', function ($join) {
                $join->on('bc_activity.id', '=', 'bc.allocation_id')
                    ->where('bc.allocation_level', '=', 'activity');
            })
            ->leftJoin('myb_sub_activities as bc_sub_activity', function ($join) {
                $join->on('bc_sub_activity.id', '=', 'bc.allocation_id')
                    ->where('bc.allocation_level', '=', 'sub_activity');
            })
            ->leftJoin('myb_activities as bc_sub_activity_project', 'bc_sub_activity_project.id', '=', 'bc_sub_activity.activity_id')
            ->leftJoin('myb_activities as bc_pr_activity', function ($join) {
                $join->on('bc_pr_activity.id', '=', 'bc_pr.allocation_id')
                    ->where('bc_pr.allocation_level', '=', 'activity');
            })
            ->leftJoin('myb_sub_activities as bc_pr_sub_activity', function ($join) {
                $join->on('bc_pr_sub_activity.id', '=', 'bc_pr.allocation_id')
                    ->where('bc_pr.allocation_level', '=', 'sub_activity');
            })
            ->leftJoin('myb_activities as bc_pr_sub_activity_project', 'bc_pr_sub_activity_project.id', '=', 'bc_pr_sub_activity.activity_id')
            ->whereNotNull('d.paid_at')
            ->whereIn('d.status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES)
            ->when($yearStart && $yearEnd, function ($q) use ($yearStart, $yearEnd) {
                $q->whereBetween('d.paid_at', [
                    Carbon::create((int) $yearStart, 1, 1)->startOfDay(),
                    Carbon::create((int) $yearEnd, 12, 31)->endOfDay(),
                ]);
            })
            ->whereIn(DB::raw("({$projectExpression})"), $componentIds)
            ->selectRaw("{$projectExpression} as component_id")
            ->selectRaw('SUM(d.amount) as total')
            ->groupByRaw($projectExpression)
            ->pluck('total', 'component_id');
    }

    private function executionComponentSortKey(string $name): string
    {
        if (preg_match('/component\s*#?\s*(\d+)/i', $name, $matches)) {
            return sprintf('%03d-%s', (int) $matches[1], mb_strtolower($name));
        }

        return '999-' . mb_strtolower($name);
    }

    private function executionComponentLabel(string $name): array
    {
        if (preg_match('/component\s*#?\s*(\d+)/i', $name, $matches)) {
            $description = trim((string) preg_replace('/^\s*component\s*#?\s*\d+\s*[:.\-]?\s*/i', '', $name));

            return ['Component ' . (int) $matches[1], $description];
        }

        return [$name, null];
    }

    /**
     * ============================================================
     * PURCHASE ORDER SCOPE FILTER FOR DISBURSEMENTS
     * ============================================================
     */
    protected function applyExecutionScopeToPurchaseOrderQuery($query, string $scopeType, $scope): void
    {
        if ($scopeType === 'program') {
            $query->where(function ($scopeQuery) use ($scope) {
                $scopeQuery
                    ->whereHas('purchaseRequest.programFunding', fn ($q) => $q->where('program_id', $scope->id))
                    ->orWhereHas('budgetCommitment.programFunding', fn ($q) => $q->where('program_id', $scope->id))
                    ->orWhereHas('budgetCommitment.purchaseRequest.programFunding', fn ($q) => $q->where('program_id', $scope->id));
            });

            return;
        }

        if ($scopeType === 'sector') {
            $query->where(function ($scopeQuery) use ($scope) {
                $scopeQuery
                    ->whereHas('purchaseRequest.programFunding.program', fn ($q) => $q->where('sector_id', $scope->id))
                    ->orWhereHas('budgetCommitment.programFunding.program', fn ($q) => $q->where('sector_id', $scope->id))
                    ->orWhereHas('budgetCommitment.purchaseRequest.programFunding.program', fn ($q) => $q->where('sector_id', $scope->id));
            });

            return;
        }

        if ($scopeType === 'project') {
            $projectScope = $this->projectExecutionScopeIds($scope);

            $query->where(function ($scopeQuery) use ($projectScope) {
                if (!empty($projectScope['sub_activity_ids'])) {
                    $scopeQuery->whereIn('sub_activity_id', $projectScope['sub_activity_ids']);
                } else {
                    $scopeQuery->whereRaw('1 = 0');
                }

                $scopeQuery
                    ->orWhereHas('purchaseRequest', function ($requestQuery) use ($projectScope) {
                        $this->applyProjectExecutionAllocationScope($requestQuery, $projectScope);
                    })
                    ->orWhereHas('budgetCommitment', function ($commitmentQuery) use ($projectScope) {
                        $this->applyProjectExecutionAllocationScope($commitmentQuery, $projectScope);
                    })
                    ->orWhereHas('budgetCommitment.purchaseRequest', function ($requestQuery) use ($projectScope) {
                        $this->applyProjectExecutionAllocationScope($requestQuery, $projectScope);
                    });
            });
        }
    }

    private function projectExecutionScopeIds(Project $project): array
    {
        $activityIds = DB::table('myb_activities')
            ->where('project_id', $project->id)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $subActivityIds = empty($activityIds)
            ? []
            : DB::table('myb_sub_activities')
                ->whereIn('activity_id', $activityIds)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->all();

        return [
            'project_id' => (string) $project->id,
            'activity_ids' => $activityIds,
            'sub_activity_ids' => $subActivityIds,
        ];
    }

    private function applyProjectExecutionAllocationScope($query, array $projectScope): void
    {
        $query->where(function ($scopeQuery) use ($projectScope) {
            $scopeQuery->where(function ($projectQuery) use ($projectScope) {
                $projectQuery->where('allocation_level', 'project')
                    ->where('allocation_id', $projectScope['project_id']);
            });

            if (!empty($projectScope['activity_ids'])) {
                $scopeQuery->orWhere(function ($activityQuery) use ($projectScope) {
                    $activityQuery->where('allocation_level', 'activity')
                        ->whereIn('allocation_id', $projectScope['activity_ids']);
                });
            }

            if (!empty($projectScope['sub_activity_ids'])) {
                $scopeQuery->orWhere(function ($subActivityQuery) use ($projectScope) {
                    $subActivityQuery->where('allocation_level', 'sub_activity')
                        ->whereIn('allocation_id', $projectScope['sub_activity_ids']);
                });
            }
        });
    }

    private function resolveExecutionCurrency(string $scopeType, $scope): string
    {
        if ($scopeType === 'project' && $scope?->currency) {
            return $scope->currency;
        }

        if ($scopeType === 'project' && $scope?->program?->currency) {
            return $scope->program->currency;
        }

        if ($scopeType === 'program' && $scope?->currency) {
            return $scope->currency;
        }

        if ($scopeType === 'sector' && $scope?->id) {
            $query = Program::where('sector_id', $scope->id)->whereNotNull('currency');
            if ($this->userHasAssignedPortfolioScope(request()->user())) {
                $this->applyAssignedPortfolioScopeToPrograms($query, request()->user());
            }

            return $query
                ->value('currency') ?: 'USD';
        }

        $query = Program::whereNotNull('currency');
        if ($this->userHasAssignedPortfolioScope(request()->user())) {
            $this->applyAssignedPortfolioScopeToPrograms($query, request()->user());
        }

        return $query->value('currency') ?: 'USD';
    }

    /**
     * Resolve the approved budget envelope independently from its yearly
     * distribution. Programme budgets are authoritative; project allocation
     * rows remain the source for yearly execution charts.
     */
    private function resolveBudgetEnvelope(
        string $scopeType,
        $scope,
        $currentUser,
        bool $hasPortfolioScope,
        float $scheduledAllocation
    ): float {
        if ($scopeType === 'project') {
            return $this->preferDeclaredEnvelope(
                (float) ($scope?->total_budget ?? 0),
                $scheduledAllocation
            );
        }

        if ($scopeType === 'program') {
            return $this->preferDeclaredEnvelope(
                (float) ($scope?->total_budget ?? 0),
                $scheduledAllocation
            );
        }

        $programQuery = Program::query()->select(['id', 'total_budget']);
        if ($scopeType === 'sector' && $scope?->id) {
            $programQuery->where('sector_id', $scope->id);
        }
        if ($hasPortfolioScope) {
            $this->applyAssignedPortfolioScopeToPrograms($programQuery, $currentUser);
        }

        $programs = $programQuery->get();
        if ($programs->isEmpty()) {
            return $scheduledAllocation;
        }

        $declaredEnvelope = (float) $programs
            ->filter(fn (Program $program) => (float) $program->total_budget > 0)
            ->sum('total_budget');
        $programIdsWithoutEnvelope = $programs
            ->filter(fn (Program $program) => (float) $program->total_budget <= 0)
            ->pluck('id');

        $fallbackEnvelope = $programIdsWithoutEnvelope->isEmpty()
            ? 0.0
            : (float) DB::table('myb_project_allocations as allocation')
                ->join('myb_projects as project', 'project.id', '=', 'allocation.project_id')
                ->whereIn('project.program_id', $programIdsWithoutEnvelope)
                ->sum('allocation.amount');

        return $this->preferDeclaredEnvelope(
            $declaredEnvelope + $fallbackEnvelope,
            $scheduledAllocation
        );
    }

    private function preferDeclaredEnvelope(float $declaredEnvelope, float $scheduledAllocation): float
    {
        return round($declaredEnvelope > 0 ? $declaredEnvelope : $scheduledAllocation, 2);
    }

    /**
     * ============================================================
     * ALLOCATION RESOLVER (ESTIMATED)
     * ============================================================
     */
    protected function resolveAllocation(string $scopeType, $scope, int $year): float
    {
        return match ($scopeType) {

            'project' =>
                DB::table('myb_project_allocations')
                    ->where('project_id', $scope->id)
                    ->where('year', $year)
                    ->sum('amount'),

            'program' =>
                DB::table('myb_project_allocations')
                    ->whereIn(
                        'project_id',
                        Project::where('program_id', $scope->id)->pluck('id')
                    )
                    ->where('year', $year)
                    ->sum('amount'),

            'sector' =>
                DB::table('myb_project_allocations')
                    ->whereIn(
                        'project_id',
                        Project::whereIn(
                            'program_id',
                            Program::where('sector_id', $scope->id)->pluck('id')
                        )->pluck('id')
                    )
                    ->where('year', $year)
                    ->sum('amount'),

            default =>
                $this->resolveScopedGlobalAllocation($year),
        };
    }

    private function resolveScopedGlobalAllocation(int $year): float
    {
        $query = DB::table('myb_project_allocations')->where('year', $year);

        if ($this->userHasAssignedPortfolioScope(request()->user())) {
            $projectIds = $this->assignedProjectIds(request()->user());

            if (empty($projectIds)) {
                return 0.0;
            }

            $query->whereIn('project_id', $projectIds);
        }

        return (float) $query->sum('amount');
    }

    private function normaliseExecutionYears($start, $end): array
    {
        $start = (int) ($start ?: now()->year);
        $end = (int) ($end ?: $start);

        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }

        return range($start, $end);
    }

    private function assertExecutionSectorScope(Sector $sector, $user): void
    {
        if (! $this->userHasAssignedPortfolioScope($user)) {
            return;
        }

        abort_unless($this->sectorIsAssignedToUser($sector, $user), 403, 'This report is not assigned to your portfolio.');
    }

    private function assertExecutionProgramScope(Program $program, $user): void
    {
        if (! $this->userHasAssignedPortfolioScope($user)) {
            return;
        }

        abort_unless($this->programIsInAssignedPortfolio($program, $user), 403, 'This report is not assigned to your portfolio.');
    }

    private function assertExecutionProjectScope(Project $project, $user): void
    {
        if (! $this->userHasAssignedPortfolioScope($user)) {
            return;
        }

        abort_unless($this->projectIsInAssignedPortfolio($project, $user), 403, 'This report is not assigned to your portfolio.');
    }
}
