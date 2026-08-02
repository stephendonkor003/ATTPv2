<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeIndicatorAchievement extends BaseModel
{
    public const VERIFICATION_STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'returned' => 'Returned for Revision',
        'verified' => 'Verified',
        'approved' => 'Approved',
    ];

    public const GEOGRAPHIC_SCOPES = [
        'country' => 'Country',
        'national' => 'National',
        'rec' => 'Regional Economic Community (REC)',
        'regional' => 'Regional / multi-country',
    ];

    public const PRIORITY_THEMES = [
        'economic_transformation_governance' => 'Economic Transformation and Governance',
        'climate_change' => 'Climate Change',
        'regional_trade' => 'Regional Trade',
        'food_security' => 'Food Security',
        'human_capital' => 'Human Capital',
        'digitalization' => 'Digitalization',
    ];

    public const RECS = [
        'amu' => 'Arab Maghreb Union (AMU)',
        'cen_sad' => 'Community of Sahel-Saharan States (CEN-SAD)',
        'comesa' => 'Common Market for Eastern and Southern Africa (COMESA)',
        'eac' => 'East African Community (EAC)',
        'eccas' => 'Economic Community of Central African States (ECCAS)',
        'ecowas' => 'Economic Community of West African States (ECOWAS)',
        'igad' => 'Intergovernmental Authority on Development (IGAD)',
        'sadc' => 'Southern African Development Community (SADC)',
    ];

    public const INSTITUTION_TYPES = [
        'think_tank' => 'Think tank',
        'consortium' => 'Consortium',
        'partner_institution' => 'Partner institution',
    ];

    public const GENDERS = [
        'female' => 'Female',
        'male' => 'Male',
        'other_not_reported' => 'Other / not reported',
    ];

    public const AGE_GROUPS = [
        'youth_below_35' => 'Youth below 35 years',
        'adult_35_plus' => 'Adults aged 35 years and above',
        'not_reported' => 'Not reported',
    ];

    public const STAKEHOLDER_CATEGORIES = [
        'government' => 'Government',
        'parliament' => 'Parliament',
        'regional_organization' => 'Regional organization',
        'think_tank' => 'Think tank',
        'academia' => 'Academia',
        'civil_society' => 'Civil society',
        'private_sector' => 'Private sector',
        'development_partner' => 'Development partner',
        'media' => 'Media',
        'other' => 'Other',
    ];

    protected $table = 'me_indicator_achievements';

    protected $fillable = [
        'report_id',
        'report_indicator_result_id',
        'indicator_id',
        'indicator_result_id',
        'achievement_code',
        'title',
        'description',
        'achieved_on',
        'geographic_scope',
        'country',
        'rec',
        'location',
        'lead_think_tank_member_id',
        'collaborating_institutions',
        'priority_themes',
        'total_beneficiaries',
        'verification_status',
        'verified_by',
        'verified_at',
        'verification_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'achieved_on' => 'date',
        'collaborating_institutions' => 'array',
        'priority_themes' => 'array',
        'total_beneficiaries' => 'integer',
        'verified_at' => 'datetime',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(MePerformanceReport::class, 'report_id');
    }

    public function reportIndicatorResult(): BelongsTo
    {
        return $this->belongsTo(MePerformanceReportIndicatorResult::class, 'report_indicator_result_id');
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    public function indicatorResult(): BelongsTo
    {
        return $this->belongsTo(IndicatorResult::class, 'indicator_result_id');
    }

    public function leadThinkTank(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'lead_think_tank_member_id');
    }

    public function breakdowns(): HasMany
    {
        return $this->hasMany(MeIndicatorAchievementDisaggregation::class, 'achievement_id')
            ->orderBy('created_at');
    }

    public function documentLinks(): HasMany
    {
        return $this->hasMany(MeRepositoryDocumentLink::class, 'linkable_id')
            ->where('linkable_type', self::class);
    }

    public function recalculateBeneficiaries(): int
    {
        $total = (int) $this->breakdowns()->sum('beneficiary_count');
        $this->updateQuietly(['total_beneficiaries' => $total]);

        return $total;
    }
}
