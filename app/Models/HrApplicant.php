<?php

namespace App\Models;

use App\Models\BaseModel;

class HrApplicant extends BaseModel
{
    protected $table = 'hr_applicants';

    public $timestamps = false;

    protected $fillable = [
        'governance_node_id',
        'vacancy_id',
        'full_name',
        'email',
        'phone',
        'gender',
        'nationality',
        'cv_path',
        'cover_letter_path',
        'status',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    /* =========================
        RELATIONSHIPS
    ========================== */

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function vacancy()
    {
        return $this->belongsTo(HrVacancy::class, 'vacancy_id');
    }

    public function employee()
    {
        return $this->hasOne(HrEmployee::class, 'applicant_id');
    }

    public function shortlist()
    {
        return $this->hasOne(HrShortlist::class, 'applicant_id');
    }

    /* =========================
        SCOPES
    ========================== */

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeShortlisted($query)
    {
        return $query->where('status', 'shortlisted');
    }
}