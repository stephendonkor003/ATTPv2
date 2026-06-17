<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPurchaseRequestItem extends BaseModel
{
    protected $fillable = [
        'vendor_purchase_request_id',
        'item_name',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'delivery_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'amount' => 'decimal:2',
        'delivery_date' => 'date',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(VendorPurchaseRequest::class, 'vendor_purchase_request_id');
    }
}
