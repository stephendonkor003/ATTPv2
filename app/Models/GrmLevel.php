<?php

namespace App\Models;

class GrmLevel extends BaseModel
{
    protected $fillable = [
        'program_id',
        'governance_node_id',
        'name',
        'slug',
        'color',
        'description',
        'priority',
        'response_due_hours',
        'resolution_due_hours',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'priority' => 'integer',
        'response_due_hours' => 'integer',
        'resolution_due_hours' => 'integer',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function grievances()
    {
        return $this->hasMany(GrmGrievance::class, 'level_id');
    }
}
