<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VendorPurchaseRequest extends BaseModel
{
    protected $fillable = [
        'user_id',
        'procurement_id',
        'sub_activity_id',
        'purchase_order_id',
        'reference_no',
        'request_type',
        'title',
        'requested_amount',
        'currency',
        'needed_by',
        'priority',
        'status',
        'description',
        'business_justification',
        'admin_response',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'requested_amount' => 'decimal:2',
        'needed_by' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    public function subActivity(): BelongsTo
    {
        return $this->belongsTo(SubActivity::class, 'sub_activity_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementPurchaseOrder::class, 'purchase_order_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorPurchaseRequestItem::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VendorDocument::class, 'source_id')
            ->where('source_type', 'vendor_purchase_request');
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'VPR-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
        } while (self::where('reference_no', $reference)->exists());

        return $reference;
    }
}
