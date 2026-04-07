<?php

namespace App\Models;

class ProcurementSubmissionScreening extends BaseModel
{
    protected $fillable = [
        'submission_id',
        'provider',
        'checked_by',
        'reviewed_by',
        'checked_via',
        'request_status',
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
        'last_checked_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'response_payload' => 'array',
    ];

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
