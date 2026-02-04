<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProcurementDisbursement extends BaseModel
{
    protected $table = 'procurement_disbursements';

    protected $fillable = [
        'purchase_order_id',
        'procurement_id',
        'vendor_id',
        'sub_activity_id',
        'governance_node_id',
        'reference_no',
        'amount',
        'currency',
        'payment_method',
        'status',
        'paid_at',
        'created_by',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementPurchaseOrder::class, 'purchase_order_id');
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
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

    public static function generateReference(): string
    {
        do {
            $reference = 'RCPT-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
        } while (self::where('reference_no', $reference)->exists());

        return $reference;
    }
}
