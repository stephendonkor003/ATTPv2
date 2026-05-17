<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThinkTankProcurementReview extends BaseModel
{
    protected $table = 'attp_think_tank_procurement_reviews';

    protected $fillable = [
        'procurement_id',
        'form_submission_id',
        'think_tank_member_id',
        'reviewed_by',
        'technical_score',
        'financial_score',
        'total_score',
        'recommendation',
        'comments',
        'reviewed_at',
    ];

    protected $casts = [
        'technical_score' => 'decimal:2',
        'financial_score' => 'decimal:2',
        'total_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
