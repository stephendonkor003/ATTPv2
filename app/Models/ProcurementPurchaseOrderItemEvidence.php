<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPurchaseOrderItemEvidence extends BaseModel
{
    protected $table = 'procurement_purchase_order_item_evidence';

    public const VENDOR_STATUS_SUBMITTED = 'submitted';
    public const VENDOR_STATUS_REVISION_REQUESTED = 'revision_requested';

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
        'vendor_submission_status',
        'vendor_submitted_at',
        'vendor_resubmission_requested_at',
        'vendor_resubmission_requested_by',
        'vendor_resubmission_note',
        'created_by',
    ];

    protected $casts = [
        'is_met' => 'boolean',
        'deliverable_date' => 'date',
        'delivered_unit_price' => 'decimal:2',
        'delivered_quantity' => 'decimal:2',
        'delivered_amount' => 'decimal:2',
        'documents' => 'array',
        'vendor_submitted_at' => 'datetime',
        'vendor_resubmission_requested_at' => 'datetime',
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

    public function hasVendorDocuments(): bool
    {
        return collect($this->documents ?? [])
            ->filter(fn ($document) => is_array($document))
            ->contains(fn ($document) => ($document['source'] ?? null) === 'vendor');
    }

    public function vendorEvidenceStatus(): ?string
    {
        if ($this->vendor_submission_status) {
            return $this->vendor_submission_status;
        }

        return $this->hasVendorDocuments() ? self::VENDOR_STATUS_SUBMITTED : null;
    }

    public function isOpenForVendorResubmission(): bool
    {
        return $this->vendorEvidenceStatus() === self::VENDOR_STATUS_REVISION_REQUESTED;
    }

    public function isLockedForVendorUpload(): bool
    {
        return $this->hasVendorDocuments() && ! $this->isOpenForVendorResubmission();
    }
}
