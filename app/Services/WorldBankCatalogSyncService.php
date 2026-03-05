<?php

namespace App\Services;

use App\Models\WorldBankCountry;
use App\Models\WorldBankIndicator;
use App\Models\WorldBankTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WorldBankCatalogSyncService
{
    public function __construct(protected WorldBankApiService $worldBankApiService)
    {
    }

    /**
     * @return array<string, int>
     */
    public function syncCatalog(): array
    {
        $now = now();

        $topicsPayload = $this->worldBankApiService->getTopics();
        $topicRows = $this->mapTopics($topicsPayload, $now);
        $this->upsertRows(
            'world_bank_topics',
            $topicRows,
            ['wb_topic_id'],
            ['name', 'source_note', 'metadata', 'updated_at']
        );

        $topicIdMap = WorldBankTopic::query()->pluck('id', 'wb_topic_id')->all();

        $indicatorsPayload = $this->worldBankApiService->getIndicators();
        $indicatorTopicMap = $this->extractIndicatorTopics($indicatorsPayload);
        $indicatorRows = $this->mapIndicators($indicatorsPayload, $now);

        foreach ($topicsPayload as $topicPayload) {
            $topicId = (int) data_get($topicPayload, 'id', 0);
            if ($topicId <= 0) {
                continue;
            }

            $topicIndicators = $this->worldBankApiService->getIndicatorsByTopic($topicId);
            foreach ($topicIndicators as $topicIndicator) {
                $indicatorId = trim((string) data_get($topicIndicator, 'id', ''));
                if ($indicatorId === '') {
                    continue;
                }
                if (!isset($indicatorTopicMap[$indicatorId])) {
                    $indicatorTopicMap[$indicatorId] = [];
                }
                $indicatorTopicMap[$indicatorId][] = $topicId;
            }
        }

        $this->upsertRows(
            'world_bank_indicators',
            $indicatorRows,
            ['wb_indicator_id'],
            ['name', 'unit', 'source_note', 'source_organization', 'source_id', 'source_name', 'metadata', 'updated_at']
        );

        $indicatorIdMap = WorldBankIndicator::query()->pluck('id', 'wb_indicator_id')->all();
        $this->syncIndicatorTopicPivot($indicatorTopicMap, $indicatorIdMap, $topicIdMap);

        $countriesPayload = $this->worldBankApiService->getCountries();
        $countryRows = $this->mapCountries($countriesPayload, $now);
        $this->upsertRows(
            'world_bank_countries',
            $countryRows,
            ['wb_country_id'],
            [
                'iso2_code',
                'name',
                'region',
                'admin_region',
                'income_level',
                'lending_type',
                'capital_city',
                'longitude',
                'latitude',
                'continent',
                'is_aggregate',
                'updated_at',
            ]
        );

        return [
            'topics' => count($topicRows),
            'indicators' => count($indicatorRows),
            'countries' => count($countryRows),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $topicsPayload
     * @return array<int, array<string, mixed>>
     */
    private function mapTopics(array $topicsPayload, $now): array
    {
        $rows = [];

        foreach ($topicsPayload as $topic) {
            $wbTopicId = (int) data_get($topic, 'id', 0);
            $name = trim((string) data_get($topic, 'value', ''));

            if ($wbTopicId <= 0 || $name === '') {
                continue;
            }

            $rows[] = [
                'id' => (string) Str::uuid(),
                'wb_topic_id' => $wbTopicId,
                'name' => $name,
                'source_note' => $this->nullableString(data_get($topic, 'sourceNote')),
                'metadata' => $this->encodeJson([
                    'raw_id' => data_get($topic, 'id'),
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $indicatorsPayload
     * @return array<int, array<string, mixed>>
     */
    private function mapIndicators(array $indicatorsPayload, $now): array
    {
        $rows = [];

        foreach ($indicatorsPayload as $indicator) {
            $wbIndicatorId = trim((string) data_get($indicator, 'id', ''));
            $name = trim((string) data_get($indicator, 'name', ''));

            if ($wbIndicatorId === '' || $name === '') {
                continue;
            }

            $rows[] = [
                'id' => (string) Str::uuid(),
                'wb_indicator_id' => $wbIndicatorId,
                'name' => $name,
                'unit' => $this->nullableString(data_get($indicator, 'unit')),
                'source_note' => $this->nullableString(data_get($indicator, 'sourceNote')),
                'source_organization' => $this->nullableString(data_get($indicator, 'sourceOrganization')),
                'source_id' => $this->nullableString(data_get($indicator, 'source.id')),
                'source_name' => $this->nullableString(data_get($indicator, 'source.value')),
                'metadata' => $this->encodeJson([
                    'decimal' => data_get($indicator, 'decimal'),
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, mixed>>  $indicatorsPayload
     * @return array<string, array<int, int>>
     */
    private function extractIndicatorTopics(array $indicatorsPayload): array
    {
        $map = [];

        foreach ($indicatorsPayload as $indicator) {
            $wbIndicatorId = trim((string) data_get($indicator, 'id', ''));
            if ($wbIndicatorId === '') {
                continue;
            }

            $topics = data_get($indicator, 'topics', []);
            if (!is_array($topics)) {
                continue;
            }

            foreach ($topics as $topic) {
                $topicId = (int) data_get($topic, 'id', 0);
                if ($topicId <= 0) {
                    continue;
                }
                if (!isset($map[$wbIndicatorId])) {
                    $map[$wbIndicatorId] = [];
                }
                $map[$wbIndicatorId][] = $topicId;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $countriesPayload
     * @return array<int, array<string, mixed>>
     */
    private function mapCountries(array $countriesPayload, $now): array
    {
        $rows = [];

        foreach ($countriesPayload as $country) {
            $wbCountryId = strtoupper(trim((string) data_get($country, 'id', '')));
            $name = trim((string) data_get($country, 'name', ''));

            if ($wbCountryId === '' || $name === '') {
                continue;
            }

            $region = $this->nullableString(data_get($country, 'region.value'));
            $isAggregate = strtolower((string) data_get($country, 'region.id')) === 'agg' || $region === 'Aggregates';

            $rows[] = [
                'id' => (string) Str::uuid(),
                'wb_country_id' => $wbCountryId,
                'iso2_code' => $this->normalizeIso2(data_get($country, 'iso2Code')),
                'name' => $name,
                'region' => $region,
                'admin_region' => $this->nullableString(data_get($country, 'adminregion.value')),
                'income_level' => $this->nullableString(data_get($country, 'incomeLevel.value')),
                'lending_type' => $this->nullableString(data_get($country, 'lendingType.value')),
                'capital_city' => $this->nullableString(data_get($country, 'capitalCity')),
                'longitude' => $this->nullableString(data_get($country, 'longitude')),
                'latitude' => $this->nullableString(data_get($country, 'latitude')),
                'continent' => $isAggregate ? null : $this->resolveContinent($country),
                'is_aggregate' => $isAggregate,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, array<int, int>>  $indicatorTopicMap
     * @param  array<string, string>  $indicatorIdMap
     * @param  array<int, string>  $topicIdMap
     */
    private function syncIndicatorTopicPivot(array $indicatorTopicMap, array $indicatorIdMap, array $topicIdMap): void
    {
        DB::table('world_bank_indicator_topic')->delete();

        $pivotRows = [];
        foreach ($indicatorTopicMap as $wbIndicatorId => $topicIds) {
            $indicatorId = $indicatorIdMap[$wbIndicatorId] ?? null;
            if (!$indicatorId) {
                continue;
            }

            foreach (array_unique($topicIds) as $wbTopicId) {
                $topicId = $topicIdMap[(int) $wbTopicId] ?? null;
                if (!$topicId) {
                    continue;
                }

                $pivotRows[] = [
                    'world_bank_indicator_id' => $indicatorId,
                    'world_bank_topic_id' => $topicId,
                ];
            }
        }

        foreach (array_chunk($pivotRows, 2000) as $chunk) {
            DB::table('world_bank_indicator_topic')->insert($chunk);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $uniqueBy
     * @param  array<int, string>  $updateColumns
     */
    private function upsertRows(string $table, array $rows, array $uniqueBy, array $updateColumns): void
    {
        if (empty($rows)) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->upsert($chunk, $uniqueBy, $updateColumns);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }

    private function normalizeIso2(mixed $value): ?string
    {
        $iso2 = strtoupper(trim((string) $value));

        if (strlen($iso2) !== 2) {
            return null;
        }

        return $iso2;
    }

    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Failed to encode JSON payload for World Bank catalog sync.');
        }

        return $encoded;
    }

    private function resolveContinent(array $country): ?string
    {
        $iso2 = strtoupper(trim((string) data_get($country, 'iso2Code', '')));
        $regionId = strtoupper(trim((string) data_get($country, 'region.id', '')));
        $regionName = trim((string) data_get($country, 'region.value', ''));

        if ($regionName === '' || strcasecmp($regionName, 'Aggregates') === 0) {
            return null;
        }

        $oceaniaIso2 = [
            'AU', 'NZ', 'PG', 'FJ', 'SB', 'VU', 'WS', 'TO', 'KI', 'TV', 'NR', 'FM', 'MH', 'PW',
        ];

        if (in_array($iso2, $oceaniaIso2, true)) {
            return 'Oceania';
        }

        return match ($regionId) {
            'SSF', 'MEA' => 'Africa',
            'SAS', 'EAS' => 'Asia',
            'ECS' => 'Europe',
            'NAC' => 'North America',
            'LCN' => 'South America',
            default => $regionName,
        };
    }
}

