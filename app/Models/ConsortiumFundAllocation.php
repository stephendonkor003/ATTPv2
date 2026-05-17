<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsortiumFundAllocation extends BaseModel
{
    protected $table = 'attp_fund_allocations';

    protected $fillable = [
        'consortium_id',
        'think_tank_member_id',
        'program_funding_id',
        'budget_line',
        'currency',
        'amount_allocated',
        'amount_committed',
        'amount_disbursed',
        'amount_spent',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount_allocated' => 'decimal:2',
        'amount_committed' => 'decimal:2',
        'amount_disbursed' => 'decimal:2',
        'amount_spent' => 'decimal:2',
    ];

    public function consortium(): BelongsTo
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function disbursementRequests(): HasMany
    {
        return $this->hasMany(ConsortiumDisbursementRequest::class, 'fund_allocation_id');
    }
}
