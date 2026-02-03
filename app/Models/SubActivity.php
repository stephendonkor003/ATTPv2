<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\GovernanceNode;

class SubActivity extends BaseModel
{
    protected $table = 'myb_sub_activities';

    protected $fillable = [
        'activity_id',
        'governance_node_id',
        'name',
        'description',
        'expected_outcome_type',
        'expected_outcome_value',
        'created_by',
    ];

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function allocations()
    {
        return $this->hasMany(SubActivityAllocation::class, 'sub_activity_id');
    }




    public function years()
    {
        return $this->activity->years();
    }

    public function totalAllocation()
    {
        return $this->allocations->sum('amount');
    }

    public function yearlyTotals()
{
    $years = $this->years();
    $totals = [];

    foreach ($years as $year) {
        $totals[$year] = $this->allocations->where('year', $year)->sum('amount');
    }

    return $totals;
}

}
