<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\ProcurementMethodPlannedMilestone;
use App\Models\User;

class ProcurementMethodPlanned extends BaseModel
{
    protected $table = 'myb_procurement_method_planned';

    protected $fillable = [
        'method_name',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProcurementMethodPlannedMilestone::class, 'procurement_method_planned_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function getMethodTargetDaysAttribute(): int
    {
        if ($this->relationLoaded('milestones')) {
            return $this->milestones->sum('target_days');
        }

        return (int) $this->milestones()->sum('target_days');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
