<?php

namespace App\Models;

class ProcurementSubmissionScreening extends BaseModel
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_WAITING = 'waiting';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_ERROR = 'error';

    public const ACTIVE_STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_RETRYING,
    ];

    protected $fillable = [
        'submission_id',
        'run_token',
        'submission_fingerprint',
        'provider',
        'checked_by',
        'reviewed_by',
        'checked_via',
        'request_status',
        'attempt_count',
        'retryable',
        'queued_at',
        'processing_started_at',
        'request_started_at',
        'next_retry_at',
        'review_decision',
        'entity_name',
        'entity_country',
        'risk_level',
        'total_matches',
        'is_flagged',
        'error_message',
        'review_notes',
        'last_checked_at',
        'reviewed_at',
        'response_payload',
    ];

    protected $casts = [
        'is_flagged' => 'boolean',
        'total_matches' => 'integer',
        'attempt_count' => 'integer',
        'retryable' => 'boolean',
        'queued_at' => 'datetime',
        'processing_started_at' => 'datetime',
        'request_started_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'response_payload' => 'array',
    ];

    public function isActive(): bool
    {
        return in_array($this->request_status, self::ACTIVE_STATUSES, true);
    }

    public function completedSuccessfully(): bool
    {
        return $this->request_status === self::STATUS_SUCCESS;
    }

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
