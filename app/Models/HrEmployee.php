<?php

namespace App\Models;

use App\Models\BaseModel;

class HrEmployee extends BaseModel
{
    protected $table = 'hr_employees';

    protected $fillable = [
        'governance_node_id',
        'applicant_id',
        'position_id',
        'employee_code',
        'employment_start_date',
        'employment_end_date',
        'contract_type',
        'salary',
        'status',
    ];

    protected $casts = [
        'employment_start_date' => 'date',
        'employment_end_date' => 'date',
        'salary' => 'decimal:2',
    ];

    /* =========================
        RELATIONSHIPS
    ========================== */

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function applicant()
    {
        return $this->belongsTo(HrApplicant::class);
    }

    public function position()
    {
        return $this->belongsTo(HrPosition::class);
    }

    /* =========================
        SCOPES
    ========================== */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeCurrentlyEmployed($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('employment_end_date')
                  ->orWhere('employment_end_date', '>=', now());
            });
    }
}
