<?php

namespace App\Http\Controllers\MemberState;

use App\Http\Controllers\Controller;
use App\Models\MemberStateCommodityTrend;
use App\Models\MemberStateCommunication;
use App\Models\MemberStateNationalData;
use App\Models\MemberStateQuestion;
use App\Models\TreatyMemberStateStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MemberStateDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->loadMissing('memberState');
        $memberStateId = $user->member_state_id;

        $requiredTables = [
            'myb_member_state_national_data',
            'myb_member_state_communications',
            'myb_member_state_questions',
            'myb_member_state_commodity_trends',
        ];
        $missingTables = collect($requiredTables)
            ->filter(fn ($table) => !Schema::hasTable($table))
            ->values();

        $hasNationalDataTable = Schema::hasTable('myb_member_state_national_data');
        $hasNationalDataReviewStatusColumn = $hasNationalDataTable
            && Schema::hasColumn('myb_member_state_national_data', 'review_status');
        $hasCommunicationTable = Schema::hasTable('myb_member_state_communications');
        $hasQuestionTable = Schema::hasTable('myb_member_state_questions');
        $hasCommodityTrendTable = Schema::hasTable('myb_member_state_commodity_trends');

        $nationalDataBaseQuery = $hasNationalDataTable
            ? MemberStateNationalData::where('member_state_id', $memberStateId)
            : null;

        $summary = [
            'national_data_count' => $hasNationalDataTable
                ? (clone $nationalDataBaseQuery)->count()
                : 0,
            'approved_national_data_count' => ($hasNationalDataTable && $hasNationalDataReviewStatusColumn)
                ? (clone $nationalDataBaseQuery)->where('review_status', 'approved')->count()
                : ($hasNationalDataTable ? (clone $nationalDataBaseQuery)->count() : 0),
            'pending_national_data_count' => ($hasNationalDataTable && $hasNationalDataReviewStatusColumn)
                ? (clone $nationalDataBaseQuery)->where('review_status', 'pending')->count()
                : 0,
            'avg_cooperation_score' => $hasNationalDataTable
                ? (float) (($hasNationalDataReviewStatusColumn
                    ? (clone $nationalDataBaseQuery)->where('review_status', 'approved')
                    : (clone $nationalDataBaseQuery)
                )->avg('cooperation_score') ?? 0)
                : 0,
            'pending_communications' => $hasCommunicationTable
                ? MemberStateCommunication::where('member_state_id', $memberStateId)
                    ->whereIn('status', ['pending_response', 'in_review'])
                    ->count()
                : 0,
            'open_questions' => $hasQuestionTable
                ? MemberStateQuestion::where('member_state_id', $memberStateId)
                    ->whereIn('status', ['open', 'in_review'])
                    ->count()
                : 0,
            'commodity_trend_count' => $hasCommodityTrendTable
                ? MemberStateCommodityTrend::where('member_state_id', $memberStateId)->count()
                : 0,
            'avg_commodity_growth' => $hasCommodityTrendTable
                ? (float) MemberStateCommodityTrend::where('member_state_id', $memberStateId)->avg('growth_rate_pct')
                : 0,
        ];

        $treatyStats = TreatyMemberStateStatus::query()
            ->where('member_state_id', $memberStateId)
            ->selectRaw('COUNT(*) as total_records')
            ->selectRaw('SUM(CASE WHEN is_signed IS TRUE THEN 1 ELSE 0 END) as signed_count')
            ->selectRaw('SUM(CASE WHEN is_ratified IS TRUE THEN 1 ELSE 0 END) as ratified_count')
            ->first();

        $latestNationalData = $hasNationalDataTable
            ? MemberStateNationalData::query()
                ->with(['aspiration', 'goal'])
                ->where('member_state_id', $memberStateId)
                ->orderByDesc('recorded_on')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
            : collect();

        $latestCommodityTrends = $hasCommodityTrendTable
            ? MemberStateCommodityTrend::query()
                ->with('commodity')
                ->where('member_state_id', $memberStateId)
                ->orderByDesc('recorded_on')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get()
            : collect();

        $comparisonRows = $hasNationalDataTable
            ? tap(
                DB::table('myb_member_state_national_data as d')
                    ->join('myb_au_member_states as s', 's.id', '=', 'd.member_state_id')
                    ->selectRaw('s.id as member_state_id, s.name as member_state_name')
                    ->selectRaw('AVG(d.cooperation_score) as avg_score')
                    ->selectRaw('COUNT(*) as data_points')
                    ->whereDate('d.recorded_on', '>=', now()->subMonths(6)->toDateString()),
                function ($query) use ($hasNationalDataReviewStatusColumn) {
                    if ($hasNationalDataReviewStatusColumn) {
                        $query->where('d.review_status', 'approved');
                    }
                }
            )
                ->groupBy('s.id', 's.name')
                ->orderByDesc('avg_score')
                ->limit(10)
                ->get()
                ->map(function ($row, $index) use ($memberStateId) {
                    $row->rank = $index + 1;
                    $row->is_current = $row->member_state_id === $memberStateId;
                    return $row;
                })
            : collect();

        return view('member-state.dashboard', [
            'memberState' => $user->memberState,
            'summary' => $summary,
            'treatyStats' => $treatyStats,
            'latestNationalData' => $latestNationalData,
            'latestCommodityTrends' => $latestCommodityTrends,
            'comparisonRows' => $comparisonRows,
            'missingTables' => $missingTables,
        ]);
    }
}
