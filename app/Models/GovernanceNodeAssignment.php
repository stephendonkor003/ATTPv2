<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class GovernanceNodeAssignment extends BaseModel
{
    protected $table = 'myb_governance_node_assignments';

    protected $fillable = [
        'node_id',
        'user_id',
        'role_title',
        'is_primary',
        'effective_start',
        'effective_end',
        'created_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'effective_start' => 'date',
        'effective_end' => 'date',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(GovernanceNode::class, 'node_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
