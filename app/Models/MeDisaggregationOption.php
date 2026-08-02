<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeDisaggregationOption extends BaseModel
{
    protected $table = 'me_disaggregation_options';

    protected $fillable = [
        'dimension_id',
        'parent_id',
        'code',
        'name',
        'metadata',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(MeDisaggregationDimension::class, 'dimension_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
