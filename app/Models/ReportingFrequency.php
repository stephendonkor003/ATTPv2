<?php

namespace App\Models;

use App\Models\BaseModel;

class ReportingFrequency extends BaseModel
{
    protected $table = 'me_reporting_frequencies';

    protected $fillable = [
        'name',
        'code',
        'frequency_in_days',
        'description',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'frequency_in_days' => 'integer',
    ];

    // Relationships
    public function indicators()
    {
        return $this->hasMany(Indicator::class, 'frequency_of_reporting_id');
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
