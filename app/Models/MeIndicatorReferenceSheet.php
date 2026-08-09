<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeIndicatorReferenceSheet extends BaseModel
{
    protected $table = 'me_indicator_reference_sheets';

    protected $fillable = [
        'indicator_id', 'framework_id', 'version', 'definition', 'rationale',
        'inclusion_criteria', 'exclusion_criteria', 'unit_of_measurement',
        'data_collection_method', 'disaggregation', 'data_sources',
        'calculation_method', 'collection_frequency', 'reporting_frequency',
        'means_of_verification', 'data_generation_responsibility',
        'verification_responsibility', 'additional_guidance', 'approval_status',
        'approved_by', 'approved_at', 'effective_from', 'effective_to',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'disaggregation' => 'array',
        'approved_at' => 'datetime',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    public function framework(): BelongsTo
    {
        return $this->belongsTo(MeFramework::class, 'framework_id');
    }
}
