<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsortiumDisbursementRequest extends BaseModel
{
    protected $table = 'attp_disbursement_requests';

    protected $fillable = [
        'consortium_id',
        'think_tank_member_id',
        'fund_allocation_id',
        'request_code',
        'amount_requested',
        'amount_approved',
        'currency',
        'status',
        'purpose',
        'requested_by',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'paid_at',
    ];

    protected $casts = [
        'amount_requested' => 'decimal:2',
        'amount_approved' => 'decimal:2',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function consortium(): BelongsTo
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(ConsortiumFundAllocation::class, 'fund_allocation_id');
    }
}
