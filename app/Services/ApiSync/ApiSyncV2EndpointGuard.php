<?php

namespace App\Services\ApiSync;

use App\Exceptions\ApiSyncException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class ApiSyncV2EndpointGuard
{
    public function assertInvitationOrigins(
        string $centralOrigin,
        string $targetOrigin,
        string $confirmationUrl,
        string $invitationId,
    ): void {
        $configuredCentral = $this->origin((string) config('api_sync.v2.central.origin'));
        $configuredTarget = $this->origin((string) config('api_sync.v2.public_origin'));

        if (! hash_equals($configuredCentral, $this->origin($centralOrigin))) {
            throw new ApiSyncException('central_origin_mismatch', 'The invitation does not come from this instance’s trusted AU-PReMIS origin.', 422);
        }
        if (! hash_equals($configuredTarget, $this->origin($targetOrigin))) {
            throw new ApiSyncException('target_origin_mismatch', 'The invitation targets a different ATTP deployment.', 422);
        }

        $parts = $this->parts($confirmationUrl);
        $callbackOrigin = $this->origin($confirmationUrl);
        $expectedPath = '/api/v2/portfolio-sync/invitations/'.strtolower($invitationId).'/confirm';
        if (! hash_equals($configuredCentral, $callbackOrigin)
            || ! hash_equals($expectedPath, strtolower((string) ($parts['path'] ?? '')))
            || isset($parts['query'])
            || isset($parts['fragment'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            throw new ApiSyncException('confirmation_url_rejected', 'The signed confirmation URL is outside the trusted AU-PReMIS callback endpoint.', 422);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function httpOptions(string $url): array
    {
        $parts = $this->parts($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $addresses = $this->resolve($host);
        if ($addresses === []) {
            throw new ApiSyncException('central_origin_unavailable', 'The trusted AU-PReMIS host could not be resolved safely.', 503);
        }

        $allowPrivate = (bool) config('api_sync.v2.central.allow_private_networks', false);
        $allowedIps = array_map('strtolower', (array) config('api_sync.v2.central.allowed_ips', []));
        foreach ($addresses as $address) {
            $public = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
            if (! $public && (! $allowPrivate || ! in_array(strtolower($address), $allowedIps, true))) {
                throw new ApiSyncException('central_origin_rejected', 'The trusted AU-PReMIS hostname resolved to a disallowed network address.', 503);
            }
        }

        $options = [
            'allow_redirects' => false,
            'on_headers' => static function (ResponseInterface $response): void {
                $length = $response->getHeaderLine('Content-Length');
                if ($length !== '' && ctype_digit($length) && (int) $length > 65_536) {
                    throw new RuntimeException('AU-PReMIS confirmation response exceeds the 64 KiB limit.');
                }
            },
            'progress' => static function (int $downloadTotal, int $downloadedBytes): void {
                if ($downloadTotal > 65_536 || $downloadedBytes > 65_536) {
                    throw new RuntimeException('AU-PReMIS confirmation response exceeds the 64 KiB limit.');
                }
            },
        ];
        if (app()->environment('production') && ! defined('CURLOPT_RESOLVE')) {
            throw new ApiSyncException('dns_pinning_unavailable', 'Production synchronization requires cURL DNS pinning support.', 503);
        }
        if (defined('CURLOPT_RESOLVE')) {
            $resolved = implode(',', array_map(
                static fn (string $ip): string => str_contains($ip, ':') ? '['.$ip.']' : $ip,
                $addresses,
            ));
            $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$resolved}"]];
        }

        return $options;
    }

    public function origin(string $value): string
    {
        $parts = $this->parts($value);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        if (! in_array($scheme, ['https', 'http'], true) || $host === '') {
            throw new ApiSyncException('origin_configuration_invalid', 'A valid synchronization origin is required.', 503);
        }
        if (app()->environment('production') && $scheme !== 'https') {
            throw new ApiSyncException('https_required', 'Production synchronization origins must use HTTPS.', 503);
        }
        if (($parts['path'] ?? '') !== '' && ($parts['path'] ?? '') !== '/') {
            // Callbacks contain a path; origin inputs are normalized by ignoring
            // it only when this method is called from callback validation.
            $isCallback = str_contains((string) $parts['path'], '/api/v2/portfolio-sync/');
            if (! $isCallback) {
                throw new ApiSyncException('origin_configuration_invalid', 'Synchronization origins cannot contain a path.', 503);
            }
        }
        if (isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new ApiSyncException('origin_configuration_invalid', 'Synchronization origins cannot contain credentials, queries, or fragments.', 503);
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $includePort = $port !== null && ! (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80));

        return $scheme.'://'.$host.($includePort ? ':'.$port : '');
    }

    /** @return array<string, mixed> */
    private function parts(string $url): array
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts)) {
            throw new ApiSyncException('origin_configuration_invalid', 'The synchronization endpoint is invalid.', 503);
        }

        return $parts;
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $addresses = [];
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            foreach ($records as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($address) && filter_var($address, FILTER_VALIDATE_IP)) {
                    $addresses[] = $address;
                }
            }
        }

        if ($addresses === []) {
            $fallback = gethostbyname($host);
            if ($fallback !== $host && filter_var($fallback, FILTER_VALIDATE_IP)) {
                $addresses[] = $fallback;
            }
        }

        return array_values(array_unique($addresses));
    }
}
