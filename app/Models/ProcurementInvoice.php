<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ProcurementInvoice extends BaseModel
{
    protected $table = 'procurement_invoices';

    protected $fillable = [
        'procurement_id',
        'vendor_id',
        'sub_activity_id',
        'governance_node_id',
        'invoice_month',
        'reference_no',
        'amount',
        'currency',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'invoice_month' => 'date',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    /**
     * Exclude legacy invoices whose originating PO/procurement was deleted.
     */
    public function scopeRecognizedInvoice(Builder $query, ?string $tableAlias = null): Builder
    {
        $table = $tableAlias ?: $this->getTable();

        return $query->where(function (Builder $sourceQuery) use ($table) {
            $sourceQuery
                ->whereExists(function ($exists) use ($table) {
                    $exists
                        ->selectRaw('1')
                        ->from('procurement_purchase_orders as report_invoice_purchase_order')
                        ->whereColumn('report_invoice_purchase_order.invoice_id', "{$table}.id");
                })
                ->orWhereExists(function ($exists) use ($table) {
                    $exists
                        ->selectRaw('1')
                        ->from('procurements as report_invoice_procurement')
                        ->whereColumn('report_invoice_procurement.id', "{$table}.procurement_id");
                })
                ->orWhereExists(function ($exists) use ($table) {
                    $exists
                        ->selectRaw('1')
                        ->from('procurement_purchase_order_item_evidence as report_invoice_evidence')
                        ->whereColumn('report_invoice_evidence.invoice_id', "{$table}.id");
                });
        });
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'procurement_id')->withTrashed();
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function subActivity(): BelongsTo
    {
        return $this->belongsTo(SubActivity::class, 'sub_activity_id');
    }

    public function governanceNode(): BelongsTo
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(ProcurementPurchaseOrder::class, 'invoice_id');
    }

    public function evidence(): HasOne
    {
        return $this->hasOne(ProcurementPurchaseOrderItemEvidence::class, 'invoice_id');
    }

    public function resolvedPurchaseOrder(): ?ProcurementPurchaseOrder
    {
        $this->loadMissing('purchaseOrder', 'evidence.purchaseOrder');

        return $this->purchaseOrder ?: $this->evidence?->purchaseOrder;
    }

    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(
            ProcurementDeliverable::class,
            'procurement_invoice_deliverables',
            'invoice_id',
            'deliverable_id'
        )->withTimestamps();
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'INV-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
        } while (self::where('reference_no', $reference)->exists());

        return $reference;
    }
}
