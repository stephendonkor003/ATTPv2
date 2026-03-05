<?php

namespace App\Services;

use App\Models\WorldBankCountry;
use App\Models\WorldBankIndicator;
use App\Models\WorldBankIndicatorObservation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorldBankObservationSyncService
{
    public function __construct(protected WorldBankApiService $worldBankApiService)
    {
    }

    /**
     * @return array<string, int|string>
     */
    public function syncIndicator(string $wbIndicatorId, int $yearFrom, int $yearTo): array
    {
        $indicator = WorldBankIndicator::query()
            ->where('wb_indicator_id', $wbIndicatorId)
            ->first();

        if (!$indicator) {
            throw new RuntimeException("World Bank indicator '{$wbIndicatorId}' is not available in local catalog.");
        }

        if ($yearFrom > $yearTo) {
            [$yearFrom, $yearTo] = [$yearTo, $yearFrom];
        }

        $yearFrom = max(1960, $yearFrom);
        $yearTo = min((int) now()->year, $yearTo);

        $payload = $this->worldBankApiService->getIndicatorData($wbIndicatorId, 'all', $yearFrom, $yearTo);
        $isoMappings = $this->countryMappings();
        $now = now();
        $rows = [];
        $nonNullCount = 0;

        foreach ($payload as $record) {
            $year = (int) data_get($record, 'date', 0);
            if ($year < $yearFrom || $year > $yearTo) {
                continue;
            }

            $countryIso2 = $this->resolveCountryIso2($record, $isoMappings);
            if ($countryIso2 === null) {
                continue;
            }

            $value = data_get($record, 'value');
            if (is_numeric($value)) {
                $value = (float) $value;
                $nonNullCount++;
            } else {
                $value = null;
            }

            $rows[] = [
                'world_bank_indicator_id' => $indicator->id,
                'country_iso2' => $countryIso2,
                'country_name' => $this->nullableString(data_get($record, 'country.value')),
                'year' => $year,
                'value' => $value,
                'decimal_places' => is_numeric(data_get($record, 'decimal')) ? (int) data_get($record, 'decimal') : null,
                'observation_status' => $this->nullableString(data_get($record, 'obs_status')),
                'fetched_at' => $now,
                'raw_payload' => $this->encodeJson($record),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('world_bank_indicator_observations')->upsert(
                $chunk,
                ['world_bank_indicator_id', 'country_iso2', 'year'],
                ['country_name', 'value', 'decimal_places', 'observation_status', 'fetched_at', 'raw_payload', 'updated_at']
            );
        }

        return [
            'indicator' => $wbIndicatorId,
            'rows_processed' => count($rows),
            'rows_with_values' => $nonNullCount,
            'year_from' => $yearFrom,
            'year_to' => $yearTo,
        ];
    }

    /**
     * @param  array<int, string>  $wbIndicatorIds
     * @return array<int, array<string, int|string>>
     */
    public function syncIndicators(array $wbIndicatorIds, int $yearFrom, int $yearTo): array
    {
        $results = [];

        foreach (array_values(array_unique(array_filter($wbIndicatorIds))) as $indicatorId) {
            $results[] = $this->syncIndicator((string) $indicatorId, $yearFrom, $yearTo);
        }

        return $results;
    }

    /**
     * @return array<int, array<string, int|string>>
     */
    public function syncUsedIndicators(int $yearFrom, int $yearTo): array
    {
        $indicatorIds = WorldBankIndicatorObservation::query()
            ->join('world_bank_indicators', 'world_bank_indicators.id', '=', 'world_bank_indicator_observations.world_bank_indicator_id')
            ->distinct()
            ->pluck('world_bank_indicators.wb_indicator_id')
            ->all();

        if (empty($indicatorIds)) {
            $indicatorIds = [
                'SP.POP.TOTL',
                'NY.GDP.MKTP.CD',
                'SL.UEM.TOTL.ZS',
                'EN.ATM.CO2E.PC',
            ];
        }

        return $this->syncIndicators($indicatorIds, $yearFrom, $yearTo);
    }

    public function ensureIndicatorRangeAvailable(string $wbIndicatorId, int $yearFrom, int $yearTo): void
    {
        $indicator = WorldBankIndicator::query()
            ->where('wb_indicator_id', $wbIndicatorId)
            ->first();

        if (!$indicator) {
            throw new RuntimeException("World Bank indicator '{$wbIndicatorId}' does not exist in local catalog.");
        }

        $existingRows = WorldBankIndicatorObservation::query()
            ->where('world_bank_indicator_id', $indicator->id)
            ->whereBetween('year', [$yearFrom, $yearTo])
            ->count();

        if ($existingRows === 0) {
            $this->syncIndicator($wbIndicatorId, $yearFrom, $yearTo);
            return;
        }

        $latestFetch = WorldBankIndicatorObservation::query()
            ->where('world_bank_indicator_id', $indicator->id)
            ->whereBetween('year', [max($yearTo - 2, $yearFrom), $yearTo])
            ->max('fetched_at');

        if (!$latestFetch || now()->diffInHours($latestFetch) >= 24) {
            $this->syncIndicator($wbIndicatorId, max($yearTo - 2, $yearFrom), $yearTo);
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function countryMappings(): array
    {
        $countries = WorldBankCountry::query()
            ->get(['wb_country_id', 'iso2_code']);

        $iso3ToIso2 = [];
        foreach ($countries as $country) {
            $iso3 = strtoupper((string) $country->wb_country_id);
            $iso2 = strtoupper((string) $country->iso2_code);
            if ($iso3 !== '' && strlen($iso2) === 2) {
                $iso3ToIso2[$iso3] = $iso2;
            }
        }

        return [
            'iso3_to_iso2' => $iso3ToIso2,
        ];
    }

    /**
     * @param  array<string, array<string, string>>  $mappings
     */
    private function resolveCountryIso2(array $record, array $mappings): ?string
    {
        $candidate = strtoupper(trim((string) data_get($record, 'country.id', '')));
        if (strlen($candidate) === 2) {
            return $candidate;
        }

        $iso3 = strtoupper(trim((string) data_get($record, 'countryiso3code', '')));
        if ($iso3 !== '' && isset($mappings['iso3_to_iso2'][$iso3])) {
            return $mappings['iso3_to_iso2'][$iso3];
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);
        return $stringValue === '' ? null : $stringValue;
    }

    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('Failed to encode World Bank observation payload.');
        }

        return $encoded;
    }
}

