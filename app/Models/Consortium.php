<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Consortium extends BaseModel
{
    protected $table = 'attp_consortia';

    protected $fillable = [
        'code',
        'name',
        'lead_applicant_id',
        'lead_think_dataset_id',
        'program_funding_id',
        'funder_id',
        'secretariat_manager_id',
        'country',
        'region',
        'covered_countries',
        'approved_budget',
        'currency',
        'start_date',
        'end_date',
        'status',
        'mandate',
        'notes',
    ];

    protected $casts = [
        'covered_countries' => 'array',
        'approved_budget' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function leadApplicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class, 'lead_applicant_id');
    }

    public function leadThinkTank(): BelongsTo
    {
        return $this->belongsTo(ThinkDataset::class, 'lead_think_dataset_id');
    }

    public function programFunding(): BelongsTo
    {
        return $this->belongsTo(ProgramFunding::class, 'program_funding_id');
    }

    public function funder(): BelongsTo
    {
        return $this->belongsTo(Funder::class, 'funder_id');
    }

    public function secretariatManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretariat_manager_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ConsortiumThinkTank::class, 'consortium_id');
    }

    public function workplans(): HasMany
    {
        return $this->hasMany(ConsortiumWorkplan::class, 'consortium_id');
    }

    public function activityReports(): HasMany
    {
        return $this->hasMany(ConsortiumActivityReport::class, 'consortium_id');
    }

    public function fundAllocations(): HasMany
    {
        return $this->hasMany(ConsortiumFundAllocation::class, 'consortium_id');
    }

    public function disbursementRequests(): HasMany
    {
        return $this->hasMany(ConsortiumDisbursementRequest::class, 'consortium_id');
    }

    public function transferDisbursements(): HasMany
    {
        return $this->hasMany(ProcurementDisbursement::class, 'consortium_id');
    }

    public function expenseReports(): HasMany
    {
        return $this->hasMany(ConsortiumExpenseReport::class, 'consortium_id');
    }

    public function riskFlags(): HasMany
    {
        return $this->hasMany(ConsortiumRiskFlag::class, 'consortium_id');
    }

    public function researchOutputs(): HasMany
    {
        return $this->hasMany(ThinkTankResearchOutput::class, 'consortium_id');
    }

    public function procurementPlans(): HasMany
    {
        return $this->hasMany(ThinkTankProcurementPlan::class, 'consortium_id');
    }

    public function procurements(): HasMany
    {
        return $this->hasMany(Procurement::class, 'consortium_id');
    }
}
