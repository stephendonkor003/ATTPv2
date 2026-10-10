<?php

namespace App\Services;

use App\Models\ConsortiumThinkTank;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\Program;
use App\Models\ProgramFunding;
use App\Models\SubActivity;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class ThinkTankFundingSourceService
{
    public const PROGRAM_CODE = 'PROG00001';

    public const COMPONENT_CODE = 'PROG00001-02';

    public const SUB_ACTIVITY_NAME = 'Funding to Think Tanks';

    /** @var array{program: Program, programFunding: ProgramFunding, subActivity: SubActivity}|null */
    private ?array $resolved = null;

    /**
     * Resolve the one authoritative USD funding chain. Ambiguity is treated
     * as unavailable instead of selecting an arbitrary approved envelope.
     *
     * @return array{program: Program, programFunding: ProgramFunding, subActivity: SubActivity}
     */
    public function resolve(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $programs = Program::query()
            ->where('program_id', self::PROGRAM_CODE)
            ->limit(2)
            ->get();
        if ($programs->count() !== 1) {
            throw new RuntimeException('Expected exactly one ATTP program with code '.self::PROGRAM_CODE.'.');
        }
        /** @var Program $program */
        $program = $programs->first();

        $fundings = ProgramFunding::query()
            ->where('program_id', $program->id)
            ->where('status', 'approved')
            ->where('currency', 'USD')
            ->orderByDesc('approved_at')
            ->orderByDesc('approved_amount')
            ->limit(2)
            ->get();
        if ($fundings->count() !== 1) {
            throw new RuntimeException('Expected exactly one approved USD funding envelope for '.self::PROGRAM_CODE.'.');
        }
        /** @var ProgramFunding $programFunding */
        $programFunding = $fundings->first();

        $subActivities = SubActivity::query()
            ->where('name', self::SUB_ACTIVITY_NAME)
            ->whereHas('activity.project', fn ($query) => $query
                ->where('program_id', $program->id)
                ->where('project_id', self::COMPONENT_CODE))
            ->with('activity.project.program')
            ->limit(2)
            ->get();
        if ($subActivities->count() !== 1) {
            throw new RuntimeException(
                'Expected exactly one '.self::SUB_ACTIVITY_NAME.' sub-activity under '.self::COMPONENT_CODE.'.'
            );
        }
        /** @var SubActivity $subActivity */
        $subActivity = $subActivities->first();

        return $this->resolved = compact('program', 'programFunding', 'subActivity');
    }

    /** @return array{program: Program|null, programFunding: ProgramFunding|null, subActivity: SubActivity|null} */
    public function resolveOrNull(): array
    {
        try {
            return $this->resolve();
        } catch (RuntimeException) {
            return [
                'program' => null,
                'programFunding' => null,
                'subActivity' => null,
            ];
        }
    }

    public function historicalAwardCandidatesQuery(): Builder
    {
        $source = $this->resolve();

        return ProcurementPurchaseOrder::query()
            ->where('sub_activity_id', $source['subActivity']->id)
            ->where('currency', 'USD')
            ->where(function (Builder $type): void {
                $type->whereNull('po_type')
                    ->orWhere('po_type', '<>', 'think_tank_transfer');
            })
            ->whereHas('budgetCommitment', fn ($commitment) => $commitment
                ->where('program_funding_id', $source['programFunding']->id));
    }

    public function incomingPurchaseOrdersQuery(?ConsortiumThinkTank $member = null): Builder
    {
        $source = $this->resolve();
        $query = ProcurementPurchaseOrder::query()
            ->where('sub_activity_id', $source['subActivity']->id)
            ->where('currency', 'USD')
            ->whereHas('budgetCommitment', fn ($commitment) => $commitment
                ->where('program_funding_id', $source['programFunding']->id))
            ->where(function (Builder $classification) use ($source): void {
                $classification->where('po_type', 'think_tank_transfer')
                    ->orWhere(function (Builder $historical) use ($source): void {
                        $historical
                            ->where(function (Builder $type): void {
                                $type->whereNull('po_type')
                                    ->orWhere('po_type', '<>', 'think_tank_transfer');
                            })
                            ->whereExists(function ($allocation) use ($source): void {
                                $allocation
                                    ->selectRaw('1')
                                    ->from('attp_fund_allocations as incoming_award_allocations')
                                    ->whereColumn(
                                        'incoming_award_allocations.source_purchase_order_id',
                                        'procurement_purchase_orders.id'
                                    )
                                    ->whereColumn(
                                        'incoming_award_allocations.think_tank_member_id',
                                        'procurement_purchase_orders.think_tank_member_id'
                                    )
                                    ->whereColumn(
                                        'incoming_award_allocations.consortium_id',
                                        'procurement_purchase_orders.consortium_id'
                                    )
                                    ->where('incoming_award_allocations.program_funding_id', $source['programFunding']->id)
                                    ->where('incoming_award_allocations.currency', 'USD');
                            });
                    });
            });

        if ($member) {
            return $query
                ->where('think_tank_member_id', $member->id)
                ->where('consortium_id', $member->consortium_id);
        }

        return $query
            ->whereNotNull('think_tank_member_id')
            ->whereNotNull('consortium_id');
    }

    public function incomingPaymentsQuery(?ConsortiumThinkTank $member = null): Builder
    {
        $source = $this->resolve();
        $query = ProcurementDisbursement::query()
            ->recognizedPayment()
            ->whereIn(
                'purchase_order_id',
                $this->incomingPurchaseOrdersQuery($member)->select('procurement_purchase_orders.id')
            )
            ->where(function (Builder $classification) use ($source): void {
                $classification
                    ->whereHas('purchaseOrder', fn ($purchaseOrder) => $purchaseOrder
                        ->where('po_type', 'think_tank_transfer'))
                    ->orWhereExists(function ($allocation) use ($source): void {
                        $allocation
                            ->selectRaw('1')
                            ->from('attp_fund_allocations as incoming_payment_allocations')
                            ->whereColumn(
                                'incoming_payment_allocations.id',
                                'procurement_disbursements.fund_allocation_id'
                            )
                            ->whereColumn(
                                'incoming_payment_allocations.source_purchase_order_id',
                                'procurement_disbursements.purchase_order_id'
                            )
                            ->whereColumn(
                                'incoming_payment_allocations.think_tank_member_id',
                                'procurement_disbursements.think_tank_member_id'
                            )
                            ->whereColumn(
                                'incoming_payment_allocations.consortium_id',
                                'procurement_disbursements.consortium_id'
                            )
                            ->where('incoming_payment_allocations.program_funding_id', $source['programFunding']->id)
                            ->where('incoming_payment_allocations.currency', 'USD');
                    });
            });

        if ($member) {
            return $query
                ->where('think_tank_member_id', $member->id)
                ->where('consortium_id', $member->consortium_id);
        }

        return $query
            ->whereNotNull('think_tank_member_id')
            ->whereNotNull('consortium_id');
    }
}
