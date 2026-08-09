<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeDataGovernanceAction extends BaseModel
{
    protected $table = 'me_data_governance_actions';

    protected $fillable = [
        'control_id', 'action_type', 'title', 'description', 'priority', 'status',
        'owner_user_id', 'due_date', 'resolution_notes', 'created_by', 'updated_by',
        'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    public function control(): BelongsTo
    {
        return $this->belongsTo(MeDataGovernanceControl::class, 'control_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'in_progress'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date?->isPast();
    }
}
