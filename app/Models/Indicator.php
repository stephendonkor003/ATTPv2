<?php

namespace App\Models;

use App\Models\BaseModel;

class Indicator extends BaseModel
{
    protected $table = 'myb_indicators';

    protected $fillable = [
        'indicatorable_type',
        'indicatorable_id',
        'name',
        'baseline_year',
        'baseline_type',
        'baseline_value',
        'indicator_level_id',
        'methodology',
        'notes',
        'responsible_party',
        'frequency_of_reporting_id',
        'unit_id',
        'primary_source',
        'definitions',
        'created_by',
    ];

    protected $casts = [
        'baseline_type' => 'string',
    ];

    // Polymorphic relationship to parent (Program, Project, Activity, SubActivity)
    public function indicatorable()
    {
        return $this->morphTo();
    }

    // M&E Configuration relationships
    public function level()
    {
        return $this->belongsTo(IndicatorLevel::class, 'indicator_level_id');
    }

    public function frequency()
    {
        return $this->belongsTo(ReportingFrequency::class, 'frequency_of_reporting_id');
    }

    public function unit()
    {
        return $this->belongsTo(IndicatorUnit::class, 'unit_id');
    }

    // Nested project indicators (if this indicator belongs to a program)
    public function projectIndicators()
    {
        return $this->hasMany(Indicator::class, 'parent_indicator_id');
    }

    // Parent program indicator (if this is a project indicator)
    public function parentIndicator()
    {
        return $this->belongsTo(Indicator::class, 'parent_indicator_id');
    }

    // Targets and actuals
    public function targets()
    {
        return $this->hasMany(IndicatorTarget::class);
    }

    public function results()
    {
        return $this->hasMany(IndicatorResult::class);
    }

    public function surveyLink()
    {
        return $this->hasOne(IndicatorSurveyLink::class, 'indicator_id')
            ->where('is_active', true);
    }

    public function surveyResponses()
    {
        return $this->hasMany(IndicatorSurveyResponse::class, 'indicator_id');
    }

    public function dataSourceSyncLogs()
    {
        return $this->hasMany(IndicatorDataSourceSyncLog::class, 'indicator_id');
    }

    public function latestDataSourceSyncLog()
    {
        return $this->hasOne(IndicatorDataSourceSyncLog::class, 'indicator_id')
            ->latestOfMany('synced_at');
    }
}
