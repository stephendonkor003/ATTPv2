<?php

namespace App\Services\ThinkTank;

use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ThinkTankApiAuditService
{
    /**
     * Record a required event. Call this inside the same transaction as a
     * privileged mutation so the mutation cannot commit without its audit.
     *
     * @param  array<string, mixed>  $payload
     */
    public function required(Request $request, string $action, string $message, array $payload = [], ?User $actor = null): void
    {
        SystemAuditLog::query()->create($this->attributes($request, $action, $message, $payload, $actor));
    }

    /** @param array<string, mixed> $payload */
    public function bestEffort(Request $request, string $action, string $message, array $payload = [], ?User $actor = null): void
    {
        try {
            $this->required($request, $action, $message, $payload, $actor);
        } catch (Throwable $exception) {
            Log::warning('Think Tank API audit event could not be recorded.', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attributes(Request $request, string $action, string $message, array $payload, ?User $actor): array
    {
        return [
            'user_id' => $actor?->getKey() ?: $request->user()?->getKey(),
            'module' => 'think_tank_api',
            'action' => $action,
            'action_message' => $message,
            'description' => $message,
            'method' => $request->method(),
            // Never persist query strings: reset tokens, credentials, and
            // future sensitive filters do not belong in an audit URL field.
            'url' => '/'.ltrim($request->path(), '/'),
            'route_name' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'status_code' => 200,
            'payload' => $payload,
        ];
    }
}
