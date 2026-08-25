<?php

namespace App\Services\ApiSync;

use App\Exceptions\ApiSyncException;
use JsonException;

class ApiSyncCursor
{
    public function __construct(private readonly ?string $signingKey = null) {}

    /**
     * @param  array<string, int|string>  $position
     */
    public function encode(string $dataset, string $snapshotId, string $consumerInstance, array $position): string
    {
        $payload = $this->base64UrlEncode(json_encode([
            'dataset' => $dataset,
            'snapshot_id' => $snapshotId,
            'consumer_instance' => $consumerInstance,
            'position' => $position,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $signature = hash_hmac('sha256', $payload, $this->key(), true);

        return $payload.'.'.$this->base64UrlEncode($signature);
    }

    /**
     * @return array<string, int|string>
     */
    public function decode(string $cursor, string $dataset, string $snapshotId, string $consumerInstance): array
    {
        if (strlen($cursor) > 1024 || substr_count($cursor, '.') !== 1) {
            throw $this->invalid();
        }

        [$payload, $providedSignature] = explode('.', $cursor, 2);
        $expectedSignature = $this->base64UrlEncode(hash_hmac('sha256', $payload, $this->key(), true));

        if (! hash_equals($expectedSignature, $providedSignature)) {
            throw $this->invalid();
        }

        try {
            $decoded = json_decode($this->base64UrlDecode($payload), true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->invalid();
        }

        if (
            ! is_array($decoded)
            || ($decoded['dataset'] ?? null) !== $dataset
            || ($decoded['snapshot_id'] ?? null) !== $snapshotId
            || ($decoded['consumer_instance'] ?? null) !== $consumerInstance
            || ! is_array($decoded['position'] ?? null)
        ) {
            throw $this->invalid();
        }

        return $decoded['position'];
    }

    private function key(): string
    {
        $key = $this->signingKey ?? (string) config('api_sync.pairing_pepper');
        if ($key === '') {
            throw new \RuntimeException('ATTP API Sync requires a pairing pepper or APP_KEY.');
        }

        return $key;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);
        if (! is_string($decoded)) {
            throw $this->invalid();
        }

        return $decoded;
    }

    private function invalid(): ApiSyncException
    {
        return new ApiSyncException('invalid_cursor', 'The synchronization cursor is invalid or belongs to another session.', 422);
    }
}
