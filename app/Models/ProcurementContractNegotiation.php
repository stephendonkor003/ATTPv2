<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProcurementContractNegotiation extends BaseModel
{
    protected $table = 'procurement_contract_negotiations';

    protected $fillable = [
        'procurement_id',
        'submission_id',
        'vendor_id',
        'proposed_amount',
        'agreed_amount',
        'status',
        'notes',
        'termination_reason',
        'terminated_by',
        'terminated_at',
        'created_by',
        'agreed_at',
    ];

    protected $casts = [
        'proposed_amount' => 'decimal:2',
        'agreed_amount' => 'decimal:2',
        'agreed_at' => 'datetime',
        'terminated_at' => 'datetime',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProcurementContractDocument::class, 'negotiation_id');
    }

    public function purchaseOrder(): HasOne
    {
        return $this->hasOne(ProcurementPurchaseOrder::class, 'negotiation_id');
    }
}
