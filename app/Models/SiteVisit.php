<?php

namespace App\Models;

use App\Models\BaseModel;

class SiteVisit extends BaseModel
{
    protected $fillable = [
        'procurement_id',
        'form_submission_id',
        'assignment_type',
        'visit_type',
        'visit_date',
        'status',
        'created_by',
        'assigned_by',
    ];

    protected $casts = [
        'visit_date' => 'date',
    ];

    /* =======================
     | Relationships
     ======================= */

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignment()
    {
        return $this->hasOne(SiteVisitAssignment::class);
    }

    public function group()
    {
        return $this->hasOne(SiteVisitGroup::class);
    }

    public function observations()
    {
        return $this->hasMany(SiteVisitObservation::class);
    }

    public function media()
    {
        return $this->hasMany(SiteVisitMedia::class);
    }

    public function approvals()
    {
        return $this->hasMany(SiteVisitApproval::class);
    }
}