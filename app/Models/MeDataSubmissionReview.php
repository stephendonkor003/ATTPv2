<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeDataSubmissionReview extends BaseModel
{
    protected $table = 'me_data_submission_reviews';

    protected $fillable = [
        'submission_id', 'submission_version', 'from_status', 'to_status',
        'action', 'comments', 'metadata', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'submission_version' => 'integer',
        'metadata' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(MeDataSubmission::class, 'submission_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
