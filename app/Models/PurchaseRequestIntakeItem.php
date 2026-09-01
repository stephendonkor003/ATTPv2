<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestIntakeItem extends BaseModel
{
    protected $table = 'purchase_request_intake_items';

    protected $fillable = [
        'intake_id',
        'name',
        'notes',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function intake(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestIntake::class, 'intake_id');
    }
}
