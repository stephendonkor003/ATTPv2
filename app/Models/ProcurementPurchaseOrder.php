<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProcurementPurchaseOrder extends BaseModel
{
    protected $table = 'procurement_purchase_orders';

    protected $fillable = [
        'procurement_id',
        'negotiation_id',
        'invoice_id',
        'vendor_id',
        'sub_activity_id',
        'governance_node_id',
        'reference_no',
        'amount',
        'currency',
        'status',
        'created_by',
        'issued_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    public function negotiation(): BelongsTo
    {
        return $this->belongsTo(ProcurementContractNegotiation::class, 'negotiation_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ProcurementInvoice::class, 'invoice_id');
    }

    public function disbursements()
    {
        return $this->hasMany(ProcurementDisbursement::class, 'purchase_order_id');
    }

    public function paidAmount(): float
    {
        return (float) $this->disbursements()->sum('amount');
    }

    public function remainingAmount(): float
    {
        $amount = (float) ($this->amount ?? 0);
        return max($amount - $this->paidAmount(), 0);
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
            $reference = 'PO-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
        } while (self::where('reference_no', $reference)->exists());

        return $reference;
    }
}
