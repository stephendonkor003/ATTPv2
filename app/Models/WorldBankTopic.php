<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorldBankTopic extends BaseModel
{
    protected $table = 'world_bank_topics';

    protected $fillable = [
        'wb_topic_id',
        'name',
        'source_note',
        'metadata',
    ];

    protected $casts = [
        'wb_topic_id' => 'integer',
        'metadata' => 'array',
    ];

    public function indicators(): BelongsToMany
    {
        return $this->belongsToMany(
            WorldBankIndicator::class,
            'world_bank_indicator_topic',
            'world_bank_topic_id',
            'world_bank_indicator_id'
        );
    }
}

