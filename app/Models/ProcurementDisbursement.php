<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProcurementDisbursement extends BaseModel
{
    public const PROCUREMENT_STATUS_PENDING = 'pending_procurement_review';
    public const PROCUREMENT_STATUS_COMPLETED = 'goods_receipt_recorded';

    protected $table = 'procurement_disbursements';

    protected $fillable = [
        'purchase_order_id',
        'purchase_request_item_id',
        'deliverable_id',
        'procurement_id',
        'vendor_id',
        'sub_activity_id',
        'governance_node_id',
        'consortium_id',
        'think_tank_member_id',
        'fund_allocation_id',
        'consortium_disbursement_request_id',
        'reference_no',
        'amount',
        'currency',
        'payment_method',
        'transfer_reference',
        'status',
        'recipient_confirmation_status',
        'recipient_confirmed_by',
        'recipient_confirmed_at',
        'recipient_confirmation_notes',
        'paid_at',
        'created_by',
        'notes',
        'signed_documents',
        'procurement_processing_status',
        'procurement_notified_at',
        'goods_receipt_reference',
        'goods_receipt_generated_at',
        'goods_receipt_generated_by',
        'sap_52_series_reference',
        'sap_52_series_entered_at',
        'sap_52_series_entered_by',
        'procurement_processing_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'recipient_confirmed_at' => 'datetime',
        'signed_documents' => 'array',
        'procurement_notified_at' => 'datetime',
        'goods_receipt_generated_at' => 'datetime',
        'sap_52_series_entered_at' => 'datetime',
    ];

    /**
     * Limit financial reports to completed payments backed by a live source.
     *
     * Legacy PO deletion used to leave paid rows attached only to a
     * sub-activity. Those rows remain available for audit, but a classification
     * alone is not evidence that a payment is still financially reportable.
     */
    public function scopeRecognizedPayment(Builder $query, ?string $tableAlias = null): Builder
    {
        $table = $tableAlias ?: $this->getTable();

        return $query
            ->whereNotNull("{$table}.paid_at")
            ->whereIn("{$table}.status", ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES)
            ->where(function (Builder $sourceQuery) use ($table) {
                $sourceQuery
                    ->whereExists(function ($exists) use ($table) {
                        $exists
                            ->selectRaw('1')
                            ->from('procurement_purchase_orders as report_purchase_order')
                            ->whereColumn('report_purchase_order.id', "{$table}.purchase_order_id");
                    })
                    ->orWhereExists(function ($exists) use ($table) {
                        $exists
                            ->selectRaw('1')
                            ->from('procurements as report_procurement')
                            ->whereColumn('report_procurement.id', "{$table}.procurement_id");
                    })
                    ->orWhereExists(function ($exists) use ($table) {
                        $exists
                            ->selectRaw('1')
                            ->from('attp_fund_allocations as report_fund_allocation')
                            ->whereColumn('report_fund_allocation.id', "{$table}.fund_allocation_id");
                    })
                    ->orWhereExists(function ($exists) use ($table) {
                        $exists
                            ->selectRaw('1')
                            ->from('attp_disbursement_requests as report_disbursement_request')
                            ->whereColumn('report_disbursement_request.id', "{$table}.consortium_disbursement_request_id");
                    });
            });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementPurchaseOrder::class, 'purchase_order_id');
    }

    public function getResolvedCurrencyAttribute(): string
    {
        $this->loadMissing([
            'purchaseOrder.purchaseRequest.programFunding.program',
            'purchaseOrder.budgetCommitment.programFunding.program',
            'purchaseOrder.budgetCommitment.purchaseRequest.programFunding.program',
        ]);

        return $this->purchaseOrder?->resolved_currency
            ?: $this->currency
            ?: 'USD';
    }

    public function getProcurementProcessingStatusLabelAttribute(): string
    {
        return match ($this->procurement_processing_status ?: self::PROCUREMENT_STATUS_PENDING) {
            self::PROCUREMENT_STATUS_COMPLETED => 'Goods Receipt Recorded',
            default => 'Pending Procurement Review',
        };
    }

    public function isProcurementProcessingComplete(): bool
    {
        return ($this->procurement_processing_status === self::PROCUREMENT_STATUS_COMPLETED)
            || filled($this->goods_receipt_reference)
            || filled($this->sap_52_series_reference);
    }

    public function isAwaitingProcurementProcessing(): bool
    {
        return ! $this->isProcurementProcessingComplete();
    }

    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(ProcurementDeliverable::class, 'deliverable_id');
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function consortium(): BelongsTo
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }

    public function thinkTankMember(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function fundAllocation(): BelongsTo
    {
        return $this->belongsTo(ConsortiumFundAllocation::class, 'fund_allocation_id');
    }

    public function consortiumDisbursementRequest(): BelongsTo
    {
        return $this->belongsTo(ConsortiumDisbursementRequest::class, 'consortium_disbursement_request_id');
    }

    public function recipientConfirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_confirmed_by');
    }

    public function subActivity(): BelongsTo
    {
        return $this->belongsTo(SubActivity::class, 'sub_activity_id');
    }

    public function governanceNode(): BelongsTo
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'RCPT-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
        } while (self::where('reference_no', $reference)->exists());

        return $reference;
    }
}
