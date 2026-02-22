<?php

namespace App\Models;

use App\Models\BaseModel;

class IndicatorResult extends BaseModel
{
    protected $table = 'me_indicator_results';

    protected $fillable = [
        'indicator_id',
        'period_type',
        'period_label',
        'period_start',
        'period_end',
        'actual_value',
        'unit_id',
        'data_source',
        'method',
        'notes',
        'collected_by',
        'collected_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'collected_at' => 'datetime',
        'actual_value' => 'decimal:4',
    ];

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    public function unit()
    {
        return $this->belongsTo(IndicatorUnit::class, 'unit_id');
    }

    public function collectedByUser()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
