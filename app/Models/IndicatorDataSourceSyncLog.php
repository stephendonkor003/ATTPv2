<?php

namespace App\Models;

class IndicatorDataSourceSyncLog extends BaseModel
{
    protected $table = 'me_indicator_data_source_sync_logs';

    protected $fillable = [
        'indicator_id',
        'source_type',
        'source_value',
        'status',
        'message',
        'synced_rows',
        'started_at',
        'synced_at',
        'synced_by',
        'meta',
    ];

    protected $casts = [
        'synced_rows' => 'integer',
        'started_at' => 'datetime',
        'synced_at' => 'datetime',
        'meta' => 'array',
    ];

    public function indicator()
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    public function syncedBy()
    {
        return $this->belongsTo(User::class, 'synced_by');
    }
}

