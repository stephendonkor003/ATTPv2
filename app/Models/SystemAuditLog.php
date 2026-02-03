<?php

namespace App\Models;

use App\Models\BaseModel;

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
