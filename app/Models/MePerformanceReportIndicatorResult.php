<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MePerformanceReportIndicatorResult extends BaseModel
{
    protected $table = 'me_performance_report_indicator_results';

    protected $fillable = [
        'report_id',
        'indicator_id',
        'indicator_result_id',
        'target_value',
        'annual_target',
        'life_of_programme_target',
        'actual_value',
        'cumulative_year_result',
        'cumulative_programme_result',
        'progress_percent',
        'target_achievement_percent',
        'reporting_frequency',
        'aggregation_method',
    ];

    protected $casts = [
        'target_value' => 'decimal:4',
        'annual_target' => 'decimal:4',
        'life_of_programme_target' => 'decimal:4',
        'actual_value' => 'decimal:4',
        'cumulative_year_result' => 'decimal:4',
        'cumulative_programme_result' => 'decimal:4',
        'progress_percent' => 'decimal:2',
        'target_achievement_percent' => 'decimal:2',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MePerformanceReport::class, 'report_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    public function indicatorResult(): BelongsTo
    {
        return $this->belongsTo(IndicatorResult::class, 'indicator_result_id');
    }
}
