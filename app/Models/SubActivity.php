<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\GovernanceNode;
use DomainException;
use Illuminate\Support\Facades\DB;

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

    protected static function booted(): void
    {
        static::deleting(function (SubActivity $subActivity): void {
            $subActivity->assertHasNoDependentRecords();
        });
    }

    /**
     * Prevent a budget-structure deletion from silently orphaning posted
     * commitments, procurement records, payments, or operational history.
     */
    public function assertHasNoDependentRecords(): void
    {
        $dependencies = collect($this->dependentRecordCounts())
            ->filter(fn (int $count): bool => $count > 0);

        if ($dependencies->isEmpty()) {
            return;
        }

        $summary = $dependencies
            ->map(fn (int $count, string $label): string => $count.' '.$label)
            ->implode(', ');

        throw new DomainException(
            "This sub-activity cannot be deleted because it is linked to {$summary}. "
            .'Reassign the linked records first so financial and audit history remains intact.'
        );
    }

    /**
     * @return array<string, int>
     */
    public function dependentRecordCounts(): array
    {
        $id = (string) $this->getKey();

        return [
            'budget commitment(s)' => DB::table('myb_budget_commitments')
                ->where('allocation_level', 'sub_activity')
                ->where('allocation_id', $id)
                ->count(),
            'purchase request(s)' => DB::table('myb_purchase_requests')
                ->where('allocation_level', 'sub_activity')
                ->where('allocation_id', $id)
                ->count(),
            'purchase order(s)' => DB::table('procurement_purchase_orders')
                ->where('sub_activity_id', $id)
                ->count(),
            'invoice(s)' => DB::table('procurement_invoices')
                ->where('sub_activity_id', $id)
                ->count(),
            'disbursement(s)' => DB::table('procurement_disbursements')
                ->where('sub_activity_id', $id)
                ->count(),
            'program budget allocation(s)' => DB::table('program_budget_allocations')
                ->where('sub_activity_id', $id)
                ->count(),
            'procurement plan(s)' => DB::table('myb_procurement_plans')
                ->where('sub_activity_id', $id)
                ->count(),
            'activity report(s)' => DB::table('attp_activity_reports')
                ->where('sub_activity_id', $id)
                ->count(),
            'vendor purchase request(s)' => DB::table('vendor_purchase_requests')
                ->where('sub_activity_id', $id)
                ->count(),
            'vendor assignment(s)' => DB::table('vendor_sub_activity_assignments')
                ->where('sub_activity_id', $id)
                ->count(),
        ];
    }

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
