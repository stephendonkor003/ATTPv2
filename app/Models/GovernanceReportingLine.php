<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovernanceReportingLine extends BaseModel
{
    protected $table = 'myb_governance_reporting_lines';

    protected $fillable = [
        'child_node_id',
        'parent_node_id',
        'line_type',
        'effective_start',
        'effective_end',
        'created_by',
    ];

    protected $casts = [
        'effective_start' => 'date',
        'effective_end' => 'date',
    ];

    public function child(): BelongsTo
    {
        return $this->belongsTo(GovernanceNode::class, 'child_node_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(GovernanceNode::class, 'parent_node_id');
    }
}
