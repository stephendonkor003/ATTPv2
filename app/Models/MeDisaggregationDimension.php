<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MeDisaggregationDimension extends BaseModel
{
    protected $table = 'me_disaggregation_dimensions';

    protected $fillable = [
        'code',
        'name',
        'dimension_group',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function options(): HasMany
    {
        return $this->hasMany(MeDisaggregationOption::class, 'dimension_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function indicatorRequirements(): HasMany
    {
        return $this->hasMany(MeIndicatorDisaggregationRequirement::class, 'dimension_id');
    }
}
