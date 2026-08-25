<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ApiSyncInvitationEvent extends BaseModel
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'invitation_id',
        'user_id',
        'event_type',
        'lifecycle_key',
        'message',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('API synchronization invitation events are append-only.'));
        static::deleting(fn () => throw new LogicException('API synchronization invitation events are append-only.'));
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(ApiSyncInvitation::class, 'invitation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
