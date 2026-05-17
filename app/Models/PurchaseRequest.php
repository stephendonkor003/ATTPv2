<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends BaseModel
{
    protected $table = 'myb_purchase_requests';

    protected $fillable = [
        'reference_no',
        'program_funding_id',
        'governance_node_id',
        'allocation_level',
        'allocation_id',
        'start_year',
        'commitment_date',
        'delivery_date',
        'currency',
        'total_amount',
        'description',
        'status',
        'work_plan_source',
        'work_plan_component',
        'work_plan_sub_component',
        'created_by',
    ];

    protected $casts = [
        'start_year' => 'integer',
        'total_amount' => 'decimal:2',
        'commitment_date' => 'date',
        'delivery_date' => 'date',
    ];

    public function programFunding(): BelongsTo
    {
        return $this->belongsTo(ProgramFunding::class, 'program_funding_id');
    }

    public function governanceNode(): BelongsTo
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function subActivity(): BelongsTo
    {
        return $this->belongsTo(SubActivity::class, 'allocation_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class, 'purchase_request_id');
    }

    public function commitments(): HasMany
    {
        return $this->hasMany(BudgetCommitment::class, 'purchase_request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
