<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\GovernanceNode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function transferActivityBudgetTo(Activity $activity, Project $targetProject, bool $save = true): float
    {
        $activityAllocations = $activity->relationLoaded('allocations')
            ? $activity->getRelation('allocations')
            : $activity->allocations()->get();

        $subActivities = $activity->relationLoaded('subActivities')
            ? $activity->getRelation('subActivities')
            : $activity->subActivities()->get();

        $yearlyTransfers = [];

        foreach ($activityAllocations as $allocation) {
            $year = (int) ($allocation->year ?? 0);
            if ($year <= 0) {
                continue;
            }

            $yearlyTransfers[$year] = ($yearlyTransfers[$year] ?? 0.0) + (float) $allocation->amount;
        }

        foreach ($subActivities as $subActivity) {
            $subActivityAllocations = $subActivity->relationLoaded('allocations')
                ? $subActivity->getRelation('allocations')
                : $subActivity->allocations()->get();

            foreach ($subActivityAllocations as $allocation) {
                $year = (int) ($allocation->year ?? 0);
                if ($year <= 0) {
                    continue;
                }

                $yearlyTransfers[$year] = ($yearlyTransfers[$year] ?? 0.0) + (float) $allocation->amount;
            }
        }

        $transferAmount = round(array_sum($yearlyTransfers), 2);

        if ($transferAmount <= 0) {
            return 0.0;
        }

        $this->total_budget = round(max(0, (float) $this->total_budget - $transferAmount), 2);
        $targetProject->total_budget = round((float) $targetProject->total_budget + $transferAmount, 2);

        if ($save) {
            foreach ($yearlyTransfers as $year => $amount) {
                $this->adjustProjectAllocationForYear($year, -$amount);
                $targetProject->adjustProjectAllocationForYear($year, $amount);
            }

            $this->save();
            $targetProject->save();
        }

        return $transferAmount;
    }

    protected function adjustProjectAllocationForYear(int $year, float $amount): void
    {
        if ($amount == 0.0) {
            return;
        }

        $allocation = DB::table('myb_project_allocations')
            ->where('project_id', $this->id)
            ->where('year', $year)
            ->first();

        if ($allocation) {
            DB::table('myb_project_allocations')
                ->where('id', $allocation->id)
                ->update([
                    'amount' => round((float) $allocation->amount + $amount, 2),
                ]);

            return;
        }

        DB::table('myb_project_allocations')->insert([
            'id' => Str::uuid()->toString(),
            'project_id' => $this->id,
            'year' => $year,
            'year_number' => 1,
            'actual_year' => $year,
            'amount' => round($amount, 2),
        ]);
    }

    public function commitments()
    {
        return $this->hasMany(BudgetCommitment::class, 'allocation_id')
            ->where('allocation_level', 'project');
    }


    public function totalAllocation(): float
    {
        return DB::table('myb_project_allocations')
            ->where('project_id', $this->id)
            ->sum('amount');
    }



}
