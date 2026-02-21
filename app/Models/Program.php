<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\GovernanceNode;

class Program extends BaseModel
{
    protected $table = 'myb_programs';

    protected $fillable = [
    'program_id',
    'sector_id',
    'department_id',
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


    public function sector()
    {
        return $this->belongsTo(Sector::class, 'sector_id');
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'program_id');
    }

    // Helper to generate list of years
    public function years()
    {
        return range($this->start_year, $this->end_year);
    }


    // Program belongs to a Department
public function department()
{
    return $this->belongsTo(Department::class, 'department_id');
}

public function governanceNode()
{
    return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
}

// Program has many funding approvals
public function fundings()
{
    return $this->hasMany(ProgramFunding::class, 'program_id');
}

// Approved funding only (important for budgeting)
public function approvedFundings()
{
    return $this->hasMany(ProgramFunding::class, 'program_id')
                ->where('status', 'approved');
}

    public function indicators()
    {
        return $this->morphMany(Indicator::class, 'indicatorable');
    }

    /**
     * Total allocations under this program
     * (projects + activities + sub-activities)
     */
    public function totalAllocatedAmount()
    {
        return $this->projects->sum(function ($project) {

        $projectAllocations = $project->allocations->sum('amount');

        $activityAllocations = $project->activities->sum(function ($activity) {
            return $activity->allocations->sum('amount');
        });

        $subActivityAllocations = $project->activities->sum(function ($activity) {
            return $activity->subActivities->sum(function ($sub) {
                return $sub->allocations->sum('amount');
            });
        });

        return $projectAllocations + $activityAllocations + $subActivityAllocations;
    });
}


}
