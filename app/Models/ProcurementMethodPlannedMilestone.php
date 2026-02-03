<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementMethodPlannedMilestone extends BaseModel
{
    protected $table = 'myb_procurement_method_planned_milestones';

    protected $fillable = [
        'procurement_method_planned_id',
        'title',
        'description',
        'target_days',
        'sort_order',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'target_days' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function method(): BelongsTo
    {
        return $this->belongsTo(ProcurementMethodPlanned::class, 'procurement_method_planned_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
