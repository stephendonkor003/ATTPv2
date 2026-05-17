<?php

namespace App\Http\Controllers\MemberState;

use App\Http\Controllers\Controller;
use App\Models\AuAspiration;
use App\Models\AuMemberState;
use App\Models\Commodity;
use App\Models\MemberStateCommodityTrend;
use App\Models\MemberStateNationalData;
use App\Models\TreatyMemberStateStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MemberStateComparisonController extends Controller
{
    public function index(Request $request)
    {
        $memberStateId = $request->user()->member_state_id;

        $from = (string) $request->input('from', now()->subYear()->toDateString());
        $to = (string) $request->input('to', now()->toDateString());

        try {
            $fromDate = Carbon::parse($from)->startOfDay();
        } catch (\Throwable) {
            $fromDate = now()->subYear()->startOfDay();
        }

        try {
            $toDate = Carbon::parse($to)->endOfDay();
        } catch (\Throwable) {
            $toDate = now()->endOfDay();
        }

        if ($fromDate->gt($toDate)) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        $from = $fromDate->toDateString();
        $to = $toDate->toDateString();

        $aspirationId = (string) $request->input('aspiration_id', '');
        $goalId = (string) $request->input('goal_id', '');
        $reportingPeriodType = (string) $request->input('reporting_period_type', '');
        $progressStatus = (string) $request->input('progress_status', '');
        $minDataPoints = max(0, (int) $request->input('min_data_points', 0));
        $minCooperation = $request->filled('min_cooperation') ? (float) $request->input('min_cooperation') : null;
        $minRatification = $request->filled('min_ratification') ? (float) $request->input('min_ratification') : null;
        $growthDirection = (string) $request->input('growth_direction', '');
        if (!in_array($growthDirection, ['', 'positive', 'negative', 'flat'], true)) {
            $growthDirection = '';
        }

        $sortBy = (string) $request->input('sort_by', 'overall_index');
        $allowedSortBy = [
            'overall_index',
            'avg_cooperation_score',
            'awareness_composite_score',
            'avg_outreach_score',
            'treaty_ratification_rate',
            'treaty_signed_rate',
            'treaty_original_submission_rate',
            'avg_budget_execution_rate',
            'avg_growth_rate',
            'commodity_count',
            'export_value_total',
            'data_points',
            'people_reached_total',
        ];
        if (!in_array($sortBy, $allowedSortBy, true)) {
            $sortBy = 'overall_index';
        }

        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $maxResults = min(60, max(5, (int) $request->input('max_results', 20)));

        $allCommodities = Commodity::query()
            ->orderBy('name')
            ->get(['id', 'name']);
        $validCommodityIds = $allCommodities->pluck('id');
        $selectedCommodityIds = collect((array) $request->input('commodity_ids', []))
            ->filter()
            ->intersect($validCommodityIds)
            ->values();

        $allPeerStates = AuMemberState::query()
            ->active()
            ->ordered()
            ->where('id', '!=', $memberStateId)
            ->get(['id', 'name', 'code', 'code_alpha2']);

        $validPeerIds = $allPeerStates->pluck('id');
        $selectedPeerStateIds = collect((array) $request->input('peer_state_ids', []))
            ->filter()
            ->intersect($validPeerIds)
            ->values();

        if ($selectedPeerStateIds->isEmpty()) {
            $selectedPeerStateIds = $validPeerIds->take(10)->values();
        }

        $stateIds = collect([$memberStateId])
            ->merge($selectedPeerStateIds)
            ->unique()
            ->values();

        $stateLookup = AuMemberState::query()
            ->whereIn('id', $stateIds)
            ->get(['id', 'name', 'code', 'code_alpha2'])
            ->keyBy('id');

        $applyNationalFilters = function ($query) use ($aspirationId, $goalId, $reportingPeriodType, $progressStatus) {
            if ($aspirationId !== '') {
                $query->where('aspiration_id', $aspirationId);
            }
            if ($goalId !== '') {
                $query->where('goal_id', $goalId);
            }
            if ($reportingPeriodType !== '') {
                $query->where('reporting_period_type', $reportingPeriodType);
            }
            if ($progressStatus !== '') {
                $query->where('progress_status', $progressStatus);
            }
        };

        $nationalRowsQuery = MemberStateNationalData::query()
            ->selectRaw('member_state_id')
            ->selectRaw('COUNT(*) as data_points')
            ->selectRaw('AVG(cooperation_score) as avg_cooperation_score')
            ->selectRaw('AVG(agenda_awareness_score) as avg_agenda_awareness_score')
            ->selectRaw('AVG(flagship_awareness_score) as avg_flagship_awareness_score')
            ->selectRaw('AVG(outreach_coverage_score) as avg_outreach_score')
            ->selectRaw('SUM(COALESCE(people_reached, 0)) as people_reached_total')
            ->selectRaw('SUM(COALESCE(households_impacted, 0)) as households_impacted_total')
            ->selectRaw("SUM(CASE WHEN progress_status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->selectRaw("SUM(CASE WHEN progress_status IN ('in_progress', 'advanced') THEN 1 ELSE 0 END) as in_flight_count")
            ->selectRaw('AVG(CASE WHEN budget_allocated_usd > 0 THEN (budget_executed_usd / budget_allocated_usd) * 100 ELSE NULL END) as avg_budget_execution_rate')
            ->whereIn('member_state_id', $stateIds)
            ->where('review_status', 'approved')
            ->whereBetween('recorded_on', [$from, $to]);

        $applyNationalFilters($nationalRowsQuery);

        $nationalRows = $nationalRowsQuery
            ->groupBy('member_state_id')
            ->get()
            ->keyBy('member_state_id');

        $treatyRows = TreatyMemberStateStatus::query()
            ->selectRaw('member_state_id')
            ->selectRaw('SUM(CASE WHEN is_signed IS TRUE THEN 1 ELSE 0 END) as signed_count')
            ->selectRaw('SUM(CASE WHEN is_ratified IS TRUE THEN 1 ELSE 0 END) as ratified_count')
            ->selectRaw('SUM(CASE WHEN is_original_submitted IS TRUE THEN 1 ELSE 0 END) as original_count')
            ->selectRaw('COUNT(*) as total_treaties')
            ->whereIn('member_state_id', $stateIds)
            ->groupBy('member_state_id')
            ->get()
            ->keyBy('member_state_id');

        $treatyNameRows = TreatyMemberStateStatus::query()
            ->with(['treaty:id,reference_code,title'])
            ->whereIn('member_state_id', $stateIds)
            ->get(['member_state_id', 'treaty_id', 'is_signed', 'is_ratified', 'is_original_submitted']);

        $stateTreatyNames = $treatyNameRows
            ->groupBy('member_state_id')
            ->map(function ($items) {
                $formatTreaty = function ($item): ?string {
                    $title = trim((string) ($item->treaty?->title ?? ''));
                    $code = trim((string) ($item->treaty?->reference_code ?? ''));
                    if ($title === '' && $code === '') {
                        return null;
                    }
                    return $code !== '' ? "{$code} - {$title}" : $title;
                };

                return [
                    'signed' => $items->where('is_signed', true)->map($formatTreaty)->filter()->unique()->values()->all(),
                    'ratified' => $items->where('is_ratified', true)->map($formatTreaty)->filter()->unique()->values()->all(),
                    'original' => $items->where('is_original_submitted', true)->map($formatTreaty)->filter()->unique()->values()->all(),
                ];
            });

        $commodityRowsQuery = MemberStateCommodityTrend::query()
            ->selectRaw('member_state_id')
            ->selectRaw('AVG(growth_rate_pct) as avg_growth_rate')
            ->selectRaw('COUNT(*) as commodity_points')
            ->selectRaw('COUNT(DISTINCT commodity_id) as commodity_count')
            ->selectRaw('SUM(COALESCE(export_value_usd, 0)) as total_export_value')
            ->selectRaw('SUM(COALESCE(production_volume, 0)) as total_production_volume')
            ->selectRaw('SUM(COALESCE(export_volume, 0)) as total_export_volume')
            ->whereIn('member_state_id', $stateIds)
            ->whereBetween('recorded_on', [$from, $to]);

        if ($selectedCommodityIds->isNotEmpty()) {
            $commodityRowsQuery->whereIn('commodity_id', $selectedCommodityIds);
        }

        $commodityRows = $commodityRowsQuery
            ->groupBy('member_state_id')
            ->get()
            ->keyBy('member_state_id');

        $commodityBreakdownQuery = MemberStateCommodityTrend::query()
            ->selectRaw('member_state_id')
            ->selectRaw('commodity_id')
            ->selectRaw('AVG(growth_rate_pct) as avg_growth_rate')
            ->selectRaw('COUNT(*) as data_points')
            ->selectRaw('SUM(COALESCE(export_value_usd, 0)) as total_export_value')
            ->whereIn('member_state_id', $stateIds)
            ->whereBetween('recorded_on', [$from, $to]);

        if ($selectedCommodityIds->isNotEmpty()) {
            $commodityBreakdownQuery->whereIn('commodity_id', $selectedCommodityIds);
        }

        $commodityNameLookup = $allCommodities->pluck('name', 'id');
        $stateCommodityHighlights = $commodityBreakdownQuery
            ->groupBy('member_state_id', 'commodity_id')
            ->get()
            ->groupBy('member_state_id')
            ->map(function ($stateRows) use ($commodityNameLookup) {
                return $stateRows
                    ->sortByDesc(function ($row) {
                        return (float) ($row->total_export_value ?? 0);
                    })
                    ->take(3)
                    ->map(function ($row) use ($commodityNameLookup) {
                        $name = (string) ($commodityNameLookup->get($row->commodity_id) ?? 'Unknown commodity');
                        $avgGrowth = round((float) ($row->avg_growth_rate ?? 0), 2);
                        $exportValue = round((float) ($row->total_export_value ?? 0), 2);

                        return [
                            'name' => $name,
                            'avg_growth_rate' => $avgGrowth,
                            'export_value' => $exportValue,
                            'data_points' => (int) ($row->data_points ?? 0),
                            'label' => $name . ' (' . number_format($avgGrowth, 2) . '%, $' . number_format($exportValue, 0) . ')',
                        ];
                    })
                    ->values()
                    ->all();
            });

        $baseRows = $stateIds->map(function ($stateId) use ($stateLookup, $nationalRows, $treatyRows, $commodityRows, $memberStateId, $stateTreatyNames, $stateCommodityHighlights) {
            $score = $nationalRows->get($stateId);
            $treaty = $treatyRows->get($stateId);
            $commodity = $commodityRows->get($stateId);
            $treatyNames = $stateTreatyNames->get($stateId, [
                'signed' => [],
                'ratified' => [],
                'original' => [],
            ]);
            $commodityHighlights = $stateCommodityHighlights->get($stateId, []);

            $avgCooperation = round((float) ($score->avg_cooperation_score ?? 0), 2);
            $dataPoints = (int) ($score->data_points ?? 0);
            $agendaAwareness = round((float) ($score->avg_agenda_awareness_score ?? 0), 2);
            $flagshipAwareness = round((float) ($score->avg_flagship_awareness_score ?? 0), 2);
            $outreachScore = round((float) ($score->avg_outreach_score ?? 0), 2);
            $awarenessComposite = round(($agendaAwareness + $flagshipAwareness) / 2, 2);
            $peopleReachedTotal = (int) ($score->people_reached_total ?? 0);
            $householdsImpactedTotal = (int) ($score->households_impacted_total ?? 0);
            $completedCount = (int) ($score->completed_count ?? 0);
            $inFlightCount = (int) ($score->in_flight_count ?? 0);
            $avgBudgetExecutionRate = round((float) ($score->avg_budget_execution_rate ?? 0), 2);
            $budgetExecutionScore = max(0, min(100, $avgBudgetExecutionRate));

            $signedCount = (int) ($treaty->signed_count ?? 0);
            $ratifiedCount = (int) ($treaty->ratified_count ?? 0);
            $originalCount = (int) ($treaty->original_count ?? 0);
            $totalTreaties = (int) ($treaty->total_treaties ?? 0);
            $signedRate = $totalTreaties > 0 ? round(($signedCount / $totalTreaties) * 100, 2) : 0;
            $ratifiedRate = $totalTreaties > 0 ? round(($ratifiedCount / $totalTreaties) * 100, 2) : 0;
            $originalRate = $totalTreaties > 0 ? round(($originalCount / $totalTreaties) * 100, 2) : 0;

            $avgGrowth = round((float) ($commodity->avg_growth_rate ?? 0), 2);
            $commodityEntries = (int) ($commodity->commodity_points ?? 0);
            $commodityCount = (int) ($commodity->commodity_count ?? 0);
            $exportValueTotal = round((float) ($commodity->total_export_value ?? 0), 2);
            $productionVolumeTotal = round((float) ($commodity->total_production_volume ?? 0), 2);
            $exportVolumeTotal = round((float) ($commodity->total_export_volume ?? 0), 2);

            $growthScore = max(0, min(100, round($avgGrowth + 50, 2)));
            $completionRate = $dataPoints > 0 ? round(($completedCount / $dataPoints) * 100, 2) : 0;

            $treatyPipelineScore = round(
                ($signedRate * 0.25) +
                ($ratifiedRate * 0.45) +
                ($originalRate * 0.30),
                2
            );

            $overallIndex = round(
                ($avgCooperation * 0.30) +
                ($awarenessComposite * 0.15) +
                ($outreachScore * 0.12) +
                ($treatyPipelineScore * 0.23) +
                ($growthScore * 0.08) +
                ($budgetExecutionScore * 0.07) +
                ($completionRate * 0.05),
                2
            );

            $row = [
                'member_state_id' => $stateId,
                'member_state_name' => $stateLookup->get($stateId)?->name ?? 'Unknown',
                'member_state_code' => $stateLookup->get($stateId)?->code,
                'avg_cooperation_score' => $avgCooperation,
                'avg_agenda_awareness_score' => $agendaAwareness,
                'avg_flagship_awareness_score' => $flagshipAwareness,
                'awareness_composite_score' => $awarenessComposite,
                'avg_outreach_score' => $outreachScore,
                'avg_budget_execution_rate' => $avgBudgetExecutionRate,
                'treaty_signed_rate' => $signedRate,
                'treaty_ratification_rate' => $ratifiedRate,
                'treaty_original_submission_rate' => $originalRate,
                'signed_count' => $signedCount,
                'ratified_count' => $ratifiedCount,
                'original_count' => $originalCount,
                'treaty_signed_names' => $treatyNames['signed'],
                'treaty_ratified_names' => $treatyNames['ratified'],
                'treaty_original_names' => $treatyNames['original'],
                'total_treaties' => $totalTreaties,
                'avg_growth_rate' => $avgGrowth,
                'growth_score' => $growthScore,
                'commodity_points' => $commodityEntries,
                'commodity_count' => $commodityCount,
                'export_value_total' => $exportValueTotal,
                'production_volume_total' => $productionVolumeTotal,
                'export_volume_total' => $exportVolumeTotal,
                'top_commodities' => $commodityHighlights,
                'overall_index' => $overallIndex,
                'data_points' => $dataPoints,
                'people_reached_total' => $peopleReachedTotal,
                'households_impacted_total' => $householdsImpactedTotal,
                'completed_count' => $completedCount,
                'in_flight_count' => $inFlightCount,
                'is_current' => $stateId === $memberStateId,
                'dimension_scores' => [
                    $avgCooperation,
                    $awarenessComposite,
                    $outreachScore,
                    $ratifiedRate,
                    $originalRate,
                    $growthScore,
                ],
            ];

            $row['summary'] = $this->buildStateNarrative($row);

            return $row;
        });

        $filteredRows = $baseRows->filter(function (array $row) use ($minDataPoints, $minCooperation, $minRatification, $growthDirection) {
            if ($row['is_current']) {
                return true;
            }

            if ($row['data_points'] < $minDataPoints) {
                return false;
            }

            if ($minCooperation !== null && $row['avg_cooperation_score'] < $minCooperation) {
                return false;
            }

            if ($minRatification !== null && $row['treaty_ratification_rate'] < $minRatification) {
                return false;
            }

            if ($growthDirection === 'positive' && $row['avg_growth_rate'] <= 0) {
                return false;
            }
            if ($growthDirection === 'negative' && $row['avg_growth_rate'] >= 0) {
                return false;
            }
            if ($growthDirection === 'flat' && abs((float) $row['avg_growth_rate']) > 0.5) {
                return false;
            }

            return true;
        });

        $sortedRows = ($sortDir === 'asc' ? $filteredRows->sortBy($sortBy) : $filteredRows->sortByDesc($sortBy))
            ->values()
            ->map(function ($row, $index) {
                $row['rank'] = $index + 1;
                return $row;
            });

        $currentRow = $sortedRows->firstWhere('is_current', true);
        $peerRows = $sortedRows->where('is_current', false)->values();
        $topPerformer = $sortedRows->first();

        $rows = $sortedRows->take($maxResults)->values();
        if ($currentRow && !$rows->contains(fn ($row) => $row['member_state_id'] === $currentRow['member_state_id'])) {
            $rows = $rows->push($currentRow)->sortBy('rank')->values();
        }

        $peerAverage = [
            'overall_index' => round((float) ($peerRows->avg('overall_index') ?? 0), 2),
            'avg_cooperation_score' => round((float) ($peerRows->avg('avg_cooperation_score') ?? 0), 2),
            'awareness_composite_score' => round((float) ($peerRows->avg('awareness_composite_score') ?? 0), 2),
            'avg_outreach_score' => round((float) ($peerRows->avg('avg_outreach_score') ?? 0), 2),
            'treaty_ratification_rate' => round((float) ($peerRows->avg('treaty_ratification_rate') ?? 0), 2),
            'treaty_original_submission_rate' => round((float) ($peerRows->avg('treaty_original_submission_rate') ?? 0), 2),
            'avg_growth_rate' => round((float) ($peerRows->avg('avg_growth_rate') ?? 0), 2),
            'commodity_count' => round((float) ($peerRows->avg('commodity_count') ?? 0), 2),
            'export_value_total' => round((float) ($peerRows->avg('export_value_total') ?? 0), 2),
            'avg_budget_execution_rate' => round((float) ($peerRows->avg('avg_budget_execution_rate') ?? 0), 2),
            'data_points' => (int) ($peerRows->avg('data_points') ?? 0),
            'dimension_scores' => [
                round((float) ($peerRows->avg('avg_cooperation_score') ?? 0), 2),
                round((float) ($peerRows->avg('awareness_composite_score') ?? 0), 2),
                round((float) ($peerRows->avg('avg_outreach_score') ?? 0), 2),
                round((float) ($peerRows->avg('treaty_ratification_rate') ?? 0), 2),
                round((float) ($peerRows->avg('treaty_original_submission_rate') ?? 0), 2),
                round((float) ($peerRows->avg('growth_score') ?? 0), 2),
            ],
        ];

        $monthlyRowsQuery = MemberStateNationalData::query()
            ->selectRaw("member_state_id, to_char(recorded_on, 'YYYY-MM') as month_key")
            ->selectRaw('AVG(cooperation_score) as avg_cooperation')
            ->whereIn('member_state_id', $stateIds)
            ->where('review_status', 'approved')
            ->whereBetween('recorded_on', [$from, $to]);

        $applyNationalFilters($monthlyRowsQuery);

        $monthlyRows = $monthlyRowsQuery
            ->groupByRaw("member_state_id, to_char(recorded_on, 'YYYY-MM')")
            ->orderBy('month_key')
            ->get();

        $months = collect();
        $monthCursor = $fromDate->copy()->startOfMonth();
        $monthEnd = $toDate->copy()->startOfMonth();
        while ($monthCursor->lte($monthEnd)) {
            $months->push($monthCursor->format('Y-m'));
            $monthCursor->addMonth();
        }
        if ($months->isEmpty()) {
            $months->push(now()->format('Y-m'));
        }

        $monthlyByState = $monthlyRows
            ->groupBy('member_state_id')
            ->map(function ($stateRows) {
                return $stateRows->mapWithKeys(function ($row) {
                    return [$row->month_key => round((float) ($row->avg_cooperation ?? 0), 2)];
                });
            });

        $currentTrend = $months->map(function ($month) use ($monthlyByState, $memberStateId) {
            return $monthlyByState->get($memberStateId)?->get($month);
        })->values();

        $peerIdsForTrend = $rows->where('is_current', false)->pluck('member_state_id');
        $peerTrend = $months->map(function ($month) use ($monthlyByState, $peerIdsForTrend) {
            $values = $peerIdsForTrend
                ->map(function ($peerId) use ($monthlyByState, $month) {
                    return $monthlyByState->get($peerId)?->get($month);
                })
                ->filter(fn ($value) => $value !== null);

            return $values->isNotEmpty() ? round((float) $values->avg(), 2) : null;
        })->values();

        $insights = $this->buildComparisonNarrative($currentRow, $peerAverage, $topPerformer);

        $chartPayload = [
            'labels' => $rows->pluck('member_state_name')->values(),
            'overallIndex' => $rows->pluck('overall_index')->values(),
            'cooperation' => $rows->pluck('avg_cooperation_score')->values(),
            'ratification' => $rows->pluck('treaty_ratification_rate')->values(),
            'signed' => $rows->pluck('treaty_signed_rate')->values(),
            'original' => $rows->pluck('treaty_original_submission_rate')->values(),
            'commodityGrowth' => $rows->pluck('avg_growth_rate')->values(),
            'commodityCoverage' => $rows->pluck('commodity_count')->values(),
            'commodityExportValue' => $rows->pluck('export_value_total')->values(),
            'commodityTop' => $rows->pluck('top_commodities')->values(),
            'treatyNames' => [
                'signed' => $rows->pluck('treaty_signed_names')->values(),
                'ratified' => $rows->pluck('treaty_ratified_names')->values(),
                'original' => $rows->pluck('treaty_original_names')->values(),
            ],
            'months' => $months->values(),
            'currentTrend' => $currentTrend,
            'peerTrend' => $peerTrend,
            'currentDimensions' => $currentRow['dimension_scores'] ?? [0, 0, 0, 0, 0, 0],
            'peerDimensions' => $peerAverage['dimension_scores'] ?? [0, 0, 0, 0, 0, 0],
        ];

        $aspirations = AuAspiration::query()
            ->active()
            ->ordered()
            ->with(['goals' => function ($goalQuery) {
                $goalQuery->active()->ordered();
            }])
            ->get(['id', 'number', 'title']);

        return view('member-state.comparisons.index', [
            'memberState' => $request->user()->memberState,
            'allPeerStates' => $allPeerStates,
            'selectedPeerStateIds' => $selectedPeerStateIds,
            'allCommodities' => $allCommodities,
            'selectedCommodityIds' => $selectedCommodityIds,
            'aspirations' => $aspirations,
            'from' => $from,
            'to' => $to,
            'rows' => $rows,
            'currentRow' => $currentRow,
            'peerAverage' => $peerAverage,
            'topPerformer' => $topPerformer,
            'insights' => $insights,
            'chartPayload' => $chartPayload,
            'filters' => [
                'aspiration_id' => $aspirationId,
                'goal_id' => $goalId,
                'reporting_period_type' => $reportingPeriodType,
                'progress_status' => $progressStatus,
                'min_data_points' => (string) $minDataPoints,
                'min_cooperation' => $minCooperation !== null ? (string) $minCooperation : '',
                'min_ratification' => $minRatification !== null ? (string) $minRatification : '',
                'growth_direction' => $growthDirection,
                'commodity_ids' => $selectedCommodityIds->values()->all(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'max_results' => (string) $maxResults,
            ],
        ]);
    }

    private function buildStateNarrative(array $row): string
    {
        $strengths = [];
        if (($row['avg_cooperation_score'] ?? 0) >= 70) {
            $strengths[] = 'strong cooperation execution';
        }
        if (($row['treaty_ratification_rate'] ?? 0) >= 60) {
            $strengths[] = 'good treaty ratification momentum';
        }
        if (($row['awareness_composite_score'] ?? 0) >= 65) {
            $strengths[] = 'solid citizen awareness coverage';
        }

        $gaps = [];
        if (($row['treaty_original_submission_rate'] ?? 0) < 40) {
            $gaps[] = 'accelerate original instrument submission';
        }
        if (($row['avg_outreach_score'] ?? 0) < 55) {
            $gaps[] = 'increase outreach depth';
        }
        if (($row['data_points'] ?? 0) < 6) {
            $gaps[] = 'submit more approved national-data evidence';
        }

        $strengthText = !empty($strengths) ? implode(', ', $strengths) : 'balanced cross-pillar performance';
        $gapText = !empty($gaps) ? implode('; ', $gaps) : 'maintain current delivery quality and consistency';

        return "{$row['member_state_name']} shows {$strengthText}. Priority focus: {$gapText}.";
    }

    private function buildComparisonNarrative(?array $currentRow, array $peerAverage, ?array $topPerformer): array
    {
        if (!$currentRow) {
            return [
                'headline' => 'No approved national data was available for the selected filters and period.',
                'strength' => 'Broaden filters or submit additional national-data evidence for stronger benchmarking.',
                'gap' => 'Comparison insights become reliable only after sufficient approved entries are available.',
            ];
        }

        $rank = (int) ($currentRow['rank'] ?? 0);
        $gapToPeers = round((float) ($currentRow['overall_index'] ?? 0) - (float) ($peerAverage['overall_index'] ?? 0), 2);
        $gapToTop = $topPerformer ? round((float) ($topPerformer['overall_index'] ?? 0) - (float) ($currentRow['overall_index'] ?? 0), 2) : 0;

        $headline = "Rank #{$rank} with an overall index of " . number_format((float) ($currentRow['overall_index'] ?? 0), 2) . " based only on approved national data.";

        $strength = $gapToPeers >= 0
            ? "Current performance is +" . number_format($gapToPeers, 2) . " points above peer average, led by cooperation and treaty implementation depth."
            : "Current performance is " . number_format(abs($gapToPeers), 2) . " points below peer average; improving outreach and treaty conversion can close this quickly.";

        $gap = $gapToTop > 0
            ? "Gap to top performer: " . number_format($gapToTop, 2) . " points. Focus on ratification-to-original pipeline and monthly approved reporting cadence."
            : 'Current state is leading this comparison window; preserve performance with consistent approved submissions.';

        return [
            'headline' => $headline,
            'strength' => $strength,
            'gap' => $gap,
        ];
    }
}
