<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsortiumThinkTank extends BaseModel
{
    protected $table = 'attp_consortium_think_tanks';

    protected $fillable = [
        'consortium_id',
        'think_dataset_id',
        'applicant_id',
        'portal_user_id',
        'vendor_user_id',
        'au_sap_vendor_number',
        'name',
        'country',
        'email',
        'role',
        'budget_allocated',
        'status',
        'joined_at',
    ];

    protected $casts = [
        'budget_allocated' => 'decimal:2',
        'joined_at' => 'date',
    ];

    public function consortium(): BelongsTo
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }

    public function thinkDataset(): BelongsTo
    {
        return $this->belongsTo(ThinkDataset::class, 'think_dataset_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function portalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'portal_user_id');
    }

    public function vendorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_user_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ConsortiumActivityReport::class, 'think_tank_member_id');
    }

    public function fundAllocations(): HasMany
    {
        return $this->hasMany(ConsortiumFundAllocation::class, 'think_tank_member_id');
    }

    public function disbursementRequests(): HasMany
    {
        return $this->hasMany(ConsortiumDisbursementRequest::class, 'think_tank_member_id');
    }

    public function transferDisbursements(): HasMany
    {
        return $this->hasMany(ProcurementDisbursement::class, 'think_tank_member_id');
    }

    public function procurementPlans(): HasMany
    {
        return $this->hasMany(ThinkTankProcurementPlan::class, 'think_tank_member_id');
    }

    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class, 'think_tank_member_id');
    }

    public function researchOutputs(): HasMany
    {
        return $this->hasMany(ThinkTankResearchOutput::class, 'think_tank_member_id');
    }
}
