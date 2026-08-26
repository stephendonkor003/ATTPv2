<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;

class FormSubmission extends BaseModel
{
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVISION_REQUESTED = 'revision_requested';
    public const STATUS_WITHDRAWN = 'withdrawn';
    public const STATUS_EOI_EVALUATION = 'eoi_evaluation';
    public const STATUS_EOI_NOT_QUALIFIED = 'eoi_not_qualified';
    public const STATUS_TECHNICAL_EVALUATION = 'technical_evaluation';

    /**
     * Application states in which a new or draft evaluation must not proceed.
     * Every other state remains visible because procurement workflows continue
     * through states such as prescreen_passed, evaluated and site_visit_completed.
     */
    public const EVALUATION_BLOCKED_STATUSES = [
        'draft',
        self::STATUS_REVISION_REQUESTED,
        self::STATUS_WITHDRAWN,
        'prescreen_failed',
        self::STATUS_EOI_NOT_QUALIFIED,
    ];

    protected $fillable = [
        'procurement_id',
        'procurement_submission_code',
        'form_id',
        'submitted_by',
        'assigned_prescreener_id',
        'status',
        'submitted_at',
        'publication_version',
        'vendor_response',
        'resubmitted_at',
        'withdrawn_at',
        'withdrawal_reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'publication_version' => 'integer',
        'resubmitted_at' => 'datetime',
        'withdrawn_at' => 'datetime',
    ];

    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN;
    }

    public function isAvailableForEvaluation(): bool
    {
        return $this->status === null
            || ! in_array($this->status, self::EVALUATION_BLOCKED_STATUSES, true);
    }

    public function scopeAvailableForEvaluation(Builder $query): Builder
    {
        return $query->where(function (Builder $availability): void {
            $availability->whereNull('status')
                ->orWhereNotIn('status', self::EVALUATION_BLOCKED_STATUSES);
        });
    }

    public function procurement()
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
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

public function getDisplayNameAttribute(): string
{
    $preferredFields = ['official_name', 'consortium_name', 'think_tank_name'];
    $values = $this->relationLoaded('values')
        ? $this->values
        : $this->values()->whereIn('field_key', $preferredFields)->get(['field_key', 'value']);

    foreach ($preferredFields as $field) {
        $value = trim((string) ($values->firstWhere('field_key', $field)?->value ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    if ($this->relationLoaded('submitter')) {
        $name = trim((string) ($this->submitter?->name ?? ''));
        if ($name !== '') {
            return $name;
        }
    }

    return $this->procurement_submission_code ?: 'Submission';
}

// FormSubmission.php
public function assignedPrescreener()
{
    return $this->belongsTo(User::class, 'assigned_prescreener_id');
}




}
