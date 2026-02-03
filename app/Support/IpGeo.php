<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IpGeo
{
    public static function countryForIp(?string $ip): ?string
    {
        if (!$ip) {
            return null;
        }

        // Avoid blocking requests and leaking IPs to third parties unless explicitly enabled.
        if (!config('services.ipgeo.enabled', false)) {
            return null;
        }

        // Don't call external services for private/reserved IP ranges.
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        return Cache::remember("ip_country_{$ip}", now()->addDays(7), function () use ($ip) {
            try {
                $baseUrl = rtrim((string) config('services.ipgeo.base_url', 'https://ipapi.co'), '/');
                $timeout = (int) config('services.ipgeo.timeout_seconds', 2);

                $response = Http::timeout($timeout)->get("{$baseUrl}/{$ip}/json/");
                if (!$response->successful()) {
                    return null;
                }
                return $response->json('country_name');
            } catch (\Throwable $e) {
                return null;
            }
        });
    }
}
