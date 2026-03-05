<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorldBankIndicatorObservation extends Model
{
    protected $table = 'world_bank_indicator_observations';

    protected $fillable = [
        'world_bank_indicator_id',
        'country_iso2',
        'country_name',
        'year',
        'value',
        'decimal_places',
        'observation_status',
        'fetched_at',
        'raw_payload',
    ];

    protected $casts = [
        'value' => 'float',
        'year' => 'integer',
        'decimal_places' => 'integer',
        'fetched_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(WorldBankIndicator::class, 'world_bank_indicator_id');
    }
}

