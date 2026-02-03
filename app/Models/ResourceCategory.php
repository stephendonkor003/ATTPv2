<?php

namespace App\Models;

use App\Models\BaseModel;

class ResourceCategory extends BaseModel
{
    protected $table = 'myb_resource_categories';

    protected $fillable = [
        'name',
        'description',
        'governance_node_id',
        'status',
        'created_by',
    ];

    /* =========================
        RELATIONSHIPS
    ========================== */

    public function resources()
    {
        return $this->hasMany(Resource::class, 'resource_category_id');
    }

    public function commitments()
    {
        return $this->hasMany(BudgetCommitment::class, 'resource_category_id');
    }

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* =========================
        SCOPES
    ========================== */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
