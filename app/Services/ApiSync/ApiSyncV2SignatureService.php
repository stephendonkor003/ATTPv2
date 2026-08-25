<?php

namespace App\Services\ApiSync;

use App\Exceptions\ApiSyncException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use JsonException;
use OpenSSLAsymmetricKey;

class ApiSyncV2SignatureService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{key_id: string, timestamp: int, nonce: string, request_id: string, payload_hash: string}
     */
    public function verifyRequest(Request $request, array $payload): array
    {
        if ($request->getQueryString() !== null) {
            throw new ApiSyncException('signed_query_not_allowed', 'Signed synchronization requests cannot contain a query string.', 400);
        }

        return $this->verify(
            strtoupper($request->method()),
            '/'.ltrim($request->path(), '/'),
            $payload,
            fn (string $name): string => trim((string) $request->header($name)),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{key_id: string, timestamp: int, nonce: string, request_id: string, payload_hash: string}
     */
    public function verifyResponse(ClientResponse $response, string $path, array $payload, string $expectedRequestId): array
    {
        $verified = $this->verify(
            (string) $response->status(),
            $path,
            $payload,
            fn (string $name): string => trim((string) $response->header($name)),
        );

        if (! hash_equals(strtolower($expectedRequestId), strtolower($verified['request_id']))) {
            throw new ApiSyncException('confirmation_receipt_mismatch', 'The AU-PReMIS confirmation receipt did not match this approval attempt.', 502);
        }

        return $verified;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(string): string  $header
     * @return array{key_id: string, timestamp: int, nonce: string, request_id: string, payload_hash: string}
     */
    private function verify(string $verbOrStatus, string $path, array $payload, callable $header): array
    {
        $this->ensureConfigured();

        $keyId = $header('X-AUPReMIS-Key-Id');
        $timestampValue = $header('X-AUPReMIS-Timestamp');
        $nonce = strtolower($header('X-AUPReMIS-Nonce'));
        $requestId = strtolower($header('X-AUPReMIS-Request-Id'));
        $encodedSignature = $header('X-AUPReMIS-Signature');

        if ($keyId === '' || $timestampValue === '' || $nonce === '' || $requestId === '' || $encodedSignature === '') {
            throw new ApiSyncException('signature_required', 'A complete AU-PReMIS request signature is required.', 401);
        }

        $configuredKeyId = trim((string) config('api_sync.v2.central.key_id'));
        if (! hash_equals($configuredKeyId, $keyId)) {
            throw new ApiSyncException('unknown_signing_key', 'The AU-PReMIS signing key is not trusted by this ATTP instance.', 401);
        }

        if (! preg_match('/^[0-9]{10}$/', $timestampValue)) {
            throw new ApiSyncException('invalid_signature_timestamp', 'The request signature timestamp is invalid.', 401);
        }
        $timestamp = (int) $timestampValue;
        $maximumSkew = (int) config('api_sync.v2.maximum_clock_skew_seconds', 300);
        if (abs(now()->timestamp - $timestamp) > $maximumSkew) {
            throw new ApiSyncException('stale_signature', 'The signed synchronization request is outside the allowed clock window.', 401);
        }

        if (! Str::isUuid($nonce) || ! Str::isUuid($requestId)) {
            throw new ApiSyncException('invalid_signature_identifier', 'The signed request identifiers are invalid.', 401);
        }

        $signature = base64_decode($encodedSignature, true);
        if ($signature === false || strlen($signature) < 256 || strlen($signature) > 1_024) {
            throw new ApiSyncException('invalid_signature', 'The AU-PReMIS request signature is invalid.', 401);
        }

        $payloadHash = hash('sha256', $this->canonicalJson($payload));
        $signingInput = implode("\n", [
            $verbOrStatus,
            $path,
            $timestampValue,
            $nonce,
            $requestId,
            strtolower($payloadHash),
        ]);
        $verified = openssl_verify($signingInput, $signature, $this->publicKey(), OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new ApiSyncException('invalid_signature', 'The AU-PReMIS request signature is invalid.', 401);
        }

        return [
            'key_id' => $keyId,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'request_id' => $requestId,
            'payload_hash' => $payloadHash,
        ];
    }

    /**
     * RFC-style deterministic JSON used by both sides before signing.
     * Object keys are recursively sorted; list order remains significant.
     *
     * @param  array<string, mixed>  $payload
     */
    public function canonicalJson(array $payload): string
    {
        try {
            return json_encode(
                $this->sortCanonical($payload),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $exception) {
            throw new ApiSyncException('invalid_signed_payload', 'The signed synchronization payload is not valid JSON.', 400);
        }
    }

    private function sortCanonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->sortCanonical($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->sortCanonical($item);
        }

        return $value;
    }

    private function publicKey(): OpenSSLAsymmetricKey
    {
        $pem = trim((string) config('api_sync.v2.central.public_key_pem'));
        if ($pem === '') {
            $path = trim((string) config('api_sync.v2.central.public_key_path'));
            if ($path === '' || ! is_file($path) || ! is_readable($path)) {
                throw new ApiSyncException('signature_configuration_invalid', 'The trusted AU-PReMIS public key is unavailable.', 503);
            }
            $pem = (string) file_get_contents($path);
        }

        $key = openssl_pkey_get_public($pem);
        if (! $key instanceof OpenSSLAsymmetricKey) {
            throw new ApiSyncException('signature_configuration_invalid', 'The trusted AU-PReMIS public key is invalid.', 503);
        }

        $details = openssl_pkey_get_details($key);
        if (! is_array($details) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || (int) ($details['bits'] ?? 0) < 3_072) {
            throw new ApiSyncException('signature_configuration_invalid', 'The trusted AU-PReMIS signing key must be RSA with at least 3072 bits.', 503);
        }

        $configuredFingerprint = strtolower(str_replace(':', '', trim((string) config('api_sync.v2.central.public_key_sha256'))));
        $actualFingerprint = hash('sha256', trim((string) $details['key'])."\n");
        if (! preg_match('/^[a-f0-9]{64}$/', $configuredFingerprint) || ! hash_equals($configuredFingerprint, $actualFingerprint)) {
            throw new ApiSyncException('signature_configuration_invalid', 'The AU-PReMIS public-key fingerprint does not match the pinned value.', 503);
        }

        return $key;
    }

    private function ensureConfigured(): void
    {
        if (! config('api_sync.enabled') || ! config('api_sync.v2.enabled')) {
            throw new ApiSyncException('api_sync_disabled', 'AU-PReMIS initiated synchronization is disabled on this ATTP instance.', 503);
        }
        if (trim((string) config('api_sync.v2.central.key_id')) === '') {
            throw new ApiSyncException('signature_configuration_invalid', 'A trusted AU-PReMIS signing key ID is required.', 503);
        }
    }
}
