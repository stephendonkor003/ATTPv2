<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class VendorReport extends BaseModel
{
    protected $fillable = [
        'user_id',
        'procurement_id',
        'purchase_order_id',
        'reference_no',
        'title',
        'report_type',
        'reporting_period_start',
        'reporting_period_end',
        'status',
        'summary',
        'challenges',
        'next_steps',
        'admin_feedback',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reporting_period_start' => 'date',
        'reporting_period_end' => 'date',
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

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementPurchaseOrder::class, 'purchase_order_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VendorDocument::class, 'source_id')
            ->where('source_type', 'vendor_report');
    }

    public static function generateReference(): string
    {
        do {
            $reference = 'VRPT-' . now()->format('Y') . '-' . Str::upper(Str::random(6));
        } while (self::where('reference_no', $reference)->exists());

        return $reference;
    }
}
