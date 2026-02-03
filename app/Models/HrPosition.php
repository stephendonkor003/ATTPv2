<?php

namespace App\Models;

use App\Models\BaseModel;

class HrPosition extends BaseModel
{
    protected $table = 'hr_positions';

    protected $fillable = [
        'governance_node_id',
        'resource_id',
        'department_id',
        'title',
        'employment_type',
        'grade_level',
        'description',
        'status',
        'created_by',
    ];

    /* =========================
        RELATIONSHIPS
    ========================== */

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vacancies()
    {
        return $this->hasMany(HrVacancy::class, 'position_id');
    }

    public function employees()
    {
        return $this->hasMany(HrEmployee::class, 'position_id');
    }

    /* =========================
        SCOPES
    ========================== */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
