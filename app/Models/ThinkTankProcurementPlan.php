<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThinkTankProcurementPlan extends BaseModel
{
    protected $table = 'attp_think_tank_procurement_plans';

    protected $fillable = [
        'consortium_id',
        'think_tank_member_id',
        'plan_code',
        'title',
        'fiscal_year',
        'estimated_budget',
        'currency',
        'planned_publish_date',
        'status',
        'description',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'estimated_budget' => 'decimal:2',
        'planned_publish_date' => 'date',
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

    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class, 'think_tank_procurement_plan_id');
    }
}
