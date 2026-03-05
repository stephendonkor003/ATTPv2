<?php

namespace App\Http\Controllers;

use App\Models\WorldBankCountry;
use App\Models\WorldBankIndicator;
use App\Models\WorldBankIndicatorObservation;
use App\Models\WorldBankTopic;
use App\Models\WorldIndicatorSetting;
use App\Services\WorldBankCatalogSyncService;
use App\Services\WorldBankObservationSyncService;
use App\Services\WorldShapeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class WorldIndicatorSettingsController extends Controller
{
    protected WorldShapeService $worldShapeService;
    protected WorldBankCatalogSyncService $worldBankCatalogSyncService;
    protected WorldBankObservationSyncService $worldBankObservationSyncService;

    public function __construct(
        WorldShapeService $worldShapeService,
        WorldBankCatalogSyncService $worldBankCatalogSyncService,
        WorldBankObservationSyncService $worldBankObservationSyncService
    ) {
        $this->worldShapeService = $worldShapeService;
        $this->worldBankCatalogSyncService = $worldBankCatalogSyncService;
        $this->worldBankObservationSyncService = $worldBankObservationSyncService;

        $this->middleware('auth');
        $this->middleware('permission:world.indicators.manage');
    }

    public function edit(): View
    {
        $settings = $this->resolveSettings();
        $regions = $this->worldShapeService->getAvailableRegions();
        $regionLabels = $this->worldShapeService->getRegionLabels($regions);

        $selectedRegions = collect($settings->enabled_regions ?? [])
            ->filter(function (string $region) use ($regions): bool {
                return in_array($region, $regions, true);
            })
            ->values()
            ->all();

        if (empty($selectedRegions)) {
            $selectedRegions = $regions;
        }

        $worldBankStats = $this->buildWorldBankStats();

        return view('me.world-indicators.settings', compact(
            'settings',
            'regions',
            'regionLabels',
            'selectedRegions',
            'worldBankStats'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'page_title' => ['required', 'string', 'max:150'],
            'page_intro' => ['nullable', 'string', 'max:2000'],
            'enabled_regions' => ['nullable', 'array'],
            'enabled_regions.*' => ['string', 'max:100'],
            'default_region' => ['nullable', 'string', 'max:100'],
            'imf_api_base_url' => ['nullable', 'url', 'max:255'],
            'world_bank_api_base_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $settings = $this->resolveSettings();
        $availableRegions = $this->worldShapeService->getAvailableRegions();

        $enabledRegions = collect($validated['enabled_regions'] ?? [])
            ->filter(function (string $region) use ($availableRegions): bool {
                return in_array($region, $availableRegions, true);
            })
            ->values()
            ->all();

        if (empty($enabledRegions)) {
            $enabledRegions = $availableRegions;
        }

        $defaultRegion = (string) ($validated['default_region'] ?? '');
        if (!in_array($defaultRegion, $enabledRegions, true)) {
            $defaultRegion = $enabledRegions[0] ?? null;
        }

        $settings->fill([
            'page_title' => $validated['page_title'],
            'page_intro' => $validated['page_intro'] ?? null,
            'enabled_regions' => $enabledRegions,
            'default_region' => $defaultRegion,
            'imf_api_base_url' => $validated['imf_api_base_url'] ?? null,
            'world_bank_api_base_url' => $validated['world_bank_api_base_url'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $settings->is_public_enabled = $request->boolean('is_public_enabled');
        $settings->imf_source_enabled = $request->boolean('imf_source_enabled');
        $settings->world_bank_source_enabled = $request->boolean('world_bank_source_enabled');
        $settings->updated_by = auth()->id();
        if (empty($settings->created_by)) {
            $settings->created_by = auth()->id();
        }

        $settings->save();

        return redirect()
            ->route('budget.me.world-indicators.settings.edit')
            ->with('success', 'World Indicators settings were updated successfully.');
    }

    public function syncWorldBankCatalog(): RedirectResponse
    {
        try {
            $summary = $this->worldBankCatalogSyncService->syncCatalog();

            return redirect()
                ->to(route('budget.me.world-indicators.settings.edit') . '#world-bank-data')
                ->with(
                    'success',
                    "World Bank catalog synced: {$summary['topics']} topics, {$summary['indicators']} indicators, {$summary['countries']} countries."
                );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->to(route('budget.me.world-indicators.settings.edit') . '#world-bank-data')
                ->with('error', 'World Bank catalog sync failed: ' . $exception->getMessage());
        }
    }

    public function syncWorldBankData(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'years' => ['nullable', 'integer', 'min:1', 'max:70'],
            'indicator_ids' => ['nullable', 'string', 'max:5000'],
        ]);

        $years = (int) ($validated['years'] ?? 20);
        $yearTo = (int) now()->year;
        $yearFrom = max(1960, $yearTo - $years + 1);

        $indicatorIds = collect(explode(',', (string) ($validated['indicator_ids'] ?? '')))
            ->map(function (string $item): string {
                return trim($item);
            })
            ->filter(function (string $item): bool {
                return $item !== '';
            })
            ->unique()
            ->values()
            ->all();

        try {
            $results = !empty($indicatorIds)
                ? $this->worldBankObservationSyncService->syncIndicators($indicatorIds, $yearFrom, $yearTo)
                : $this->worldBankObservationSyncService->syncUsedIndicators(max($yearTo - 2, $yearFrom), $yearTo);

            $rowsProcessed = collect($results)->sum('rows_processed');
            $rowsWithValues = collect($results)->sum('rows_with_values');
            $indicatorCount = count($results);

            return redirect()
                ->to(route('budget.me.world-indicators.settings.edit') . '#world-bank-data')
                ->with(
                    'success',
                    "World Bank data sync complete for {$indicatorCount} indicator(s): {$rowsProcessed} rows ({$rowsWithValues} with values)."
                );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->to(route('budget.me.world-indicators.settings.edit') . '#world-bank-data')
                ->with('error', 'World Bank data sync failed: ' . $exception->getMessage());
        }
    }

    private function resolveSettings(): WorldIndicatorSetting
    {
        return WorldIndicatorSetting::query()->firstOrCreate([], [
            'page_title' => 'World Indicators / Performance',
            'page_intro' => 'Compare global indicators by region and country. IMF and World Bank endpoint integration is managed from back office settings.',
            'is_public_enabled' => true,
            'imf_source_enabled' => true,
            'world_bank_source_enabled' => true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWorldBankStats(): array
    {
        $catalogTimes = collect([
            WorldBankTopic::query()->max('updated_at'),
            WorldBankIndicator::query()->max('updated_at'),
            WorldBankCountry::query()->max('updated_at'),
        ])->filter();

        return [
            'topics' => WorldBankTopic::query()->count(),
            'indicators' => WorldBankIndicator::query()->count(),
            'countries' => WorldBankCountry::query()->where('is_aggregate', false)->count(),
            'observations' => WorldBankIndicatorObservation::query()->count(),
            'last_catalog_sync' => $catalogTimes->sortDesc()->first(),
            'last_data_sync' => WorldBankIndicatorObservation::query()->max('fetched_at'),
        ];
    }
}
