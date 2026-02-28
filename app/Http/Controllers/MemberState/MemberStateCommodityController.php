<?php

namespace App\Http\Controllers\MemberState;

use App\Http\Controllers\Controller;
use App\Models\Commodity;
use App\Models\MemberStateCommodityTrend;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MemberStateCommodityController extends Controller
{
    public function index(Request $request)
    {
        $memberStateId = $request->user()->member_state_id;

        $commodities = Commodity::query()
            ->orderBy('name')
            ->get();

        $trendQuery = MemberStateCommodityTrend::query()
            ->with('commodity')
            ->where('member_state_id', $memberStateId);

        if ($request->filled('commodity_id')) {
            $trendQuery->where('commodity_id', $request->input('commodity_id'));
        }

        if ($request->filled('from')) {
            $trendQuery->whereDate('recorded_on', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $trendQuery->whereDate('recorded_on', '<=', $request->input('to'));
        }

        $trends = $trendQuery
            ->orderByDesc('recorded_on')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $growthByCommodity = MemberStateCommodityTrend::query()
            ->join('myb_commodities as c', 'c.id', '=', 'myb_member_state_commodity_trends.commodity_id')
            ->where('myb_member_state_commodity_trends.member_state_id', $memberStateId)
            ->selectRaw('c.name as commodity_name')
            ->selectRaw('AVG(myb_member_state_commodity_trends.growth_rate_pct) as avg_growth_rate')
            ->selectRaw('SUM(myb_member_state_commodity_trends.export_value_usd) as total_export_value')
            ->groupBy('c.id', 'c.name')
            ->orderByDesc('total_export_value')
            ->limit(8)
            ->get();

        return view('member-state.commodities.index', [
            'memberState' => $request->user()->memberState,
            'commodities' => $commodities,
            'trends' => $trends,
            'growthByCommodity' => $growthByCommodity,
        ]);
    }

    public function storeCommodity(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:150'],
            'unit_of_measure' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:4000'],
        ]);

        $normalizedName = trim((string) $validated['name']);

        $alreadyExists = Commodity::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($normalizedName)])
            ->exists();

        if ($alreadyExists) {
            return back()
                ->withErrors([
                    'name' => 'This commodity already exists in the shared table. Select it below when adding trend data.',
                ])
                ->withInput();
        }

        Commodity::create([
            'name' => $normalizedName,
            'category' => $validated['category'] ?? null,
            'unit_of_measure' => $validated['unit_of_measure'] ?? null,
            'description' => $validated['description'] ?? null,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Commodity added to the shared AU commodity catalog.');
    }

    public function storeTrend(Request $request)
    {
        $memberStateId = $request->user()->member_state_id;

        $validated = $request->validate([
            'commodity_id' => ['required', 'exists:myb_commodities,id'],
            'recorded_on' => ['required', 'date'],
            'production_volume' => ['nullable', 'numeric'],
            'export_volume' => ['nullable', 'numeric'],
            'export_value_usd' => ['nullable', 'numeric'],
            'growth_rate_pct' => ['nullable', 'numeric', 'min:-100', 'max:1000'],
            'trend_summary' => ['nullable', 'string', 'max:4000'],
            'impact_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $duplicateRule = Rule::unique('myb_member_state_commodity_trends', 'commodity_id')
            ->where(function ($query) use ($memberStateId, $validated) {
                return $query
                    ->where('member_state_id', $memberStateId)
                    ->whereDate('recorded_on', $validated['recorded_on']);
            });

        $request->validate([
            'commodity_id' => [$duplicateRule],
        ], [
            'commodity_id.unique' => 'A trend entry for this commodity on the selected date already exists.',
        ]);

        MemberStateCommodityTrend::create(array_merge($validated, [
            'member_state_id' => $memberStateId,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]));

        return back()->with('success', 'Commodity trend data saved successfully.');
    }

    public function destroyTrend(Request $request, MemberStateCommodityTrend $trend)
    {
        abort_unless($trend->member_state_id === $request->user()->member_state_id, 403);

        $trend->delete();

        return back()->with('success', 'Commodity trend record deleted.');
    }
}
