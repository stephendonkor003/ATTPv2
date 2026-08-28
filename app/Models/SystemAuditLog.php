<?php

namespace App\Models;

use App\Support\UserImpersonation;
use Illuminate\Support\Str;

class SystemAuditLog extends BaseModel
{
    /** @var array<string, int> */
    private const BOUNDED_STRING_LENGTHS = [
        'module' => 255,
        'action' => 255,
        'action_message' => 255,
        'method' => 255,
        'url' => 255,
        'route_name' => 255,
        'ip_address' => 255,
        'country' => 255,
    ];

    protected $fillable = [
        'user_id',
        'module',
        'action',
        'action_message',
        'description',
        'method',
        'url',
        'route_name',
        'ip_address',
        'country',
        'user_agent',
        'status_code',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SystemAuditLog $auditLog): void {
            $auditLog->normalizeBoundedContext();

            if (! app()->bound('request')) {
                return;
            }

            $request = request();

            if (! UserImpersonation::isActive($request)) {
                return;
            }

            $state = UserImpersonation::state($request);
            $payload = is_array($auditLog->payload) ? $auditLog->payload : [];
            $payload['_impersonation'] = [
                'administrator_id' => (string) $state['administrator_id'],
                'impersonated_user_id' => (string) $state['user_id'],
                'started_at' => $state['started_at'] ?? null,
            ];

            $auditLog->payload = $payload;
        });
    }

    /**
     * Audit logging must never invalidate the business write it describes.
     * Preserve oversized context in JSON while respecting legacy varchar
     * columns that remain deployed on PostgreSQL installations.
     */
    private function normalizeBoundedContext(): void
    {
        $unabridged = [];

        foreach (self::BOUNDED_STRING_LENGTHS as $attribute => $maximumLength) {
            $value = $this->getAttribute($attribute);

            if (! is_string($value) || mb_strlen($value) <= $maximumLength) {
                continue;
            }

            $unabridged[$attribute] = $value;
            $this->setAttribute($attribute, Str::limit($value, $maximumLength, ''));
        }

        if ($unabridged === []) {
            return;
        }

        $payload = is_array($this->payload) ? $this->payload : [];
        $existingContext = data_get($payload, '_unabridged_context', []);
        $payload['_unabridged_context'] = [
            ...(is_array($existingContext) ? $existingContext : []),
            ...$unabridged,
        ];
        $this->payload = $payload;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
