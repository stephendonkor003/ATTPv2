<?php

namespace App\Models;

class IndicatorTarget extends BaseModel
{
    protected $table = 'me_indicator_targets';

    protected $fillable = [
        'indicator_id',
        'target_context',
        'period_type',
        'period_label',
        'period_start',
        'period_end',
        'target_value',
        'unit_id',
        'notes',
        'created_by',
        'updated_by',
        'framework_id',
        'reporting_year',
        'project_year',
        'target_scope',
        'think_tank_member_id',
        'baseline_value',
        'target_text',
        'revision',
        'revision_reason',
        'approval_status',
        'effective_from',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'target_value' => 'decimal:4',
        'reporting_year' => 'integer',
        'project_year' => 'integer',
        'revision' => 'integer',
        'effective_from' => 'date',
        'approved_at' => 'datetime',
    ];

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    public function unit()
    {
        return $this->belongsTo(IndicatorUnit::class, 'unit_id');
    }

    public function framework()
    {
        return $this->belongsTo(MeFramework::class, 'framework_id');
    }

    public function thinkTank()
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }
}
