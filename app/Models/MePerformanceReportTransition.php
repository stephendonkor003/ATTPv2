<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MePerformanceReportTransition extends BaseModel
{
    public const UPDATED_AT = null;

    protected $table = 'me_performance_report_transitions';

    protected $fillable = [
        'report_id',
        'from_status',
        'to_status',
        'action',
        'notes',
        'acted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MePerformanceReport::class, 'report_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
