<?php

namespace App\Http\Controllers;

use App\Models\WebsiteVisit;
use App\Models\WebsiteVisitActivity;
use App\Models\WorldBankCountry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WebsiteVisitAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin($request);

        $filters = $this->filters($request);
        $visitQuery = $this->filteredVisitQuery($filters);
        $countryReference = $this->countryReference($filters);
        $countryStats = $this->countryStats($visitQuery);
        $countryStatsByIso2 = $countryStats->keyBy('country_iso2');

        $summary = [
            'total_visits' => (clone $visitQuery)->count(),
            'unique_visitors' => (clone $visitQuery)->distinct('visitor_uuid')->count('visitor_uuid'),
            'page_views' => (int) (clone $visitQuery)->sum('page_views'),
            'total_duration_seconds' => (int) (clone $visitQuery)->sum('duration_seconds'),
            'average_duration_seconds' => (int) round((float) (clone $visitQuery)->avg('duration_seconds')),
            'active_visitors' => (clone $visitQuery)
                ->where('is_active', true)
                ->where('last_seen_at', '>=', now()->subMinutes(5))
                ->count(),
        ];

        $mapPayload = $this->mapPayload($countryReference, $countryStatsByIso2);
        $topPages = $this->topPages($filters);
        $continentRows = $this->continentRows($visitQuery);
        $dailyTrend = $this->dailyTrend($visitQuery);
        $recentActivities = $this->activityBaseQuery($filters)
            ->with('visit')
            ->latest('occurred_at')
            ->limit(10)
            ->get();

        $continents = $this->continents();
        $countries = $this->countries();

        return view('website-visit-analysis.index', [
            'summary' => $summary,
            'filters' => $filters,
            'continents' => $continents,
            'countries' => $countries,
            'countryRows' => $countryStats,
            'continentRows' => $continentRows,
            'topPages' => $topPages,
            'recentActivities' => $recentActivities,
            'mapMarkers' => $mapPayload['markers'],
            'mapRegionValues' => $mapPayload['region_values'],
            'mapCounts' => $mapPayload['counts'],
            'dailyTrend' => $dailyTrend,
        ]);
    }

    public function activity(Request $request)
    {
        $this->authorizeAdmin($request);

        $filters = $this->filters($request);
        $activityType = trim((string) $request->query('activity_type'));
        $search = trim((string) $request->query('q'));

        $query = $this->activityBaseQuery($filters)->with('visit');

        if ($activityType !== '') {
            $query->where('activity_type', $activityType);
        }

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search) {
                $like = '%' . $search . '%';
                $builder->where('url', 'like', $like)
                    ->orWhere('path', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhereHas('visit', function (Builder $visitQuery) use ($like) {
                        $visitQuery->where('ip_address', 'like', $like)
                            ->orWhere('visitor_uuid', 'like', $like)
                            ->orWhere('country_name', 'like', $like)
                            ->orWhere('country_iso2', 'like', $like);
                    });
            });
        }

        $activities = $query
            ->latest('occurred_at')
            ->paginate(30)
            ->withQueryString();

        return view('website-visit-analysis.activity', [
            'activities' => $activities,
            'filters' => [
                ...$filters,
                'activity_type' => $activityType,
                'q' => $search,
            ],
            'continents' => $this->continents(),
            'countries' => $this->countries(),
            'activityTypes' => ['page_view', 'exit'],
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && ($user->isSuperAdmin() || $user->isAdmin()), 403);
    }

    private function filters(Request $request): array
    {
        $country = strtoupper(trim((string) $request->query('country')));

        return [
            'continent' => trim((string) $request->query('continent')),
            'country' => preg_match('/^[A-Z]{2}$/', $country) ? $country : '',
        ];
    }

    private function filteredVisitQuery(array $filters): Builder
    {
        return WebsiteVisit::query()
            ->when($filters['continent'] !== '', fn (Builder $query) => $query->where('continent', $filters['continent']))
            ->when($filters['country'] !== '', fn (Builder $query) => $query->where('country_iso2', $filters['country']));
    }

    private function activityBaseQuery(array $filters): Builder
    {
        return WebsiteVisitActivity::query()
            ->whereHas('visit', function (Builder $query) use ($filters) {
                $query
                    ->when($filters['continent'] !== '', fn (Builder $builder) => $builder->where('continent', $filters['continent']))
                    ->when($filters['country'] !== '', fn (Builder $builder) => $builder->where('country_iso2', $filters['country']));
            });
    }

    private function countryStats(Builder $visitQuery)
    {
        return (clone $visitQuery)
            ->whereNotNull('country_iso2')
            ->select([
                'country_iso2',
                'country_name',
                'continent',
                DB::raw('COUNT(*) as visits'),
                DB::raw('COUNT(DISTINCT visitor_uuid) as visitors'),
                DB::raw('COUNT(DISTINCT COALESCE(ip_hash, ip_address)) as unique_ips'),
                DB::raw('SUM(page_views) as page_views'),
                DB::raw('SUM(duration_seconds) as total_duration_seconds'),
                DB::raw('AVG(duration_seconds) as average_duration_seconds'),
                DB::raw('MAX(ip_address) as sample_ip_address'),
                DB::raw('MAX(last_seen_at) as last_seen_at'),
            ])
            ->groupBy('country_iso2', 'country_name', 'continent')
            ->orderByDesc('visits')
            ->get()
            ->map(function ($row) {
                $row->visits = (int) $row->visits;
                $row->visitors = (int) $row->visitors;
                $row->unique_ips = (int) $row->unique_ips;
                $row->page_views = (int) $row->page_views;
                $row->total_duration_seconds = (int) $row->total_duration_seconds;
                $row->average_duration_seconds = (int) round((float) $row->average_duration_seconds);

                return $row;
            });
    }

    private function continentRows(Builder $visitQuery)
    {
        return (clone $visitQuery)
            ->whereNotNull('continent')
            ->select([
                'continent',
                DB::raw('COUNT(*) as visits'),
                DB::raw('COUNT(DISTINCT visitor_uuid) as visitors'),
                DB::raw('SUM(page_views) as page_views'),
                DB::raw('SUM(duration_seconds) as total_duration_seconds'),
            ])
            ->groupBy('continent')
            ->orderByDesc('visits')
            ->limit(8)
            ->get();
    }

    private function topPages(array $filters)
    {
        return $this->activityBaseQuery($filters)
            ->where('activity_type', 'page_view')
            ->whereNotNull('path')
            ->select([
                'path',
                DB::raw('MAX(url) as url'),
                DB::raw('MAX(title) as title'),
                DB::raw('COUNT(*) as views'),
                DB::raw('COUNT(DISTINCT website_visit_id) as visits'),
                DB::raw('AVG(duration_seconds) as average_duration_seconds'),
                DB::raw('MAX(occurred_at) as last_seen_at'),
            ])
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(10)
            ->get();
    }

    private function dailyTrend(Builder $visitQuery): array
    {
        $from = now()->subDays(13)->startOfDay();
        $rows = (clone $visitQuery)
            ->where('first_seen_at', '>=', $from)
            ->select([
                DB::raw('DATE(first_seen_at) as visit_date'),
                DB::raw('COUNT(*) as visits'),
                DB::raw('SUM(page_views) as page_views'),
            ])
            ->groupBy(DB::raw('DATE(first_seen_at)'))
            ->orderBy('visit_date')
            ->get()
            ->keyBy('visit_date');

        $labels = [];
        $visits = [];
        $pageViews = [];

        for ($day = 0; $day < 14; $day++) {
            $date = $from->copy()->addDays($day);
            $key = $date->toDateString();
            $labels[] = $date->format('d M');
            $visits[] = (int) ($rows[$key]->visits ?? 0);
            $pageViews[] = (int) ($rows[$key]->page_views ?? 0);
        }

        return [
            'labels' => $labels,
            'visits' => $visits,
            'page_views' => $pageViews,
        ];
    }

    private function mapPayload($countryReference, $countryStatsByIso2): array
    {
        $markers = [];
        $regionValues = [];
        $withData = 0;
        $withoutData = 0;

        foreach ($countryReference as $country) {
            $iso2 = strtoupper((string) $country->iso2_code);
            if ($iso2 === '') {
                continue;
            }

            $stats = $countryStatsByIso2->get($iso2);
            $hasData = (bool) $stats;
            $regionValues[$iso2] = $hasData ? 1 : 0;

            if ($hasData) {
                $withData++;
            } else {
                $withoutData++;
            }

            if (is_numeric($country->latitude) && is_numeric($country->longitude)) {
                $visits = (int) ($stats->visits ?? 0);
                $visitors = (int) ($stats->visitors ?? 0);
                $uniqueIps = (int) ($stats->unique_ips ?? 0);
                $pageViews = (int) ($stats->page_views ?? 0);
                $duration = (int) ($stats->total_duration_seconds ?? 0);
                $markers[] = [
                    'latLng' => [(float) $country->latitude, (float) $country->longitude],
                    'name' => $country->name,
                    'code' => $iso2,
                    'continent' => $country->continent,
                    'hasData' => $hasData,
                    'visits' => $visits,
                    'visitors' => $visitors,
                    'uniqueIps' => $uniqueIps,
                    'pageViews' => $pageViews,
                    'sampleIpAddress' => $stats->sample_ip_address ?? null,
                    'durationLabel' => $this->durationLabel($duration),
                    'style' => [
                        'fill' => $hasData ? '#16a34a' : '#dc2626',
                        'stroke' => '#ffffff',
                        'r' => $hasData ? 5 : 3,
                    ],
                ];
            }
        }

        return [
            'markers' => $markers,
            'region_values' => $regionValues,
            'counts' => [
                'with_data' => $withData,
                'without_data' => $withoutData,
            ],
        ];
    }

    private function countryReference(array $filters)
    {
        return WorldBankCountry::query()
            ->where('is_aggregate', false)
            ->whereNotNull('iso2_code')
            ->where('iso2_code', '!=', '')
            ->when($filters['continent'] !== '', fn (Builder $query) => $query->where('continent', $filters['continent']))
            ->when($filters['country'] !== '', fn (Builder $query) => $query->where('iso2_code', $filters['country']))
            ->orderBy('name')
            ->get(['name', 'iso2_code', 'continent', 'latitude', 'longitude']);
    }

    private function countries()
    {
        return WorldBankCountry::query()
            ->where('is_aggregate', false)
            ->whereNotNull('iso2_code')
            ->where('iso2_code', '!=', '')
            ->orderBy('name')
            ->get(['name', 'iso2_code', 'continent']);
    }

    private function continents()
    {
        return WorldBankCountry::query()
            ->where('is_aggregate', false)
            ->whereNotNull('continent')
            ->where('continent', '!=', '')
            ->distinct()
            ->orderBy('continent')
            ->pluck('continent');
    }

    private function durationLabel(int|float|null $seconds): string
    {
        $seconds = max(0, (int) $seconds);

        if ($seconds >= 3600) {
            $hours = intdiv($seconds, 3600);
            $minutes = intdiv($seconds % 3600, 60);

            return $hours . 'h ' . $minutes . 'm';
        }

        if ($seconds >= 60) {
            return intdiv($seconds, 60) . 'm ' . ($seconds % 60) . 's';
        }

        return $seconds . 's';
    }
}
