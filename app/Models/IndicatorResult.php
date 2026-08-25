<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;

class IndicatorResult extends BaseModel
{
    protected $table = 'me_indicator_results';

    protected $fillable = [
        'indicator_id',
        'reporting_period_id',
        'think_tank_member_id',
        'data_submission_id',
        'source_field_key',
        'period_type',
        'period_label',
        'period_start',
        'period_end',
        'actual_value',
        'actual_text',
        'unit_id',
        'data_source',
        'method',
        'notes',
        'review_status',
        'validated_by',
        'validated_at',
        'approved_by',
        'approved_at',
        'review_notes',
        'collected_by',
        'collected_at',
        'created_by',
        'updated_by',
        'rollup_numerator',
        'rollup_denominator',
        'source_record_type',
        'source_record_id',
        'deduplication_key',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'collected_at' => 'datetime',
        'validated_at' => 'datetime',
        'approved_at' => 'datetime',
        'actual_value' => 'decimal:4',
        'rollup_numerator' => 'decimal:4',
        'rollup_denominator' => 'decimal:4',
    ];

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    public function unit()
    {
        return $this->belongsTo(IndicatorUnit::class, 'unit_id');
    }

    public function reportingPeriod()
    {
        return $this->belongsTo(MeReportingPeriod::class, 'reporting_period_id');
    }

    public function thinkTank()
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function dataSubmission()
    {
        return $this->belongsTo(MeDataSubmission::class, 'data_submission_id');
    }

    public function submissionAnswer()
    {
        return $this->hasOne(MeDataSubmissionAnswer::class, 'indicator_result_id');
    }

    public function collectedByUser()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function validatedByUser()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function achievements()
    {
        return $this->hasMany(MeIndicatorAchievement::class, 'indicator_result_id');
    }

    public function performanceReportResult(): HasOne
    {
        return $this->hasOne(MePerformanceReportIndicatorResult::class, 'indicator_result_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('review_status', 'approved')->whereNotNull('approved_at');
    }
}
