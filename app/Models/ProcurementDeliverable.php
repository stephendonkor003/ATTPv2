<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProcurementDeliverable extends BaseModel
{
    protected $table = 'procurement_deliverables';

    protected $fillable = [
        'procurement_id',
        'vendor_id',
        'title',
        'type',
        'description',
        'timeline_start',
        'timeline_end',
        'amount',
        'currency',
        'sequence',
        'status',
        'vendor_approval_status',
        'admin_approval_status',
        'vendor_approved_by',
        'vendor_approved_at',
        'admin_approved_by',
        'admin_approved_at',
        'completed_at',
        'cancelled_at',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'timeline_start' => 'date',
        'timeline_end' => 'date',
        'amount' => 'decimal:2',
        'vendor_approved_at' => 'datetime',
        'admin_approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(
            ProcurementInvoice::class,
            'procurement_invoice_deliverables',
            'deliverable_id',
            'invoice_id'
        )->withTimestamps();
    }

    public function isAgreed(): bool
    {
        return $this->vendor_approval_status === 'approved'
            && $this->admin_approval_status === 'approved';
    }
}
