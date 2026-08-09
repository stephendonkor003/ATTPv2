<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeSubmissionEvidence extends BaseModel
{
    public const TYPES = [
        'report' => 'Report', 'attendance_list' => 'Attendance list', 'agenda' => 'Agenda',
        'meeting_minutes' => 'Meeting minutes', 'photo' => 'Photo', 'publication' => 'Publication',
        'policy_document' => 'Policy document', 'certificate' => 'Certificate', 'survey' => 'Survey',
        'official_letter' => 'Official letter', 'tor' => 'Terms of reference', 'workplan' => 'Workplan',
        'database_export' => 'Database export', 'other' => 'Other',
    ];

    protected $table = 'me_submission_evidence';

    protected $fillable = [
        'submission_id', 'indicator_id', 'reporting_period_id', 'think_tank_member_id',
        'answer_id', 'evidence_type', 'document_title', 'description', 'file_path',
        'original_name', 'mime_type', 'file_size', 'checksum', 'url',
        'verification_status', 'uploaded_by', 'verified_by', 'verified_at',
        'verification_notes',
    ];

    protected $casts = ['file_size' => 'integer', 'verified_at' => 'datetime'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(MeDataSubmission::class, 'submission_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    public function reportingPeriod(): BelongsTo
    {
        return $this->belongsTo(MeReportingPeriod::class, 'reporting_period_id');
    }

    public function thinkTank(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }
}
