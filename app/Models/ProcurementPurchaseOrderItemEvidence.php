<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPurchaseOrderItemEvidence extends BaseModel
{
    protected $table = 'procurement_purchase_order_item_evidence';

    protected $fillable = [
        'purchase_order_id',
        'purchase_request_item_id',
        'deliverable_id',
        'is_met',
        'deliverable_date',
        'delivered_unit_price',
        'delivered_quantity',
        'delivered_amount',
        'notes',
        'documents',
        'created_by',
    ];

    protected $casts = [
        'is_met' => 'boolean',
        'deliverable_date' => 'date',
        'delivered_unit_price' => 'decimal:2',
        'delivered_quantity' => 'decimal:2',
        'delivered_amount' => 'decimal:2',
        'documents' => 'array',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementPurchaseOrder::class, 'purchase_order_id');
    }

    public function purchaseRequestItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(ProcurementDeliverable::class, 'deliverable_id');
    }
}
