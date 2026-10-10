<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementDisbursementSubmissionBatch extends BaseModel
{
    protected $table = 'procurement_disbursement_submission_batches';

    protected $fillable = [
        'purchase_order_id',
        'actor_id',
        'operation',
        'idempotency_key',
        'fingerprint',
        'result_payment_ids',
        'status',
    ];

    protected $casts = [
        'result_payment_ids' => 'array',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(ProcurementPurchaseOrder::class, 'purchase_order_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
