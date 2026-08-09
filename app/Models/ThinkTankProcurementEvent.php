<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThinkTankProcurementEvent extends BaseModel
{
    public $timestamps = false;

    protected $table = 'attp_think_tank_procurement_events';

    protected $fillable = [
        'plan_id', 'item_id', 'actor_id', 'action', 'from_status',
        'to_status', 'reason', 'metadata', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ThinkTankProcurementPlan::class, 'plan_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ThinkTankProcurementItem::class, 'item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
