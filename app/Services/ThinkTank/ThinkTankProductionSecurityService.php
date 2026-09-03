<?php

namespace App\Services\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use Illuminate\Support\Facades\Hash;
use Throwable;

class ThinkTankProductionSecurityService
{
    private static bool $validated = false;

    public function assertRuntimeConfiguration(): void
    {
        if (! app()->environment('production') || self::$validated) {
            return;
        }

        if ($this->problems() !== []) {
            throw new ThinkTankApiException(
                'PORTAL_SECURITY_PREFLIGHT_FAILED',
                'The Think Tank portal production security configuration is incomplete.',
                503,
            );
        }

        self::$validated = true;
    }

    /** @return list<string> */
    public function problems(): array
    {
        $problems = [];
        $frontend = (string) config('think_tank_portal.frontend_url');
        $frontendHost = mb_strtolower((string) parse_url($frontend, PHP_URL_HOST));
        $frontendPort = parse_url($frontend, PHP_URL_PORT);
        $statefulHost = $frontendHost.($frontendPort ? ':'.$frontendPort : '');

        if ((bool) config('app.debug')) {
            $problems[] = 'APP_DEBUG must be false.';
        }

        if (! $this->isPublicHttpsOrigin((string) config('app.url'))) {
            $problems[] = 'APP_URL must be a non-local HTTPS URL.';
        }

        if (! $this->isPublicHttpsOrigin($frontend)) {
            $problems[] = 'THINK_TANK_PORTAL_URL must be a non-local HTTPS origin.';
        }

        $origins = config('think_tank_portal.allowed_origins', []);

        if (! is_array($origins) || $origins === []) {
            $problems[] = 'At least one Think Tank portal origin is required.';
        } else {
            foreach ($origins as $origin) {
                if (! is_string($origin) || ! $this->isPublicHttpsOrigin($origin)) {
                    $problems[] = 'Every allowed portal origin must be a non-local HTTPS origin.';
                    break;
                }
            }

            if (! in_array(rtrim($frontend, '/'), array_map(
                static fn (mixed $origin): string => rtrim((string) $origin, '/'),
                $origins,
            ), true)) {
                $problems[] = 'The portal URL must be included in the exact CORS origin list.';
            }
        }

        $stateful = array_map(
            static fn (mixed $domain): string => mb_strtolower(trim((string) $domain)),
            (array) config('sanctum.stateful', []),
        );

        if ($statefulHost === '' || ! in_array($statefulHost, $stateful, true)) {
            $problems[] = 'SANCTUM_STATEFUL_DOMAINS must include the exact portal host and port.';
        }

        if (! (bool) config('session.encrypt')
            || ! (bool) config('session.secure')
            || ! (bool) config('session.http_only')
            || mb_strtolower((string) config('session.same_site')) !== 'strict'
            || filled(config('session.domain'))) {
            $problems[] = 'Sessions must be encrypted, Secure, HttpOnly, host-only, and SameSite=Strict.';
        }

        $proxies = config('think_tank_portal.trusted_proxies', []);

        if (! is_array($proxies)
            || $proxies === []
            || collect($proxies)->contains(fn (mixed $proxy): bool => ! is_string($proxy) || ! $this->isBoundedProxy($proxy))) {
            $problems[] = 'Exact trusted proxy addresses or CIDRs are required; wildcards are forbidden.';
        }

        if (! (bool) config('think_tank_portal.require_mfa', true)) {
            $problems[] = 'MFA must be enabled.';
        }

        try {
            if (Hash::needsRehash((string) config('think_tank_portal.dummy_password_hash'))) {
                $problems[] = 'The dummy password hash must match the configured hash algorithm and cost.';
            }
        } catch (Throwable) {
            $problems[] = 'The dummy password hash is invalid for the configured hasher.';
        }

        return array_values(array_unique($problems));
    }

    private function isPublicHttpsOrigin(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || mb_strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || filled($parts['user'] ?? null)
            || filled($parts['pass'] ?? null)
            || filled($parts['query'] ?? null)
            || filled($parts['fragment'] ?? null)
            || ! in_array((string) ($parts['path'] ?? ''), ['', '/'], true)) {
            return false;
        }

        $host = mb_strtolower((string) ($parts['host'] ?? ''));

        return $host !== ''
            && $host !== 'localhost'
            && $host !== '::1'
            && ! str_starts_with($host, '127.');
    }

    private function isBoundedProxy(string $proxy): bool
    {
        $proxy = trim($proxy);

        if ($proxy === '' || in_array($proxy, ['*', '**', '0.0.0.0', '::'], true)) {
            return false;
        }

        if (filter_var($proxy, FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (! str_contains($proxy, '/')) {
            return false;
        }

        [$address, $prefix] = array_pad(explode('/', $proxy, 2), 2, null);

        if (! is_string($prefix) || ! ctype_digit($prefix)) {
            return false;
        }

        $version = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            ? 4
            : (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? 6 : null);
        $maximum = $version === 4 ? 32 : ($version === 6 ? 128 : 0);
        $bits = (int) $prefix;

        return $maximum > 0 && $bits > 0 && $bits <= $maximum;
    }
}
