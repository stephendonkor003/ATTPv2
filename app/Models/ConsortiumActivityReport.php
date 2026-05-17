<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsortiumActivityReport extends BaseModel
{
    protected $table = 'attp_activity_reports';

    protected $fillable = [
        'consortium_id',
        'think_tank_member_id',
        'workplan_id',
        'activity_id',
        'sub_activity_id',
        'title',
        'reporting_period_start',
        'reporting_period_end',
        'progress_percent',
        'funds_spent',
        'status',
        'summary',
        'achievements',
        'challenges',
        'next_steps',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'reporting_period_start' => 'date',
        'reporting_period_end' => 'date',
        'progress_percent' => 'decimal:2',
        'funds_spent' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function consortium(): BelongsTo
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function workplan(): BelongsTo
    {
        return $this->belongsTo(ConsortiumWorkplan::class, 'workplan_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(ConsortiumReportEvidence::class, 'activity_report_id');
    }
}
