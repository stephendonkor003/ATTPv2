<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeIndicatorCalculationRule extends BaseModel
{
    protected $table = 'me_indicator_calculation_rules';

    protected $fillable = [
        'indicator_id', 'framework_id', 'calculation_key', 'source_type',
        'configuration', 'deduplication_key', 'version', 'is_active',
        'effective_from', 'effective_to', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'configuration' => 'array',
        'version' => 'integer',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }
}
