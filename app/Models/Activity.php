<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\GovernanceNode;

class Activity extends BaseModel
{
    protected $table = 'myb_activities';

    protected $fillable = [
        'project_id',
        'governance_node_id',
        'name',
        'description',
        'expected_outcome_type',
        'expected_outcome_value',
        'created_by',
    ];

    /**
     * Relationship: Activity belongs to a Project
     */
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Relationship: Activity belongs to a Governance Node
     */
    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    /**
     * Relationship: Activity has many Allocations
     * Sorted by actual year (correct column name)
     */



    public function allocations()
{
    return $this->hasMany(ActivityAllocation::class, 'activity_id')
        ->orderBy('year', 'asc');   // ONLY THIS
}

    /**
     * Relationship: Activity has Sub Activities
     */
    public function subActivities()
    {
        return $this->hasMany(SubActivity::class, 'activity_id');
    }

    /**
     * Returns list of years the activity covers.
     * Inherits the years from its parent project.
     */
    public function years()
    {
        if (!$this->project) {
            return [];
        }

        $start = $this->project->start_year;
        $end   = $this->project->end_year;

        return range($start, $end);
    }

    /**
     * Total allocated amount for this activity.
     */
    public function totalAllocation()
    {
        return $this->allocations->sum('amount');
    }

    /**
     * Total allocation from all sub-activities combined.
     */
    public function totalFromSubActivities()
    {
        return $this->subActivities->sum(function ($sub) {
            return $sub->totalAllocation();
        });
    }

    /**
     * Yearly breakdown of allocations.
     */
    public function yearlyTotals()
    {
        $totals = [];

        foreach ($this->years() as $year) {
            $totals[$year] = $this->allocations
                                  ->where('year', $year)
                                  ->sum('amount');
        }

        return $totals;
    }
}
