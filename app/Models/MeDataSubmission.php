<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeDataSubmission extends BaseModel
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_RETURNED = 'returned';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RESUBMITTED = 'resubmitted';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'me_data_submissions';

    protected $fillable = [
        'assignment_id',
        'revision',
        'status',
        'schema_snapshot',
        'notes',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'workflow_status',
        'current_version',
        'under_review_by',
        'under_review_at',
        'verified_by',
        'verified_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
    ];

    protected $casts = [
        'revision' => 'integer',
        'schema_snapshot' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'current_version' => 'integer',
        'under_review_at' => 'datetime',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MeDataCollectionAssignment::class, 'assignment_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(MeDataSubmissionAnswer::class, 'submission_id');
    }

    public function indicatorResults(): HasMany
    {
        return $this->hasMany(IndicatorResult::class, 'data_submission_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MeDataSubmissionVersion::class, 'submission_id')->orderByDesc('version');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(MeDataSubmissionReview::class, 'submission_id')->latest('reviewed_at');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(MeSubmissionEvidence::class, 'submission_id');
    }

    public function dataQualityFindings(): HasMany
    {
        return $this->hasMany(MeDataQualityFinding::class, 'submission_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isDraft(): bool
    {
        return $this->effectiveStatus() === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return in_array($this->effectiveStatus(), [self::STATUS_SUBMITTED, self::STATUS_RESUBMITTED], true);
    }

    public function isReturned(): bool
    {
        return $this->effectiveStatus() === self::STATUS_RETURNED;
    }

    public function isValidated(): bool
    {
        return in_array($this->effectiveStatus(), [self::STATUS_VALIDATED, self::STATUS_VERIFIED], true);
    }

    public function isApproved(): bool
    {
        return $this->effectiveStatus() === self::STATUS_APPROVED;
    }

    public function isEditable(): bool
    {
        return $this->isDraft() || $this->isReturned();
    }

    public function canSubmit(): bool
    {
        return $this->isEditable();
    }

    public function effectiveStatus(): string
    {
        return (string) ($this->workflow_status ?: $this->status ?: self::STATUS_DRAFT);
    }
}
