<?php

namespace App\Models;

use App\Models\BaseModel;

class IndicatorTarget extends BaseModel
{
    protected $table = 'me_indicator_targets';

    protected $fillable = [
        'indicator_id',
        'period_type',
        'period_label',
        'period_start',
        'period_end',
        'target_value',
        'unit_id',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'target_value' => 'decimal:4',
    ];

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    public function unit()
    {
        return $this->belongsTo(IndicatorUnit::class, 'unit_id');
    }
}
