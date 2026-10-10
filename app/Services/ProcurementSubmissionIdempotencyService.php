<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

class ProcurementSubmissionIdempotencyService
{
    /** @param array<string, mixed> $payload */
    public function fingerprint(
        string $operation,
        mixed $actorId,
        array $payload,
    ): string {
        try {
            $encoded = json_encode($this->canonicalize([
                'operation' => $operation,
                'actor_id' => filled($actorId) ? (string) $actorId : null,
                'payload' => $payload,
            ]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('The submission could not be fingerprinted safely.', previous: $exception);
        }

        return hash('sha256', $encoded);
    }

    public function assertReplayMatches(
        mixed $storedFingerprint,
        string $submittedFingerprint,
        string $field = 'idempotency_key',
    ): void {
        if (! is_string($storedFingerprint)
            || strlen($storedFingerprint) !== 64
            || ! hash_equals($storedFingerprint, $submittedFingerprint)) {
            throw ValidationException::withMessages([
                $field => ['This submission key was already used for different procurement details. Refresh the form and try again.'],
            ]);
        }
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            $path = $value->getRealPath();
            if (! is_string($path) || $path === '' || ! is_file($path)) {
                throw new RuntimeException('An uploaded file could not be fingerprinted safely.');
            }

            return [
                'upload_name' => $value->getClientOriginalName(),
                'upload_size' => $value->getSize(),
                'upload_sha256' => hash_file('sha256', $path),
            ];
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value, SORT_STRING);
            }

            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }
        if (is_object($value)) {
            throw new RuntimeException('An unsupported submission value could not be fingerprinted safely.');
        }

        return $value;
    }
}
