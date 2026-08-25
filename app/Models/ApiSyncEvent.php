<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ApiSyncEvent extends BaseModel
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'pairing_id',
        'user_id',
        'event_type',
        'message',
        'dataset',
        'record_count',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'record_count' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('API sync events are append-only.'));
        static::deleting(fn () => throw new LogicException('API sync events are append-only.'));
    }

    public function pairing(): BelongsTo
    {
        return $this->belongsTo(ApiSyncPairing::class, 'pairing_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
