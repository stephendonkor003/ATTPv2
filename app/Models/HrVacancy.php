<?php

namespace App\Models;

use App\Models\BaseModel;

class HrVacancy extends BaseModel
{
    protected $table = 'hr_vacancies';

    protected $fillable = [
        'governance_node_id',
        'position_id',
        'vacancy_code',
        'open_date',
        'close_date',
        'number_of_positions',
        'is_public',
        'status',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'open_date' => 'date',
        'close_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /* =========================
        RELATIONSHIPS
    ========================== */

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function position()
    {
        return $this->belongsTo(HrPosition::class, 'position_id');
    }

    public function applicants()
    {
        return $this->hasMany(HrApplicant::class, 'vacancy_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /* =========================
        SCOPES
    ========================== */

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['draft', 'submitted', 'approved', 'published']);
    }
}