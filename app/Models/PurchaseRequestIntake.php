<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseRequestIntake extends BaseModel
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_CONVERTED = 'converted';

    public const PRIORITIES = ['normal', 'high', 'urgent'];

    protected $table = 'purchase_request_intakes';

    protected $fillable = [
        'reference_no',
        'created_by',
        'governance_node_id',
        'converted_purchase_request_id',
        'title',
        'description',
        'needed_by',
        'priority',
        'estimated_amount',
        'currency',
        'status',
        'converted_by',
        'converted_at',
    ];

    protected $casts = [
        'needed_by' => 'date',
        'estimated_amount' => 'decimal:2',
        'converted_at' => 'datetime',
    ];

    public static function generateReference(): string
    {
        do {
            $reference = 'APR-'.now()->format('Y').'-'.Str::upper(Str::random(6));
        } while (self::query()->where('reference_no', $reference)->exists());

        return $reference;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function governanceNode(): BelongsTo
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function convertedPurchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'converted_purchase_request_id');
    }

    public function converter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestIntakeItem::class, 'intake_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PurchaseRequestIntakeDocument::class, 'intake_id');
    }
}
