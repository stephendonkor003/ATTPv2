<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovedWorkPlan extends BaseModel
{
    protected $table = 'approved_work_plans';

    protected $fillable = [
        'awp_code',
        'title',
        'budget_commitment_id',
        'program_funding_id',
        'governance_node_id',
        'fiscal_year',
        'planned_amount',
        'currency',
        'start_date',
        'end_date',
        'status',
        'description',
        'expected_outputs',
        'implementation_notes',
        'created_by',
        'approved_by',
        'approved_at',
        'review_notes',
    ];

    protected $casts = [
        'planned_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function budgetCommitment(): BelongsTo
    {
        return $this->belongsTo(BudgetCommitment::class, 'budget_commitment_id');
    }

    public function programFunding(): BelongsTo
    {
        return $this->belongsTo(ProgramFunding::class, 'program_funding_id');
    }

    public function governanceNode(): BelongsTo
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
