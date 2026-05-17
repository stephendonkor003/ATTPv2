<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsortiumExpenseReport extends BaseModel
{
    protected $table = 'attp_expense_reports';

    protected $fillable = [
        'consortium_id',
        'think_tank_member_id',
        'activity_report_id',
        'fund_allocation_id',
        'disbursement_request_id',
        'expense_code',
        'description',
        'vendor_name',
        'expense_date',
        'amount',
        'currency',
        'receipt_path',
        'status',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function consortium(): BelongsTo
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ConsortiumActivityReport::class, 'activity_report_id');
    }
}
