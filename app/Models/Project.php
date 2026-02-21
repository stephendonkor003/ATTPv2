<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\GovernanceNode;

class Project extends BaseModel
{
    protected $table = 'myb_projects';

    protected $fillable = [
        'program_id',
        'project_id',
        'governance_node_id',
        'name',
        'description',
        'expected_outcome_type',
        'expected_outcome_value',
        'currency',
        'start_year',
        'end_year',
        'total_years',
        'total_budget',
        'created_by',
    ];

    /****************************************
     * RELATIONSHIPS
     ****************************************/

    // Each Project belongs to a Program
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    // Each Project belongs to a Governance Node
    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function sector()
    {
        return $this->belongsTo(\App\Models\Sector::class, 'sector_id');
    }


    // A Project has many Yearly Allocations
    public function allocations()
    {
        return $this->hasMany(ProjectAllocation::class, 'project_id');
    }

    // A Project has many Activities
    public function activities()
    {
        return $this->hasMany(Activity::class, 'project_id');
    }

    // A Project has many Indicators (polymorphic)
    public function indicators()
    {
        return $this->morphMany(Indicator::class, 'indicatorable');
    }

    // A Project has many Activity Allocations (via activities)
    public function activityAllocations()
    {
        return $this->hasManyThrough(
            ActivityAllocation::class,
            Activity::class,
            'project_id',    // FK on activities
            'activity_id',   // FK on activity allocations
            'id',            // Local key on projects
            'id'             // Local key on activities
        );
    }


    /****************************************
     * YEAR UTILITIES
     ****************************************/

    // Returns an array of actual project years → [2025, 2026, 2027]
    public function years()
    {
        return range($this->start_year, $this->end_year);
    }

    // Returns allocated amounts indexed by year → [2025 => 5000, 2026 => 7000]
    public function allocationsByYear()
    {
        return $this->allocations->pluck('amount', 'year')->toArray();
    }


    /****************************************
     * TOTAL AND SUMMARY HELPERS
     ****************************************/

    // Total allocated amount from project-level allocations
    public function totalAllocated()
    {
        return $this->allocations->sum('amount');
    }

    // Total allocated amount from activities
    public function totalActivityAllocated()
    {
        return $this->activityAllocations->sum('amount');
    }

    // Total activities under project
    public function totalActivities()
    {
        return $this->activities->count();
    }

    // Remaining budget from project-level allocations
    public function remainingBudget()
    {
        return $this->total_budget - $this->totalAllocated();
    }

    // Remaining budget including activities
    public function remainingBudgetOverall()
    {
        return $this->total_budget - ($this->totalAllocated() + $this->totalActivityAllocated());
    }


    public function commitments()
    {
        return $this->hasMany(BudgetCommitment::class, 'allocation_id')
            ->where('allocation_level', 'project');
    }


    public function totalAllocation(): float
    {
        return \DB::table('myb_project_allocations')
            ->where('project_id', $this->id)
            ->sum('amount');
    }



}
