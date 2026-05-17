<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsortiumRiskFlag extends BaseModel
{
    protected $table = 'attp_risk_flags';

    protected $fillable = [
        'consortium_id',
        'think_tank_member_id',
        'activity_report_id',
        'title',
        'category',
        'severity',
        'status',
        'description',
        'mitigation_plan',
        'raised_by',
        'assigned_to',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function consortium(): BelongsTo
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }
}
