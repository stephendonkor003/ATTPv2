<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeIndicatorDisaggregationRequirement extends BaseModel
{
    protected $table = 'me_indicator_disaggregation_requirements';

    protected $fillable = [
        'indicator_id',
        'dimension_id',
        'is_required',
        'collect_numeric_value',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'collect_numeric_value' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(MeDisaggregationDimension::class, 'dimension_id');
    }
}
