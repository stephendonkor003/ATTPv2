<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ConsortiumThinkTank extends BaseModel
{
    public const DEFAULT_PORTAL_BRANDING = [
        'primary_color' => '#075c7a',
        'accent_color' => '#2b84a0',
    ];

    protected $table = 'attp_consortium_think_tanks';

    protected $fillable = [
        'consortium_id',
        'think_dataset_id',
        'applicant_id',
        'portal_user_id',
        'vendor_user_id',
        'au_sap_vendor_number',
        'name',
        'logo_path',
        'portal_branding',
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
        'portal_branding' => 'array',
    ];

    public function consortium(): BelongsTo
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        $path = str_replace('\\', '/', trim((string) $this->logo_path));

        if ($path === '' || ! Str::startsWith($path, 'think-tank-logos/') || str_contains($path, '../')) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @return array{primary_color: string, accent_color: string}
     */
    public function resolvedPortalBranding(): array
    {
        $branding = array_merge(self::DEFAULT_PORTAL_BRANDING, (array) $this->portal_branding);

        foreach (self::DEFAULT_PORTAL_BRANDING as $key => $fallback) {
            if (! preg_match('/^#[0-9a-f]{6}$/i', (string) ($branding[$key] ?? ''))) {
                $branding[$key] = $fallback;
            }
        }

        return $branding;
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

    /**
     * All staff accounts assigned to this think tank. portalUser remains the
     * backward-compatible pointer to the original primary administrator.
     */
    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class, 'think_tank_member_id');
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

    public function vendorDisbursements(): HasMany
    {
        return $this->hasMany(ProcurementDisbursement::class, 'vendor_id', 'vendor_user_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(ProcurementPurchaseOrder::class, 'think_tank_member_id');
    }

    public function vendorPurchaseOrders(): HasMany
    {
        return $this->hasMany(ProcurementPurchaseOrder::class, 'vendor_id', 'vendor_user_id');
    }

    public function transferPurchaseOrders(): HasMany
    {
        return $this->purchaseOrders()->where('po_type', 'think_tank_transfer');
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

    public function dataCollectionAssignments(): HasMany
    {
        return $this->hasMany(MeDataCollectionAssignment::class, 'think_tank_member_id');
    }

    public function dataSubmissions(): HasManyThrough
    {
        return $this->hasManyThrough(
            MeDataSubmission::class,
            MeDataCollectionAssignment::class,
            'think_tank_member_id',
            'assignment_id'
        );
    }

    public function indicatorResults(): HasMany
    {
        return $this->hasMany(IndicatorResult::class, 'think_tank_member_id');
    }

    public function performanceReports(): HasMany
    {
        return $this->hasMany(MePerformanceReport::class, 'think_tank_member_id');
    }

    public function biAnnualSiteVisitProfiles(): HasMany
    {
        return $this->hasMany(BiAnnualSiteVisitProfile::class, 'think_tank_member_id');
    }
}
