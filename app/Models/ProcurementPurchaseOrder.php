<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ProcurementPurchaseOrder extends BaseModel
{
    protected $table = 'procurement_purchase_orders';

    public const NON_PAYING_DISBURSEMENT_STATUSES = ['cancelled', 'void', 'reversed'];
    public const PAID_DISBURSEMENT_STATUSES = ['completed', 'paid', 'fully_paid'];

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
        'supporting_document_path',
        'supporting_document_name',
        'supporting_document_mime_type',
        'supporting_document_size',
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

    public function deliverables(): BelongsToMany
    {
        return $this->belongsToMany(
            ProcurementDeliverable::class,
            'procurement_purchase_order_deliverables',
            'purchase_order_id',
            'deliverable_id'
        )->withTimestamps();
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

    public function getResolvedCurrencyAttribute(): string
    {
        $this->loadMissing([
            'purchaseRequest.programFunding.program',
            'budgetCommitment.programFunding.program',
            'budgetCommitment.purchaseRequest.programFunding.program',
        ]);

        return $this->sourcePurchaseRequest()?->resolved_currency
            ?: $this->budgetCommitment?->resolved_currency
            ?: $this->currency
            ?: 'USD';
    }

    public function disbursements()
    {
        return $this->hasMany(ProcurementDisbursement::class, 'purchase_order_id');
    }

    public function lineItemEvidence(): HasMany
    {
        return $this->hasMany(ProcurementPurchaseOrderItemEvidence::class, 'purchase_order_id');
    }

    public function paidAmount(): float
    {
        return $this->actualPaidAmount();
    }

    public function actualPaidAmount(): float
    {
        if ($this->relationLoaded('disbursements')) {
            return (float) $this->disbursements
                ->filter(fn (ProcurementDisbursement $disbursement) => $disbursement->paid_at
                    && in_array(strtolower((string) $disbursement->status), self::PAID_DISBURSEMENT_STATUSES, true))
                ->sum(fn (ProcurementDisbursement $disbursement) => (float) $disbursement->amount);
        }

        return (float) $this->disbursements()
            ->whereNotNull('paid_at')
            ->whereIn('status', self::PAID_DISBURSEMENT_STATUSES)
            ->sum('amount');
    }

    public function remainingAmount(): float
    {
        $amount = (float) ($this->amount ?? 0);
        return max($amount - $this->paidAmount(), 0);
    }

    public function sourcePurchaseRequest(): ?PurchaseRequest
    {
        $this->loadMissing('purchaseRequest', 'budgetCommitment.purchaseRequest');

        return $this->purchaseRequest ?: $this->budgetCommitment?->purchaseRequest;
    }

    public function lineItemSummary(): array
    {
        $this->loadMissing([
            'lineItemEvidence',
            'disbursements',
            'purchaseRequest.items',
            'budgetCommitment.purchaseRequest.items',
        ]);

        $lineItems = $this->sourcePurchaseRequest()?->items ?? collect();

        if ($lineItems->isEmpty()) {
            $totalAmount = round((float) ($this->amount ?? 0), 2);
            $paidAmount = round($this->actualPaidAmount(), 2);

            return [
                'has_line_items' => false,
                'total_items' => 0,
                'paid_items' => 0,
                'pending_items' => 0,
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'pending_amount' => round(max($totalAmount - $paidAmount, 0), 2),
            ];
        }

        $totalAmount = round($lineItems->sum(fn ($item) => (float) ($item->amount ?? 0)), 2);
        $paidDisbursements = $this->disbursements
            ->filter(fn (ProcurementDisbursement $disbursement) => $disbursement->paid_at
                && in_array(strtolower((string) $disbursement->status), self::PAID_DISBURSEMENT_STATUSES, true));
        $paidAmountsByItem = $paidDisbursements
            ->filter(fn (ProcurementDisbursement $disbursement) => filled($disbursement->purchase_request_item_id))
            ->groupBy(fn (ProcurementDisbursement $disbursement) => (string) $disbursement->purchase_request_item_id)
            ->map(fn ($receipts) => round($receipts->sum(fn (ProcurementDisbursement $disbursement) => (float) $disbursement->amount), 2));
        $legacyPaidDeliverableIds = $paidDisbursements
            ->filter(fn (ProcurementDisbursement $disbursement) => blank($disbursement->purchase_request_item_id) && $disbursement->deliverable_id)
            ->pluck('deliverable_id')
            ->map(fn ($deliverableId) => (string) $deliverableId)
            ->values()
            ->all();
        $linePaidAmounts = $lineItems->mapWithKeys(function ($item) use ($paidAmountsByItem, $legacyPaidDeliverableIds) {
            $lineAmount = round((float) ($item->amount ?? 0), 2);
            $paidAmount = round((float) ($paidAmountsByItem->get((string) $item->id, 0)), 2);

            if ($paidAmount <= 0 && $item->deliverable_id && in_array((string) $item->deliverable_id, $legacyPaidDeliverableIds, true)) {
                $paidAmount = $lineAmount;
            }

            return [(string) $item->id => round(min($lineAmount, $paidAmount), 2)];
        });
        $paidAmount = round($linePaidAmounts->sum(), 2);
        $paidItemsCount = $lineItems
            ->filter(fn ($item) => (float) $linePaidAmounts->get((string) $item->id, 0) >= (float) ($item->amount ?? 0)
                && (float) ($item->amount ?? 0) > 0)
            ->count();

        return [
            'has_line_items' => true,
            'total_items' => $lineItems->count(),
            'paid_items' => $paidItemsCount,
            'pending_items' => max($lineItems->count() - $paidItemsCount, 0),
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'pending_amount' => round(max($totalAmount - $paidAmount, 0), 2),
        ];
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
