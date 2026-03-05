<?php

namespace App\Services;

use App\Models\AuMemberState;
use App\Models\WorldBankCountry;
use App\Models\WorldBankIndicator;
use App\Models\WorldBankIndicatorObservation;
use App\Models\WorldBankTopic;
use App\Models\WorldIndicatorSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WorldIndicatorDataService
{
    private const DEFAULT_WORLD_BANK_SNAPSHOT_INDICATORS = [
        'SP.POP.TOTL',
        'NY.GDP.MKTP.CD',
        'SL.UEM.TOTL.ZS',
    ];

    public function __construct(protected WorldBankObservationSyncService $worldBankObservationSyncService)
    {
    }

    public function buildCountrySnapshot(string $country, WorldIndicatorSetting $settings): array
    {
        $cleanCountry = trim($country);
        $seed = (int) sprintf('%u', crc32(Str::lower($cleanCountry)));

        $sources = [];
        $isPlaceholder = false;

        if ($settings->imf_source_enabled) {
            $sources[] = [
                'key' => 'imf',
                'label' => 'IMF Portal',
                'note' => $settings->imf_api_base_url
                    ? 'Endpoint configured in back office. Live mapping will be applied once indicator API keys are finalized.'
                    : 'Endpoint is not configured yet. Placeholder preview values are shown.',
                'metrics' => $this->buildImfMetrics($seed),
            ];
            $isPlaceholder = true;
        }

        if ($settings->world_bank_source_enabled) {
            $worldBankSource = $this->buildWorldBankSource($cleanCountry, $settings, $seed);
            $sources[] = $worldBankSource['source'];
            $isPlaceholder = $isPlaceholder || $worldBankSource['placeholder'];
        }

        return [
            'country' => $cleanCountry,
            'placeholder' => $isPlaceholder,
            'generated_at' => now()->toIso8601String(),
            'sources' => $sources,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWorldBankTopics(): array
    {
        return WorldBankTopic::query()
            ->withCount('indicators')
            ->orderBy('wb_topic_id')
            ->get(['id', 'wb_topic_id', 'name'])
            ->map(function (WorldBankTopic $topic): array {
                return [
                    'id' => $topic->wb_topic_id,
                    'name' => $topic->name,
                    'indicator_count' => (int) $topic->indicators_count,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWorldBankIndicators(?int $topicId = null, ?string $search = null, int $limit = 500): array
    {
        $query = WorldBankIndicator::query()
            ->with('topics:id,wb_topic_id,name')
            ->orderBy('name')
            ->limit(max(1, min($limit, 2000)));

        if ($topicId !== null && $topicId > 0) {
            $query->whereHas('topics', function ($topicQuery) use ($topicId): void {
                $topicQuery->where('wb_topic_id', $topicId);
            });
        }

        $searchTerm = trim((string) $search);
        if ($searchTerm !== '') {
            $query->where(function ($inner) use ($searchTerm): void {
                $inner->where('name', 'like', '%' . $searchTerm . '%')
                    ->orWhere('wb_indicator_id', 'like', '%' . $searchTerm . '%');
            });
        }

        return $query
            ->get(['id', 'wb_indicator_id', 'name', 'unit'])
            ->map(function (WorldBankIndicator $indicator): array {
                return [
                    'id' => $indicator->wb_indicator_id,
                    'name' => $indicator->name,
                    'unit' => $indicator->unit,
                    'topics' => $indicator->topics->map(function (WorldBankTopic $topic): array {
                        return [
                            'id' => $topic->wb_topic_id,
                            'name' => $topic->name,
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWorldBankCountries(?string $search = null): array
    {
        $query = WorldBankCountry::query()
            ->where('is_aggregate', false)
            ->whereNotNull('iso2_code')
            ->orderBy('name');

        $searchTerm = trim((string) $search);
        if ($searchTerm !== '') {
            $query->where('name', 'like', '%' . $searchTerm . '%');
        }

        return $query
            ->limit(400)
            ->get(['wb_country_id', 'iso2_code', 'name', 'continent', 'region'])
            ->map(function (WorldBankCountry $country): array {
                return [
                    'iso2' => strtoupper((string) $country->iso2_code),
                    'iso3' => strtoupper((string) $country->wb_country_id),
                    'name' => $country->name,
                    'continent' => $country->continent,
                    'region' => $country->region,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getWorldBankContinents(): array
    {
        return WorldBankCountry::query()
            ->where('is_aggregate', false)
            ->whereNotNull('continent')
            ->where('continent', '!=', '')
            ->distinct()
            ->orderBy('continent')
            ->pluck('continent')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $countries
     * @param  array<int, string>  $continents
     * @return array<string, mixed>
     */
    public function compareWorldBankData(
        string $wbIndicatorId,
        string $compareMode,
        array $countries,
        array $continents,
        int $yearFrom,
        int $yearTo,
        string $aggregation = 'avg'
    ): array {
        $mode = in_array($compareMode, ['country', 'continent'], true) ? $compareMode : 'country';
        [$yearFrom, $yearTo] = $this->normalizeYearRange($yearFrom, $yearTo);

        $indicator = WorldBankIndicator::query()
            ->with('topics:id,wb_topic_id,name')
            ->where('wb_indicator_id', $wbIndicatorId)
            ->first();

        if (!$indicator) {
            throw new RuntimeException("Unknown World Bank indicator: {$wbIndicatorId}");
        }

        $this->worldBankObservationSyncService->ensureIndicatorRangeAvailable($indicator->wb_indicator_id, $yearFrom, $yearTo);

        return $mode === 'continent'
            ? $this->buildContinentComparison($indicator, $continents, $yearFrom, $yearTo, $aggregation)
            : $this->buildCountryComparison($indicator, $countries, $yearFrom, $yearTo);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWorldBankSource(string $country, WorldIndicatorSetting $settings, int $seed): array
    {
        $countryIso2 = $this->resolveCountryIso2ByName($country);
        $yearTo = (int) now()->year;
        $yearFrom = max(1960, $yearTo - 30);

        if ($countryIso2 === null) {
            return [
                'placeholder' => true,
                'source' => [
                    'key' => 'world_bank',
                    'label' => 'World Bank Portal',
                    'note' => 'Country mapping is not available yet. Placeholder preview values are shown.',
                    'metrics' => $this->buildWorldBankMetrics($seed),
                ],
            ];
        }

        $snapshotIndicators = WorldBankIndicator::query()
            ->whereIn('wb_indicator_id', self::DEFAULT_WORLD_BANK_SNAPSHOT_INDICATORS)
            ->get(['id', 'wb_indicator_id', 'name', 'unit']);

        if ($snapshotIndicators->isEmpty()) {
            return [
                'placeholder' => true,
                'source' => [
                    'key' => 'world_bank',
                    'label' => 'World Bank Portal',
                    'note' => 'Local World Bank catalog has not been synced yet. Placeholder preview values are shown.',
                    'metrics' => $this->buildWorldBankMetrics($seed),
                ],
            ];
        }

        $metrics = [];

        foreach ($snapshotIndicators as $indicator) {
            $latestObservation = WorldBankIndicatorObservation::query()
                ->where('world_bank_indicator_id', $indicator->id)
                ->where('country_iso2', $countryIso2)
                ->whereNotNull('value')
                ->orderByDesc('year')
                ->first();

            if (!$latestObservation) {
                $this->worldBankObservationSyncService->syncIndicator($indicator->wb_indicator_id, $yearFrom, $yearTo);
                $latestObservation = WorldBankIndicatorObservation::query()
                    ->where('world_bank_indicator_id', $indicator->id)
                    ->where('country_iso2', $countryIso2)
                    ->whereNotNull('value')
                    ->orderByDesc('year')
                    ->first();
            }

            if ($latestObservation) {
                $formattedValue = $this->formatWorldBankValue((float) $latestObservation->value, $indicator->unit);
                $metrics[] = [
                    'label' => $indicator->name,
                    'value' => "{$formattedValue} ({$latestObservation->year})",
                ];
            }
        }

        if (empty($metrics)) {
            return [
                'placeholder' => true,
                'source' => [
                    'key' => 'world_bank',
                    'label' => 'World Bank Portal',
                    'note' => 'No live values are cached yet for this country. Placeholder preview values are shown.',
                    'metrics' => $this->buildWorldBankMetrics($seed),
                ],
            ];
        }

        return [
            'placeholder' => false,
            'source' => [
                'key' => 'world_bank',
                'label' => 'World Bank Portal',
                'note' => $settings->world_bank_api_base_url
                    ? 'Live values loaded from the World Bank API and cached in the platform.'
                    : 'Live values loaded from cached World Bank API sync data.',
                'metrics' => $metrics,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $countries
     * @return array<string, mixed>
     */
    private function buildCountryComparison(WorldBankIndicator $indicator, array $countries, int $yearFrom, int $yearTo): array
    {
        $selectedCountries = collect($countries)
            ->map(function (string $iso2): string {
                return strtoupper(trim($iso2));
            })
            ->filter(function (string $iso2): bool {
                return strlen($iso2) === 2;
            })
            ->unique()
            ->values()
            ->all();

        if (empty($selectedCountries)) {
            $selectedCountries = WorldBankCountry::query()
                ->where('is_aggregate', false)
                ->whereNotNull('iso2_code')
                ->orderBy('name')
                ->limit(2)
                ->pluck('iso2_code')
                ->map(function (string $iso2): string {
                    return strtoupper($iso2);
                })
                ->all();
        }

        $rows = DB::table('world_bank_indicator_observations as o')
            ->join('world_bank_countries as c', 'c.iso2_code', '=', 'o.country_iso2')
            ->where('o.world_bank_indicator_id', $indicator->id)
            ->whereBetween('o.year', [$yearFrom, $yearTo])
            ->whereIn('o.country_iso2', $selectedCountries)
            ->where('c.is_aggregate', false)
            ->whereNotNull('o.value')
            ->orderBy('o.year')
            ->get([
                'o.country_iso2',
                'c.name as country_name',
                'o.year',
                'o.value',
            ]);

        $yearAxis = range($yearFrom, $yearTo);
        $seriesMap = [];

        foreach ($rows as $row) {
            $iso2 = strtoupper((string) $row->country_iso2);
            if (!isset($seriesMap[$iso2])) {
                $seriesMap[$iso2] = [
                    'key' => $iso2,
                    'label' => (string) $row->country_name,
                    'points_map' => [],
                ];
            }
            $seriesMap[$iso2]['points_map'][(int) $row->year] = (float) $row->value;
        }

        $series = [];
        foreach ($seriesMap as $item) {
            $points = [];
            foreach ($yearAxis as $year) {
                $points[] = [
                    'year' => $year,
                    'value' => $item['points_map'][$year] ?? null,
                ];
            }
            $series[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'points' => $points,
            ];
        }

        return $this->buildComparisonPayload(
            $indicator,
            'country',
            $yearFrom,
            $yearTo,
            'avg',
            $yearAxis,
            $series
        );
    }

    /**
     * @param  array<int, string>  $continents
     * @return array<string, mixed>
     */
    private function buildContinentComparison(
        WorldBankIndicator $indicator,
        array $continents,
        int $yearFrom,
        int $yearTo,
        string $aggregation
    ): array {
        $aggregationMode = strtolower($aggregation) === 'sum' ? 'sum' : 'avg';

        $selectedContinents = collect($continents)
            ->map(function (string $continent): string {
                return trim($continent);
            })
            ->filter(function (string $continent): bool {
                return $continent !== '';
            })
            ->unique()
            ->values()
            ->all();

        if (empty($selectedContinents)) {
            $selectedContinents = $this->getWorldBankContinents();
        }

        $aggregateSql = $aggregationMode === 'sum' ? 'SUM(o.value)' : 'AVG(o.value)';

        $rows = DB::table('world_bank_indicator_observations as o')
            ->join('world_bank_countries as c', 'c.iso2_code', '=', 'o.country_iso2')
            ->where('o.world_bank_indicator_id', $indicator->id)
            ->whereBetween('o.year', [$yearFrom, $yearTo])
            ->whereIn('c.continent', $selectedContinents)
            ->where('c.is_aggregate', false)
            ->whereNotNull('o.value')
            ->groupBy('c.continent', 'o.year')
            ->orderBy('o.year')
            ->orderBy('c.continent')
            ->selectRaw("c.continent as continent, o.year as year, {$aggregateSql} as value")
            ->get();

        $yearAxis = range($yearFrom, $yearTo);
        $seriesMap = [];

        foreach ($rows as $row) {
            $continent = (string) $row->continent;
            if (!isset($seriesMap[$continent])) {
                $seriesMap[$continent] = [
                    'key' => $continent,
                    'label' => $continent,
                    'points_map' => [],
                ];
            }
            $seriesMap[$continent]['points_map'][(int) $row->year] = $row->value !== null
                ? (float) $row->value
                : null;
        }

        $series = [];
        foreach ($seriesMap as $item) {
            $points = [];
            foreach ($yearAxis as $year) {
                $points[] = [
                    'year' => $year,
                    'value' => $item['points_map'][$year] ?? null,
                ];
            }
            $series[] = [
                'key' => $item['key'],
                'label' => $item['label'],
                'points' => $points,
            ];
        }

        return $this->buildComparisonPayload(
            $indicator,
            'continent',
            $yearFrom,
            $yearTo,
            $aggregationMode,
            $yearAxis,
            $series
        );
    }

    /**
     * @param  array<int, int>  $years
     * @param  array<int, array<string, mixed>>  $series
     * @return array<string, mixed>
     */
    private function buildComparisonPayload(
        WorldBankIndicator $indicator,
        string $mode,
        int $yearFrom,
        int $yearTo,
        string $aggregation,
        array $years,
        array $series
    ): array {
        return [
            'indicator' => [
                'id' => $indicator->wb_indicator_id,
                'name' => $indicator->name,
                'unit' => $indicator->unit,
                'topics' => $indicator->topics->pluck('name')->values()->all(),
            ],
            'compare_mode' => $mode,
            'aggregation' => $aggregation,
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
            'years' => $years,
            'series' => $series,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{0:int,1:int}
     */
    private function normalizeYearRange(int $yearFrom, int $yearTo): array
    {
        if ($yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        $currentYear = (int) now()->year;
        $yearFrom = max(1960, min($yearFrom, $currentYear));
        $yearTo = max(1960, min($yearTo, $currentYear));

        return [$yearFrom, $yearTo];
    }

    private function resolveCountryIso2ByName(string $countryName): ?string
    {
        $normalizedCountryName = Str::lower(trim($countryName));
        if ($normalizedCountryName === '') {
            return null;
        }

        $direct = WorldBankCountry::query()
            ->where('is_aggregate', false)
            ->whereRaw('LOWER(name) = ?', [$normalizedCountryName])
            ->value('iso2_code');

        if (is_string($direct) && strlen(trim($direct)) === 2) {
            return strtoupper(trim($direct));
        }

        $aliases = [
            'democratic republic of the congo' => 'Congo, Dem. Rep.',
            'cote d ivoire' => "Cote d'Ivoire",
            'côte d\'ivoire' => "Cote d'Ivoire",
            'cape verde' => 'Cabo Verde',
            'swaziland' => 'Eswatini',
            'sao tome and principe' => 'Sao Tome and Principe',
            'gambia' => 'Gambia, The',
            'congo' => 'Congo, Rep.',
        ];

        $aliasName = $aliases[$normalizedCountryName] ?? null;
        if ($aliasName) {
            $aliasIso2 = WorldBankCountry::query()
                ->where('is_aggregate', false)
                ->where('name', $aliasName)
                ->value('iso2_code');

            if (is_string($aliasIso2) && strlen(trim($aliasIso2)) === 2) {
                return strtoupper(trim($aliasIso2));
            }
        }

        $memberStateIso2 = AuMemberState::query()
            ->whereRaw('LOWER(name) = ?', [$normalizedCountryName])
            ->value('code_alpha2');

        if (is_string($memberStateIso2) && strlen(trim($memberStateIso2)) === 2) {
            return strtoupper(trim($memberStateIso2));
        }

        $fallback = WorldBankCountry::query()
            ->where('is_aggregate', false)
            ->where('name', 'like', '%' . trim($countryName) . '%')
            ->value('iso2_code');

        return (is_string($fallback) && strlen(trim($fallback)) === 2)
            ? strtoupper(trim($fallback))
            : null;
    }

    private function formatWorldBankValue(float $value, ?string $unit): string
    {
        $unitLabel = trim((string) $unit);

        if ($unitLabel !== '') {
            if (str_contains(strtolower($unitLabel), 'percent') || str_contains($unitLabel, '%')) {
                return number_format($value, 2) . '%';
            }
        }

        if (abs($value) >= 1_000_000_000) {
            return number_format($value / 1_000_000_000, 2) . 'B';
        }

        if (abs($value) >= 1_000_000) {
            return number_format($value / 1_000_000, 2) . 'M';
        }

        return number_format($value, 2);
    }

    private function buildImfMetrics(int $seed): array
    {
        return [
            [
                'label' => 'GDP Growth (annual %)',
                'value' => $this->formatPercent($this->scaledValue($seed, -1.8, 8.4, 5), true),
            ],
            [
                'label' => 'Inflation, consumer prices (%)',
                'value' => $this->formatPercent($this->scaledValue($seed, 1.3, 21.5, 11)),
            ],
            [
                'label' => 'Current Account Balance (% of GDP)',
                'value' => $this->formatPercent($this->scaledValue($seed, -15.0, 6.0, 17), true),
            ],
        ];
    }

    private function buildWorldBankMetrics(int $seed): array
    {
        return [
            [
                'label' => 'Population (millions)',
                'value' => number_format($this->scaledValue($seed, 0.35, 260.0, 3), 2),
            ],
            [
                'label' => 'Unemployment, total (% labor force)',
                'value' => $this->formatPercent($this->scaledValue($seed, 1.8, 28.0, 9)),
            ],
            [
                'label' => 'CO2 emissions (metric tons per capita)',
                'value' => number_format($this->scaledValue($seed, 0.09, 17.5, 23), 2),
            ],
        ];
    }

    private function scaledValue(int $seed, float $min, float $max, int $divisor): float
    {
        $safeDivisor = max(1, $divisor);
        $normalized = fmod($seed / $safeDivisor, 1000.0) / 1000.0;

        return $min + (($max - $min) * $normalized);
    }

    private function formatPercent(float $value, bool $signed = false): string
    {
        $formatted = number_format($value, 2);

        if ($signed && $value > 0) {
            $formatted = '+' . $formatted;
        }

        return $formatted . '%';
    }
}
