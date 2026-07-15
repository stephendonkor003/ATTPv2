<?php

namespace App\Models;

class GrmEscalationRule extends BaseModel
{
    protected $fillable = [
        'program_id',
        'governance_node_id',
        'level_id',
        'response_due_hours',
        'resolution_due_hours',
        'reminder_after_hours',
        'reminder_interval_hours',
        'escalate_after_hours',
        'escalation_email',
        'auto_response_subject',
        'auto_response_body',
        'reminder_subject',
        'reminder_body',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'response_due_hours' => 'integer',
        'resolution_due_hours' => 'integer',
        'reminder_after_hours' => 'integer',
        'reminder_interval_hours' => 'integer',
        'escalate_after_hours' => 'integer',
        'is_active' => 'boolean',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function level()
    {
        return $this->belongsTo(GrmLevel::class, 'level_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
