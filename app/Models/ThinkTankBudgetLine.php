<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThinkTankBudgetLine extends BaseModel
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
    ];

    protected $table = 'attp_think_tank_budget_lines';

    protected $fillable = [
        'consortium_id',
        'think_tank_member_id',
        'parent_id',
        'fund_allocation_id',
        'procurement_item_id',
        'code',
        'name',
        'description',
        'fiscal_year',
        'currency',
        'amount',
        'status',
        'portal_lock_version',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'portal_lock_version' => 'integer',
    ];

    public function consortium(): BelongsTo
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function fundAllocation(): BelongsTo
    {
        return $this->belongsTo(ConsortiumFundAllocation::class, 'fund_allocation_id');
    }

    public function procurementItem(): BelongsTo
    {
        return $this->belongsTo(ThinkTankProcurementItem::class, 'procurement_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function nextPortalLockVersion(): int
    {
        return max(1, (int) ($this->portal_lock_version ?: 1)) + 1;
    }
}
