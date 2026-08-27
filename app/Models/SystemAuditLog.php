<?php

namespace App\Models;

use App\Support\UserImpersonation;

class SystemAuditLog extends BaseModel
{
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
