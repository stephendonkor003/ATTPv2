<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndicatorDisaggregation extends BaseModel
{
    public const LEVELS = [
        'primary' => 1,
        'secondary' => 2,
        'tertiary' => 3,
    ];

    public const SUGGESTED_DIMENSIONS = [
        'Gender',
        'Age Group',
        'Geographic Location',
        'Disability Status',
        'Income / Wealth Quintile',
        'Population Group',
        'Institution Type',
        'Other',
    ];

    protected $table = 'me_indicator_disaggregations';

    protected $fillable = [
        'indicator_id',
        'level',
        'dimension',
        'parent_id',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
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
