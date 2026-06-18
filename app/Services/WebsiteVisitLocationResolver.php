<?php

namespace App\Services;

use App\Models\WebsiteVisit;
use App\Models\WorldBankCountry;
use App\Support\IpGeo;

class WebsiteVisitLocationResolver
{
    public function resolve(?string $ip, ?string $headerCountry = null, bool $forceRefresh = false): array
    {
        if ($headerCountry) {
            return array_filter([
                ...$this->countryFromIso2($headerCountry),
                'location_lookup_provider' => 'request_header',
            ], fn ($value) => $value !== null && $value !== '');
        }

        $lookup = IpGeo::lookup($ip, $forceRefresh);
        $provider = $lookup['provider'] ?? null;
        $countryCode = strtoupper((string) ($lookup['country_code'] ?? ''));

        if ($countryCode !== '') {
            $country = $this->countryFromIso2($countryCode);

            return array_filter([
                ...$country,
                'latitude' => $lookup['latitude'] ?? $country['latitude'] ?? null,
                'longitude' => $lookup['longitude'] ?? $country['longitude'] ?? null,
                'location_lookup_provider' => $provider,
            ], fn ($value) => $value !== null && $value !== '');
        }

        $countryName = trim((string) ($lookup['country_name'] ?? ''));
        if ($countryName !== '') {
            $country = WorldBankCountry::query()
                ->where('is_aggregate', false)
                ->where(function ($query) use ($countryName) {
                    $query->where('name', $countryName)
                        ->orWhere('name', 'like', '%' . $countryName . '%');
                })
                ->first();

            if ($country) {
                return array_filter([
                    ...$this->countryPayload($country),
                    'latitude' => $lookup['latitude'] ?? $country->latitude,
                    'longitude' => $lookup['longitude'] ?? $country->longitude,
                    'location_lookup_provider' => $provider,
                ], fn ($value) => $value !== null && $value !== '');
            }

            return array_filter([
                'country_name' => $countryName,
                'continent' => $lookup['continent'] ?? null,
                'latitude' => $lookup['latitude'] ?? null,
                'longitude' => $lookup['longitude'] ?? null,
                'location_lookup_provider' => $provider,
            ], fn ($value) => $value !== null && $value !== '');
        }

        return [];
    }

    public function apply(WebsiteVisit $visit, array $geo, bool $overwrite = false): bool
    {
        $changed = false;

        foreach (['country_name', 'country_iso2', 'continent', 'latitude', 'longitude', 'location_lookup_provider'] as $field) {
            if (!array_key_exists($field, $geo) || blank($geo[$field])) {
                continue;
            }

            if (!$overwrite && !$this->isMissingField($visit, $field)) {
                continue;
            }

            if ($visit->{$field} !== $geo[$field]) {
                $visit->{$field} = $geo[$field];
                $changed = true;
            }
        }

        if ($changed && !$this->needsLocation($visit)) {
            $visit->location_lookup_failed_at = null;
        }

        return $changed;
    }

    public function needsLocation(WebsiteVisit $visit): bool
    {
        return $this->isMissingField($visit, 'country_iso2') || $this->isMissingField($visit, 'country_name');
    }

    private function isMissingField(WebsiteVisit $visit, string $field): bool
    {
        $value = $visit->{$field};

        if (blank($value)) {
            return true;
        }

        return in_array(strtolower(trim((string) $value)), ['unknown', 'not captured', 'n/a'], true);
    }

    private function countryFromIso2(string $iso2): array
    {
        $iso2 = strtoupper($iso2);
        $country = WorldBankCountry::query()
            ->where('is_aggregate', false)
            ->where('iso2_code', $iso2)
            ->first();

        if (!$country) {
            return ['country_iso2' => $iso2];
        }

        return $this->countryPayload($country);
    }

    private function countryPayload(WorldBankCountry $country): array
    {
        return [
            'country_name' => $country->name,
            'country_iso2' => strtoupper((string) $country->iso2_code),
            'continent' => $country->continent,
            'latitude' => is_numeric($country->latitude) ? (float) $country->latitude : null,
            'longitude' => is_numeric($country->longitude) ? (float) $country->longitude : null,
        ];
    }
}
