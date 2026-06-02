<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThinkTankResearchOutput extends BaseModel
{
    protected $table = 'attp_think_tank_research_outputs';

    protected $fillable = [
        'consortium_id',
        'think_tank_member_id',
        'title',
        'output_type',
        'published_on',
        'status',
        'abstract',
        'file_path',
        'external_url',
        'qasc_data',
        'qasc_author_signature_path',
        'qasc_think_tank_signature_path',
        'qasc_pdf_path',
        'qasc_email_sent_at',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'published_on' => 'date',
        'qasc_data' => 'array',
        'qasc_email_sent_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function consortium(): BelongsTo
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
