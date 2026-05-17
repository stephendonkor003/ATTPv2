<?php

namespace App\Models;

use App\Models\BaseModel;

class FormSubmission extends BaseModel
{
    protected $fillable = [
        'procurement_id',
        'procurement_submission_code',
        'form_id',
        'submitted_by',
        'assigned_prescreener_id',
        'status',
        'submitted_at'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function procurement()
    {
        return $this->belongsTo(Procurement::class);
    }

    public function form()
    {
        return $this->belongsTo(DynamicForm::class);
    }

    public function values()
    {
        return $this->hasMany(FormSubmissionValue::class, 'submission_id');
    }

public function submitter()
{
    return $this->belongsTo(User::class, 'submitted_by');
}

public function screening()
{
    return $this->hasOne(ProcurementSubmissionScreening::class, 'submission_id');
}





    protected static function booted()
    {
        static::creating(function ($submission) {
            if (empty($submission->procurement_submission_code)) {
                $submission->procurement_submission_code =
                    'PROC-' .
                    now()->format('Y') . '-' .
                    strtoupper(\Illuminate\Support\Str::random(8));
            }
        });
    }


 public function prescreeningEvaluations()
{
    return $this->hasMany(PrescreeningEvaluation::class, 'submission_id');
}

public function prescreeningResult()
{
    return $this->hasOne(PrescreeningResult::class, 'submission_id');
}



public function evaluationSubmissions()
{
        return $this->hasMany(
            EvaluationSubmission::class,
            'form_submission_id'
        );
}

public function thinkTankReview()
{
    return $this->hasOne(ThinkTankProcurementReview::class, 'form_submission_id');
}

public function siteVisits()
{
    return $this->hasMany(SiteVisit::class, 'form_submission_id');
}

// FormSubmission.php
public function assignedPrescreener()
{
    return $this->belongsTo(User::class, 'assigned_prescreener_id');
}




}
