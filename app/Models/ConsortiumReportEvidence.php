<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsortiumReportEvidence extends BaseModel
{
    protected $table = 'attp_report_evidence';

    protected $fillable = [
        'activity_report_id',
        'uploaded_by',
        'title',
        'evidence_type',
        'file_path',
        'file_name',
        'mime_type',
        'file_size_bytes',
        'external_url',
        'visibility',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(ConsortiumActivityReport::class, 'activity_report_id');
    }
}
