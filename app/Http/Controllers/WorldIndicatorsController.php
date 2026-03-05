<?php

namespace App\Http\Controllers;

use App\Models\WorldBankCountry;
use App\Models\WorldBankIndicator;
use App\Models\WorldBankTopic;
use App\Models\WorldIndicatorSetting;
use App\Services\WorldIndicatorDataService;
use App\Services\WorldShapeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorldIndicatorsController extends Controller
{
    protected WorldShapeService $worldShapeService;

    protected WorldIndicatorDataService $worldIndicatorDataService;

    public function __construct(
        WorldShapeService $worldShapeService,
        WorldIndicatorDataService $worldIndicatorDataService
    ) {
        $this->worldShapeService = $worldShapeService;
        $this->worldIndicatorDataService = $worldIndicatorDataService;
    }

    public function index()
    {
        $settings = $this->resolveSettings();

        if (!$settings->is_public_enabled) {
            abort(404);
        }

        $availableRegions = $this->worldShapeService->getAvailableRegions();
        $enabledRegions = collect($settings->enabled_regions ?? [])
            ->filter(function (string $region) use ($availableRegions): bool {
                return in_array($region, $availableRegions, true);
            })
            ->values()
            ->all();

        if (empty($enabledRegions)) {
            $enabledRegions = $availableRegions;
        }

        $defaultRegion = in_array((string) $settings->default_region, $enabledRegions, true)
            ? (string) $settings->default_region
            : ($enabledRegions[0] ?? null);

        $shapeFilesByRegion = $this->worldShapeService->getShapeFilesByRegion($enabledRegions);
        $countriesByRegion = $this->worldShapeService->getCountriesByRegion($shapeFilesByRegion);
        $regionLabels = $this->worldShapeService->getRegionLabels($enabledRegions);

        $enabledSources = collect([
            [
                'key' => 'imf',
                'label' => 'IMF Portal',
                'enabled' => $settings->imf_source_enabled,
                'endpoint' => $settings->imf_api_base_url,
            ],
            [
                'key' => 'world_bank',
                'label' => 'World Bank Portal',
                'enabled' => $settings->world_bank_source_enabled,
                'endpoint' => $settings->world_bank_api_base_url,
            ],
        ])->filter(function (array $source): bool {
            return (bool) $source['enabled'];
        })->values()->all();

        $summary = [
            'regions' => count($enabledRegions),
            'countries' => collect($countriesByRegion)->flatten()->unique()->count(),
            'shape_files' => collect($shapeFilesByRegion)->flatten()->count(),
        ];

        $worldBankSummary = [
            'topics' => WorldBankTopic::query()->count(),
            'indicators' => WorldBankIndicator::query()->count(),
            'countries' => WorldBankCountry::query()->where('is_aggregate', false)->count(),
        ];

        return view('world-indicators-performance', compact(
            'settings',
            'enabledRegions',
            'defaultRegion',
            'shapeFilesByRegion',
            'countriesByRegion',
            'regionLabels',
            'enabledSources',
            'summary',
            'worldBankSummary'
        ));
    }

    public function countryMetrics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['required', 'string', 'max:140'],
        ]);

        $settings = $this->resolveSettings();

        if (!$settings->is_public_enabled) {
            abort(404);
        }

        $snapshot = $this->worldIndicatorDataService
            ->buildCountrySnapshot((string) $validated['country'], $settings);

        return response()->json($snapshot);
    }

    public function topics(): JsonResponse
    {
        $settings = $this->resolveSettings();
        if (!$settings->is_public_enabled) {
            abort(404);
        }

        return response()->json([
            'data' => $this->worldIndicatorDataService->getWorldBankTopics(),
        ]);
    }

    public function indicators(Request $request): JsonResponse
    {
        $settings = $this->resolveSettings();
        if (!$settings->is_public_enabled) {
            abort(404);
        }

        $validated = $request->validate([
            'topic_id' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        return response()->json([
            'data' => $this->worldIndicatorDataService->getWorldBankIndicators(
                isset($validated['topic_id']) ? (int) $validated['topic_id'] : null,
                $validated['search'] ?? null,
                (int) ($validated['limit'] ?? 500)
            ),
        ]);
    }

    public function countries(Request $request): JsonResponse
    {
        $settings = $this->resolveSettings();
        if (!$settings->is_public_enabled) {
            abort(404);
        }

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json([
            'data' => $this->worldIndicatorDataService->getWorldBankCountries($validated['search'] ?? null),
        ]);
    }

    public function continents(): JsonResponse
    {
        $settings = $this->resolveSettings();
        if (!$settings->is_public_enabled) {
            abort(404);
        }

        return response()->json([
            'data' => $this->worldIndicatorDataService->getWorldBankContinents(),
        ]);
    }

    public function compare(Request $request): JsonResponse
    {
        $settings = $this->resolveSettings();
        if (!$settings->is_public_enabled) {
            abort(404);
        }

        $currentYear = (int) now()->year;
        $validated = $request->validate([
            'indicator_id' => ['required', 'string', 'max:80'],
            'compare_mode' => ['required', Rule::in(['country', 'continent'])],
            'countries' => ['required_if:compare_mode,country', 'array', 'min:1'],
            'countries.*' => ['string', 'size:2'],
            'continents' => ['required_if:compare_mode,continent', 'array', 'min:2'],
            'continents.*' => ['string', 'max:80'],
            'year_from' => ['required', 'integer', 'min:1960', 'max:' . $currentYear],
            'year_to' => ['required', 'integer', 'min:1960', 'max:' . $currentYear],
            'aggregation' => ['nullable', Rule::in(['avg', 'sum'])],
        ]);

        $payload = $this->worldIndicatorDataService->compareWorldBankData(
            (string) $validated['indicator_id'],
            (string) $validated['compare_mode'],
            $validated['countries'] ?? [],
            $validated['continents'] ?? [],
            (int) $validated['year_from'],
            (int) $validated['year_to'],
            (string) ($validated['aggregation'] ?? 'avg')
        );

        return response()->json($payload);
    }

    private function resolveSettings(): WorldIndicatorSetting
    {
        return WorldIndicatorSetting::query()->firstOrCreate([], [
            'page_title' => 'World Indicators / Performance',
            'page_intro' => 'Compare global indicators by region and country. IMF and World Bank endpoint integration is managed from back office settings.',
            'is_public_enabled' => true,
            'imf_source_enabled' => true,
            'world_bank_source_enabled' => true,
        ]);
    }
}
