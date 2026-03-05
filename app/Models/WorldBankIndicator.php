<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorldBankIndicator extends BaseModel
{
    protected $table = 'world_bank_indicators';

    protected $fillable = [
        'wb_indicator_id',
        'name',
        'unit',
        'source_note',
        'source_organization',
        'source_id',
        'source_name',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(
            WorldBankTopic::class,
            'world_bank_indicator_topic',
            'world_bank_indicator_id',
            'world_bank_topic_id'
        );
    }

    public function observations(): HasMany
    {
        return $this->hasMany(WorldBankIndicatorObservation::class, 'world_bank_indicator_id');
    }
}

