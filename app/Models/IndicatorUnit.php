<?php

namespace App\Models;

use App\Models\BaseModel;

class IndicatorUnit extends BaseModel
{
    protected $table = 'me_indicator_units';

    protected $fillable = [
        'name',
        'symbol',
        'description',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function indicators()
    {
        return $this->hasMany(Indicator::class, 'unit_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
