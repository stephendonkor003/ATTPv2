<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeDataQualityFinding extends BaseModel
{
    protected $table = 'me_data_quality_findings';

    protected $fillable = [
        'submission_id', 'indicator_result_id', 'rule_code', 'severity', 'field_key',
        'message', 'context', 'status', 'resolution_notes', 'resolved_by', 'resolved_at',
    ];

    protected $casts = ['context' => 'array', 'resolved_at' => 'datetime'];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(MeDataSubmission::class, 'submission_id');
    }

    public function indicatorResult(): BelongsTo
    {
        return $this->belongsTo(IndicatorResult::class, 'indicator_result_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
