<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteVisitActivity extends BaseModel
{
    protected $fillable = [
        'website_visit_id',
        'activity_type',
        'url',
        'path',
        'title',
        'referrer',
        'duration_seconds',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(WebsiteVisit::class, 'website_visit_id');
    }
}
