<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeDataGovernanceControl extends BaseModel
{
    protected $table = 'me_data_governance_controls';

    protected $fillable = [
        'control_code', 'title', 'governance_domain', 'instrument_type', 'version',
        'scope_type', 'portfolio_id', 'think_tank_member_id', 'owner_user_id',
        'steward_user_id', 'data_classification', 'risk_rating', 'status',
        'implementation_status', 'review_frequency', 'effective_date',
        'next_review_date', 'description', 'requirements', 'evidence_notes',
        'evidence_repository_item_id', 'created_by', 'updated_by', 'approved_by',
        'approved_at', 'retired_by', 'retired_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'next_review_date' => 'date',
        'approved_at' => 'datetime',
        'retired_at' => 'datetime',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'portfolio_id');
    }

    public function thinkTank(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function steward(): BelongsTo
    {
        return $this->belongsTo(User::class, 'steward_user_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(MeKnowledgeEvidenceItem::class, 'evidence_repository_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(MeDataGovernanceAction::class, 'control_id');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'under_review'], true);
    }

    public function reviewState(): string
    {
        if ($this->status === 'retired') {
            return 'retired';
        }
        if (! $this->next_review_date) {
            return 'unscheduled';
        }
        if ($this->next_review_date->isPast()) {
            return 'overdue';
        }
        if ($this->next_review_date->lte(today()->addDays(30))) {
            return 'due_soon';
        }

        return 'scheduled';
    }
}
