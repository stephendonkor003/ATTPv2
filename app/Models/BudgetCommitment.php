<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\User;


class BudgetCommitment extends BaseModel
{
    protected $table = 'myb_budget_commitments';
    public $timestamps = false; // ✅ IMPORTANT

    protected $fillable = [
        'purchase_request_id',
        'program_funding_id',
        'governance_node_id',
        'allocation_level',
        'allocation_id',
        'resource_category_id',
        'resource_id',
        'commitment_amount',
        'commitment_year',
        'status',
        'description',
        'rejection_reason',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    /* ==========================
     * STATUS CONSTANTS
     * ========================== */

    const STATUS_DRAFT     = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED  = 'approved';
    const STATUS_CANCELLED = 'cancelled';

    /* ==========================
     * RELATIONSHIPS
     * ========================== */

    public function programFunding()
    {
        return $this->belongsTo(ProgramFunding::class, 'program_funding_id');
    }

    public function purchaseRequest()
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function resourceCategory()
    {
        return $this->belongsTo(ResourceCategory::class, 'resource_category_id');
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    /* ==========================
     * DYNAMIC ALLOCATION TARGET
     * ========================== */

    public function allocation()
    {
        return match ($this->allocation_level) {
            'project'      => $this->belongsTo(Project::class, 'allocation_id'),
            'activity'     => $this->belongsTo(Activity::class, 'allocation_id'),
            'sub_activity' => $this->belongsTo(SubActivity::class, 'allocation_id'),
            default        => null,
        };
    }

    /* ==========================
     * HELPERS
     * ========================== */

    public function isApproved()
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isDraft()
    {
        return $this->status === self::STATUS_DRAFT;
    }



    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function approvedWorkPlans()
    {
        return $this->hasMany(ApprovedWorkPlan::class, 'budget_commitment_id');
    }
}
