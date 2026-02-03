<?php

namespace App\Models;

use App\Models\BaseModel;

class Resource extends BaseModel
{
    protected $table = 'myb_resources';

    protected $fillable = [
        'resource_category_id',
        'governance_node_id',
        'name',
        'reference_code',
        'description',
        'status',
        'is_human_resource',
        'created_by',
    ];

    protected $casts = [
    'is_human_resource' => 'boolean',
    ];


    /* =========================
        RELATIONSHIPS
    ========================== */

    public function category()
    {
        return $this->belongsTo(ResourceCategory::class, 'resource_category_id');
    }

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function commitments()
    {
        return $this->hasMany(BudgetCommitment::class, 'resource_id');
    }

    public function procurements()
    {
        return $this->hasMany(Procurement::class, 'resource_id');
    }

    public function forms()
    {
        return $this->hasMany(DynamicForm::class, 'resource_id');
    }

    /* =========================
        SCOPES
    ========================== */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
