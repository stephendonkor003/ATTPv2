<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\Sector;
use App\Models\Program;
use App\Models\Project;
use App\Models\BudgetCommitment;
use App\Services\ExecutionInsightBuilder;

class MasterDashboard extends Controller
{
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
        $data = $this->executionDashboardPayload($request);
        $filename = 'execution-dashboard-' . now()->format('Ymd-His') . '.pdf';

        return Pdf::loadView('finance.execution.dashboard_pdf', $data)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    private function executionDashboardPayload(Request $request): array
    {
        /* ============================================================
         * 1. FILTER INPUTS
         * ============================================================ */
        $sectorId  = $request->get('sector_id');
        $programId = $request->get('program_id');
        $projectId = $request->get('project_id');

        /* ============================================================
         * 2. FILTER DATA (FOR DROPDOWNS)
         * ============================================================ */
        $sectors = Sector::orderBy('name')->get();

        $programs = $sectorId
            ? Program::where('sector_id', $sectorId)->orderBy('name')->get()
            : Program::orderBy('name')->get();

        $projects = $programId
            ? Project::where('program_id', $programId)->orderBy('name')->get()
            : collect();

        /* ============================================================
         * 3. RESOLVE EXECUTION SCOPE
         * ============================================================ */
        if ($projectId) {
            $scopeType = 'project';
            $scope = Project::with('program')->findOrFail($projectId);
            $years = $scope->years();

        } elseif ($programId) {
            $scopeType = 'program';
            $scope = Program::findOrFail($programId);
            $years = $scope->years();

        } elseif ($sectorId) {
            $scopeType = 'sector';
            $scope = Sector::findOrFail($sectorId);

            $range = Program::where('sector_id', $sectorId)
                ->select(
                    DB::raw('MIN(start_year) as start'),
                    DB::raw('MAX(end_year) as end')
                )->first();

            $years = range($range->start, $range->end);

        } else {
            $scopeType = 'global';
            $scope = null;

            $range = Program::select(
                DB::raw('MIN(start_year) as start'),
                DB::raw('MAX(end_year) as end')
            )->first();

            $years = range($range->start, $range->end);
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
        $commitmentByYear = BudgetCommitment::whereIn(
                'status',
                [
                    BudgetCommitment::STATUS_SUBMITTED,
                    BudgetCommitment::STATUS_APPROVED
                ]
            )
            ->when($scopeType === 'program', function ($q) use ($scope) {
                $q->whereHas('programFunding', function ($qq) use ($scope) {
                    $qq->where('program_id', $scope->id);
                });
            })
            ->when($scopeType === 'project', function ($q) use ($scope) {
                $q->where('allocation_level', 'project')
                  ->where('allocation_id', $scope->id);
            })
            ->select(
                'commitment_year',
                DB::raw('SUM(commitment_amount) as total')
            )
            ->groupBy('commitment_year')
            ->pluck('total', 'commitment_year')
            ->toArray();

        /* ============================================================
         * 6. KPI CALCULATIONS
         * ============================================================ */
        $totalAllocation = array_sum($allocationByYear);
        $totalCommitment = array_sum($commitmentByYear);

        $executionRate = $totalAllocation > 0
            ? round(($totalCommitment / $totalAllocation) * 100, 2)
            : 0;

        $variance = $totalAllocation - $totalCommitment;

        /* ============================================================
         * 7. LINE CHART DATA
         * ============================================================ */
        $lineChart = [
            'labels' => $years,
            'allocation' => array_values($allocationByYear),
            'commitment' => array_map(
                fn ($y) => $commitmentByYear[$y] ?? 0,
                $years
            ),
        ];

        /* ============================================================
         * 8. HEAT MAP DATA
         * ============================================================ */
        $heatmap = collect($years)->map(function ($year) use (
            $allocationByYear,
            $commitmentByYear
        ) {
            $alloc = $allocationByYear[$year] ?? 0;
            $commit = $commitmentByYear[$year] ?? 0;

            return [
                'year' => $year,
                'allocation' => $alloc,
                'commitment' => $commit,
                'execution_rate' => $alloc > 0
                    ? round(($commit / $alloc) * 100, 1)
                    : 0
            ];
        });

        /* ============================================================
         * 9. RADAR METRICS
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
            'budget_utilization' => round($budgetUtilization, 1),
            'timeliness' => round($timeliness, 1),
            'consistency' => round($consistency, 1),
            'coverage' => round($timeliness, 1),
            'risk_exposure' => round(100 - $riskExposure, 1),
        ];

        /* ============================================================
         * 10. AI PAYLOAD & INSIGHTS
         * ============================================================ */
        $aiPayload = [
            'scope' => $scopeType,
            'total_allocation' => $totalAllocation,
            'total_commitment' => $totalCommitment,
            'execution_rate' => $executionRate,
            'variance' => $variance,
            'yearly' => $heatmap->values()->toArray(),
        ];

        $aiInsights = ExecutionInsightBuilder::build($aiPayload);

        /* ============================================================
         * 11. RETURN VIEW
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
            'totalAllocation',
            'totalCommitment',
            'executionRate',
            'variance',
            'lineChart',
            'heatmap',
            'radarMetrics',
            'aiInsights'
        );
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
                DB::table('myb_project_allocations')
                    ->where('year', $year)
                    ->sum('amount'),
        };
    }
}
