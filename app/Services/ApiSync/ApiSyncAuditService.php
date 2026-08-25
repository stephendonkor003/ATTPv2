<?php

namespace App\Services\ApiSync;

use App\Models\ApiSyncEvent;
use App\Models\ApiSyncPairing;
use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiSyncAuditService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ?ApiSyncPairing $pairing,
        string $eventType,
        string $message,
        array $metadata = [],
        ?string $dataset = null,
        ?int $recordCount = null,
        ?User $actor = null,
        ?Request $request = null,
        ?int $statusCode = null,
    ): ApiSyncEvent {
        $request ??= app()->runningInConsole() ? null : request();
        $actor ??= $request?->user();
        $safeMetadata = $this->redact($metadata);

        return DB::transaction(function () use (
            $pairing,
            $eventType,
            $message,
            $dataset,
            $recordCount,
            $actor,
            $request,
            $statusCode,
            $safeMetadata,
        ): ApiSyncEvent {
            $event = ApiSyncEvent::query()->create([
                'pairing_id' => $pairing?->id,
                'user_id' => $actor?->id,
                'event_type' => $eventType,
                'message' => $message,
                'dataset' => $dataset,
                'record_count' => $recordCount,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent() ? mb_substr((string) $request->userAgent(), 0, 500) : null,
                'metadata' => $safeMetadata ?: null,
            ]);

            SystemAuditLog::query()->create([
                'user_id' => $actor?->id,
                'module' => 'api_sync',
                'action' => $eventType,
                'action_message' => $message,
                'description' => $message,
                'method' => $request?->method(),
                'url' => $request?->url(),
                'route_name' => $request?->route()?->getName(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent() ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
                'status_code' => $statusCode ?? 200,
                'payload' => array_filter([
                    'pairing_id' => $pairing?->id,
                    'consumer_instance' => $pairing?->consumer_instance,
                    'snapshot_id' => $pairing?->snapshot_id,
                    'dataset' => $dataset,
                    'record_count' => $recordCount,
                    'details' => $safeMetadata ?: null,
                ], static fn (mixed $value): bool => $value !== null),
            ]);

            return $event;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        $sensitive = [
            'code', 'pairing_code', 'code_hash', 'access_token', 'token',
            'token_hash', 'recovery_key', 'claim_recovery_hash', 'x-claim-recovery-key',
            'secret', 'password', 'authorization',
        ];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitive, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            } elseif (is_string($value) && mb_strlen($value) > 500) {
                $data[$key] = mb_substr($value, 0, 500).'...';
            }
        }

        return $data;
    }
}
