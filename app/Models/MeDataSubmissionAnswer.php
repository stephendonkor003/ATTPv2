<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeDataSubmissionAnswer extends BaseModel
{
    protected $table = 'me_data_submission_answers';

    protected $fillable = [
        'submission_id',
        'field_id',
        'field_key',
        'value',
        'indicator_result_id',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(MeDataSubmission::class, 'submission_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(MeDataEntryFormField::class, 'field_id');
    }

    public function indicatorResult(): BelongsTo
    {
        return $this->belongsTo(IndicatorResult::class, 'indicator_result_id');
    }
}
