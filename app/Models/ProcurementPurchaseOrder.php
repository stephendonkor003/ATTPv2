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
        'budget_commitment_id',
        'purchase_request_id',
        'vendor_id',
        'sub_activity_id',
        'governance_node_id',
        'consortium_id',
        'think_tank_member_id',
        'reference_no',
        'po_title',
        'supplier_reference',
        'contract_reference',
        'buyer_contact_name',
        'buyer_contact_email',
        'buyer_contact_phone',
        'vendor_contact_name',
        'vendor_contact_email',
        'vendor_contact_phone',
        'billing_address',
        'shipping_address',
        'delivery_location',
        'incoterm',
        'delivery_terms',
        'payment_terms',
        'warranty_terms',
        'inspection_requirements',
        'special_instructions',
        'terms_conditions',
        'po_type',
        'amount',
        'currency',
        'status',
        'created_by',
        'issued_at',
        'expected_delivery_date',
        'valid_until',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'datetime',
        'expected_delivery_date' => 'date',
        'valid_until' => 'date',
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

    public function budgetCommitment(): BelongsTo
    {
        return $this->belongsTo(BudgetCommitment::class, 'budget_commitment_id');
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
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

    public function consortium(): BelongsTo
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }

    public function thinkTankMember(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'PO-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
        } while (self::where('reference_no', $reference)->exists());

        return $reference;
    }

    public static function generateThinkTankTransferReference(ConsortiumThinkTank $member): string
    {
        $member->loadMissing('consortium');

        $consortiumCode = self::referenceSegment($member->consortium?->code ?: $member->consortium?->name ?: 'CONS');
        $thinkTankCode = self::referenceSegment($member->name);
        $period = now()->format('Ym');
        $prefix = "PO-ATTP-{$period}-{$consortiumCode}-{$thinkTankCode}";

        $sequence = 1;
        do {
            $reference = $prefix . '-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (self::where('reference_no', $reference)->exists());

        return $reference;
    }

    private static function referenceSegment(string $value): string
    {
        $words = preg_split('/[^A-Za-z0-9]+/', Str::upper($value), -1, PREG_SPLIT_NO_EMPTY);
        $stopWords = ['FOR', 'THE', 'AND', 'OF', 'DE', 'ET', 'DU'];

        $letters = collect($words)
            ->reject(fn (string $word) => in_array($word, $stopWords, true))
            ->map(fn (string $word) => Str::substr($word, 0, 1))
            ->join('');

        return Str::substr($letters ?: Str::upper(Str::slug($value, '')), 0, 8) ?: 'TT';
    }
}
