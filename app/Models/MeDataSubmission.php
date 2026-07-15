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
    ];

    protected $casts = [
        'revision' => 'integer',
        'schema_snapshot' => 'array',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
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
        return $this->status === self::STATUS_DRAFT;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isReturned(): bool
    {
        return $this->status === self::STATUS_RETURNED;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isEditable(): bool
    {
        return $this->isDraft() || $this->isReturned();
    }

    public function canSubmit(): bool
    {
        return $this->isEditable();
    }
}
