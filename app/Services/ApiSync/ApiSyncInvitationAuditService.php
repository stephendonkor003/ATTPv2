<?php

namespace App\Services\ApiSync;

use App\Models\ApiSyncInvitation;
use App\Models\ApiSyncInvitationEvent;
use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiSyncInvitationAuditService
{
    /**
     * Record a lifecycle event exactly once while the caller holds the
     * invitation row lock. This also heals an event missing from an older
     * interrupted deployment without duplicating append-only history.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordOnce(
        ApiSyncInvitation $invitation,
        string $eventType,
        string $message,
        array $metadata = [],
        ?User $actor = null,
        ?Request $request = null,
        int $statusCode = 200,
    ): ApiSyncInvitationEvent {
        $existing = ApiSyncInvitationEvent::query()
            ->where('invitation_id', $invitation->id)
            ->where('event_type', $eventType)
            ->first();

        return $existing ?? $this->record(
            $invitation,
            $eventType,
            $message,
            $metadata,
            $actor,
            $request,
            $statusCode,
        );
    }

    /** @param array<string, mixed> $metadata */
    public function record(
        ?ApiSyncInvitation $invitation,
        string $eventType,
        string $message,
        array $metadata = [],
        ?User $actor = null,
        ?Request $request = null,
        int $statusCode = 200,
    ): ApiSyncInvitationEvent {
        $request ??= app()->runningInConsole() ? null : request();
        $actor ??= $request?->user();
        $safe = $this->redact($metadata);

        return DB::transaction(function () use ($invitation, $eventType, $message, $safe, $actor, $request, $statusCode): ApiSyncInvitationEvent {
            $event = ApiSyncInvitationEvent::query()->create([
                'invitation_id' => $invitation?->id,
                'user_id' => $actor?->id,
                'event_type' => $eventType,
                'lifecycle_key' => $this->lifecycleKey($invitation, $eventType),
                'message' => $message,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent() ? mb_substr((string) $request->userAgent(), 0, 500) : null,
                'metadata' => $safe ?: null,
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
                'user_agent' => $request?->userAgent() ? mb_substr((string) $request->userAgent(), 0, 1_000) : null,
                'status_code' => $statusCode,
                'payload' => array_filter([
                    'invitation_id' => $invitation?->id,
                    'central_instance_id' => $invitation?->central_instance_id,
                    'status' => $invitation?->status,
                    'details' => $safe ?: null,
                ], static fn (mixed $value): bool => $value !== null),
            ]);

            return $event;
        }, 3);
    }

    private function lifecycleKey(?ApiSyncInvitation $invitation, string $eventType): ?string
    {
        if (! $invitation || ! in_array($eventType, [
            'invitation_received',
            'invitation_authorized',
            'invitation_transfer_completed',
        ], true)) {
            return null;
        }

        return hash('sha256', strtolower((string) $invitation->id)."\0".$eventType);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function redact(array $data): array
    {
        $sensitive = ['code', 'authorization_code', 'pairing_code', 'credential', 'credential_digest', 'token', 'authorization', 'current_password', 'confirmation_receipt'];
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
