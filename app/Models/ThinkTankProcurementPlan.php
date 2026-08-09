<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThinkTankProcurementPlan extends BaseModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVISION_REQUESTED = 'revision_requested';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_APPROVED = 'approved';

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
        'version',
        'submitted_at',
        'last_resubmitted_at',
        'approved_at',
        'rejected_at',
        'decision_reason',
    ];

    protected $casts = [
        'estimated_budget' => 'decimal:2',
        'planned_publish_date' => 'date',
        'reviewed_at' => 'datetime',
        'submitted_at' => 'datetime',
        'last_resubmitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
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

    public function items(): HasMany
    {
        return $this->hasMany(ThinkTankProcurementItem::class, 'plan_id')->oldest();
    }

    public function events(): HasMany
    {
        return $this->hasMany(ThinkTankProcurementEvent::class, 'plan_id')->latest('created_at');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_REVISION_REQUESTED,
            self::STATUS_REJECTED,
        ], true);
    }
}
