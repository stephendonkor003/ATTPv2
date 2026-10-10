<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPurchaseOrderSubmissionClaim extends BaseModel
{
    protected $table = 'procurement_purchase_order_submission_claims';

    protected $fillable = [
        'actor_id',
        'idempotency_key',
        'fingerprint',
        'result_purchase_order_id',
        'status',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function resultPurchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementPurchaseOrder::class, 'result_purchase_order_id');
    }
}
