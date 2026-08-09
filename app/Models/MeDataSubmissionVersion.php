<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeDataSubmissionVersion extends BaseModel
{
    protected $table = 'me_data_submission_versions';

    protected $fillable = [
        'submission_id', 'version', 'status', 'schema_snapshot', 'answers_snapshot',
        'evidence_snapshot', 'submitter_notes', 'created_by', 'submitted_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'schema_snapshot' => 'array',
        'answers_snapshot' => 'array',
        'evidence_snapshot' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(MeDataSubmission::class, 'submission_id');
    }
}
