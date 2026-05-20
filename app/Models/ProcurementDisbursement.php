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
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'recipient_confirmed_at' => 'datetime',
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
