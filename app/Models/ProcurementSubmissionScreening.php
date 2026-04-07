<?php

namespace App\Models;

class ProcurementSubmissionScreening extends BaseModel
{
    protected $fillable = [
        'submission_id',
        'provider',
        'checked_by',
        'checked_via',
        'request_status',
        'entity_name',
        'entity_country',
        'risk_level',
        'total_matches',
        'is_flagged',
        'error_message',
        'last_checked_at',
        'response_payload',
    ];

    protected $casts = [
        'is_flagged' => 'boolean',
        'total_matches' => 'integer',
        'last_checked_at' => 'datetime',
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
}
