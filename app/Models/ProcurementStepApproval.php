<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementStepApproval extends BaseModel
{
    protected $table = 'myb_procurement_step_approvals';

    protected $fillable = [
        'name',
        'step_stage_id',
        'governance_node_id',
        'description',
        'approval_order',
        'is_required',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_required' => 'boolean',
        'approval_order' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stepStage(): BelongsTo
    {
        return $this->belongsTo(ProcurementStepStage::class, 'step_stage_id');
    }

    public function governanceNode(): BelongsTo
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('approval_order');
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }
}
