<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IpGeo
{
    public static function countryForIp(?string $ip): ?string
    {
        return self::lookup($ip)['country_name'] ?? null;
    }

    /**
     * @return array{country_name?: string|null, country_code?: string|null, latitude?: float|null, longitude?: float|null}
     */
    public static function lookup(?string $ip): array
    {
        if (!$ip) {
            return [];
        }

        // Avoid blocking requests and leaking IPs to third parties unless explicitly enabled.
        if (!config('services.ipgeo.enabled', false)) {
            return [];
        }

        // Don't call external services for private/reserved IP ranges.
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return [];
        }

        return Cache::remember("ip_geo_{$ip}", now()->addDays(7), function () use ($ip) {
            try {
                $baseUrl = rtrim((string) config('services.ipgeo.base_url', 'https://ipapi.co'), '/');
                $timeout = (int) config('services.ipgeo.timeout_seconds', 2);

                $response = Http::timeout($timeout)->get("{$baseUrl}/{$ip}/json/");
                if (!$response->successful()) {
                    return [];
                }

                return [
                    'country_name' => $response->json('country_name'),
                    'country_code' => $response->json('country_code'),
                    'latitude' => $response->json('latitude'),
                    'longitude' => $response->json('longitude'),
                ];
            } catch (\Throwable $e) {
                return [];
            }
        });
    }
}
