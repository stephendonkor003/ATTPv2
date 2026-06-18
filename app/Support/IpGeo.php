<?php

namespace App\Support;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IpGeo
{
    public static function countryForIp(?string $ip): ?string
    {
        return self::lookup($ip)['country_name'] ?? null;
    }

    /**
     * @return array{country_name?: string|null, country_code?: string|null, continent?: string|null, latitude?: float|null, longitude?: float|null, provider?: string|null}
     */
    public static function lookup(?string $ip, bool $forceRefresh = false): array
    {
        if (!self::isLookupAllowed($ip)) {
            return [];
        }

        $cacheKey = 'ip_geo_' . sha1((string) $ip);
        if (!$forceRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $result = self::lookupFresh((string) $ip);
        $ttl = self::hasLocation($result)
            ? now()->addDays(max(1, (int) config('services.ipgeo.cache_days', 14)))
            : now()->addMinutes(max(1, (int) config('services.ipgeo.empty_cache_minutes', 20)));

        Cache::put($cacheKey, $result, $ttl);

        return $result;
    }

    private static function isLookupAllowed(?string $ip): bool
    {
        if (!$ip || !config('services.ipgeo.enabled', true)) {
            return false;
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    private static function lookupFresh(string $ip): array
    {
        foreach (self::providers() as $provider) {
            $result = match ($provider) {
                'ipinfo' => self::lookupIpinfo($ip),
                'ipdata' => self::lookupIpdata($ip),
                'abstract' => self::lookupAbstract($ip),
                'ipwhois' => self::lookupIpwhois($ip),
                'ip_api' => self::lookupIpApiCom($ip),
                'ipapi' => self::lookupIpapiCo($ip),
                default => [],
            };

            if (self::hasLocation($result)) {
                return $result;
            }
        }

        return [];
    }

    private static function providers(): array
    {
        $providers = config('services.ipgeo.providers', ['ipinfo', 'ipdata', 'abstract', 'ipapi', 'ipwhois', 'ip_api']);
        if (is_string($providers)) {
            $providers = explode(',', $providers);
        }

        return array_values(array_unique(array_filter(array_map(
            fn ($provider) => strtolower(trim((string) $provider)),
            (array) $providers
        ))));
    }

    private static function lookupIpinfo(string $ip): array
    {
        $token = trim((string) config('services.ipgeo.ipinfo_token'));
        if ($token === '') {
            return [];
        }

        return self::request('ipinfo', "https://ipinfo.io/{$ip}/json", ['token' => $token], function (Response $response) {
            $loc = explode(',', (string) $response->json('loc'));

            return [
                'country_code' => $response->json('country'),
                'latitude' => $loc[0] ?? null,
                'longitude' => $loc[1] ?? null,
            ];
        });
    }

    private static function lookupIpdata(string $ip): array
    {
        $key = trim((string) config('services.ipgeo.ipdata_key'));
        if ($key === '') {
            return [];
        }

        return self::request('ipdata', "https://api.ipdata.co/{$ip}", ['api-key' => $key], fn (Response $response) => [
            'country_name' => $response->json('country_name'),
            'country_code' => $response->json('country_code'),
            'continent' => $response->json('continent_name'),
            'latitude' => $response->json('latitude'),
            'longitude' => $response->json('longitude'),
        ]);
    }

    private static function lookupAbstract(string $ip): array
    {
        $key = trim((string) config('services.ipgeo.abstract_key'));
        if ($key === '') {
            return [];
        }

        return self::request('abstract', 'https://ipgeolocation.abstractapi.com/v1/', [
            'api_key' => $key,
            'ip_address' => $ip,
        ], fn (Response $response) => [
            'country_name' => $response->json('country'),
            'country_code' => $response->json('country_code'),
            'continent' => $response->json('continent'),
            'latitude' => $response->json('latitude'),
            'longitude' => $response->json('longitude'),
        ]);
    }

    private static function lookupIpapiCo(string $ip): array
    {
        $baseUrl = rtrim((string) config('services.ipgeo.base_url', 'https://ipapi.co'), '/');

        return self::request('ipapi', "{$baseUrl}/{$ip}/json/", [], fn (Response $response) => [
            'country_name' => $response->json('country_name'),
            'country_code' => $response->json('country_code'),
            'continent' => $response->json('continent_code') ?: $response->json('continent'),
            'latitude' => $response->json('latitude'),
            'longitude' => $response->json('longitude'),
        ]);
    }

    private static function lookupIpwhois(string $ip): array
    {
        return self::request('ipwhois', "https://ipwho.is/{$ip}", [], function (Response $response) {
            if ($response->json('success') === false) {
                return [];
            }

            return [
                'country_name' => $response->json('country'),
                'country_code' => $response->json('country_code'),
                'continent' => $response->json('continent'),
                'latitude' => $response->json('latitude'),
                'longitude' => $response->json('longitude'),
            ];
        });
    }

    private static function lookupIpApiCom(string $ip): array
    {
        return self::request('ip_api', "http://ip-api.com/json/{$ip}", [
            'fields' => 'status,country,countryCode,lat,lon,continent',
        ], function (Response $response) {
            if ($response->json('status') !== 'success') {
                return [];
            }

            return [
                'country_name' => $response->json('country'),
                'country_code' => $response->json('countryCode'),
                'continent' => $response->json('continent'),
                'latitude' => $response->json('lat'),
                'longitude' => $response->json('lon'),
            ];
        });
    }

    private static function request(string $provider, string $url, array $query, callable $mapper): array
    {
        try {
            $response = Http::acceptJson()
                ->timeout(max(1, (int) config('services.ipgeo.timeout_seconds', 3)))
                ->get($url, $query);

            if (!$response->successful()) {
                return [];
            }

            return self::normalize((array) $mapper($response), $provider);
        } catch (\Throwable) {
            return [];
        }
    }

    private static function normalize(array $data, string $provider): array
    {
        $countryCode = strtoupper(trim((string) ($data['country_code'] ?? '')));
        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;

        return array_filter([
            'country_name' => trim((string) ($data['country_name'] ?? '')),
            'country_code' => preg_match('/^[A-Z]{2}$/', $countryCode) ? $countryCode : null,
            'continent' => trim((string) ($data['continent'] ?? '')),
            'latitude' => is_numeric($latitude) ? (float) $latitude : null,
            'longitude' => is_numeric($longitude) ? (float) $longitude : null,
            'provider' => $provider,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private static function hasLocation(array $result): bool
    {
        return filled($result['country_code'] ?? null) || filled($result['country_name'] ?? null);
    }
}
