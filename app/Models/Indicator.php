<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Indicator extends BaseModel
{
    public const SETUP_TARGET_CONTEXT = 'setup';
    public const ANNUAL_TARGET_CONTEXT = 'annual';

    public const AGGREGATION_METHODS = [
        'sum' => 'Sum (additive values only)',
        'latest' => 'Latest reported value',
        'average' => 'Average of reported values',
        'minimum' => 'Minimum reported value',
        'maximum' => 'Maximum reported value',
        'percentage' => 'Percentage (latest; never summed)',
        'ratio' => 'Ratio / rate (latest; never summed)',
        'non_additive' => 'Other non-additive value (latest)',
    ];

    public const ORGANIZATION_ROLLUP_METHODS = [
        'sum' => 'Sum organization results',
        'latest' => 'Latest approved organization value',
        'average' => 'Simple average across organizations',
        'weighted_average' => 'Weighted average (requires numerator and denominator)',
        'minimum' => 'Minimum organization value',
        'maximum' => 'Maximum organization value',
        'non_additive' => 'Do not consolidate into one numeric value',
    ];

    protected $table = 'myb_indicators';

    protected $fillable = [
        'indicator_code',
        'indicatorable_type',
        'indicatorable_id',
        'project_component_id',
        'name',
        'baseline_year',
        'baseline_type',
        'baseline_value',
        'annual_target',
        'life_of_programme_target',
        'indicator_level_id',
        'results_level',
        'aggregation_method',
        'organization_rollup_method',
        'methodology',
        'data_collection_method',
        'notes',
        'responsible_user_id',
        'responsible_party',
        'frequency_of_reporting_id',
        'unit_id',
        'primary_source',
        'means_of_verification_id',
        'means_of_verification_folder_id',
        'definitions',
        'created_by',
        'code_updated_by',
    ];

    protected $casts = [
        'baseline_type' => 'string',
        'baseline_value' => 'decimal:4',
        'annual_target' => 'decimal:4',
        'life_of_programme_target' => 'decimal:4',
    ];

    protected static function booted(): void
    {
        static::creating(function (Indicator $indicator): void {
            if (blank($indicator->indicator_code)) {
                $indicator->indicator_code = static::generateIndicatorCode();
            }
        });

    }

    public static function generateIndicatorCode(): string
    {
        do {
            $code = 'IND-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        } while (static::query()->where('indicator_code', $code)->exists());

        return $code;
    }

    // Polymorphic relationship to parent (Program, Project, Activity, SubActivity)
    public function indicatorable()
    {
        return $this->morphTo();
    }

    public function projectComponent(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_component_id');
    }

    public function meansOfVerification(): BelongsTo
    {
        return $this->belongsTo(MeKnowledgeEvidenceItem::class, 'means_of_verification_id');
    }

    public function meansOfVerificationFolder(): BelongsTo
    {
        return $this->belongsTo(MeRepositoryFolder::class, 'means_of_verification_folder_id');
    }

    public function disaggregations(): HasMany
    {
        return $this->hasMany(IndicatorDisaggregation::class)
            ->orderByRaw("CASE level WHEN 'primary' THEN 1 WHEN 'secondary' THEN 2 WHEN 'tertiary' THEN 3 ELSE 4 END");
    }

    public function disaggregationRequirements(): HasMany
    {
        return $this->hasMany(MeIndicatorDisaggregationRequirement::class, 'indicator_id')
            ->with('dimension')
            ->orderBy('sort_order');
    }

    public function codeHistory(): HasMany
    {
        return $this->hasMany(IndicatorCodeHistory::class, 'indicator_id')
            ->latest('changed_at');
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(MeIndicatorAchievement::class, 'indicator_id');
    }

    public function repositoryFolders(): BelongsToMany
    {
        return $this->belongsToMany(
            MeRepositoryFolder::class,
            'me_repository_folder_indicators',
            'indicator_id',
            'folder_id'
        )->withPivot('linked_by')->withTimestamps();
    }

    public function resultsLevelLabel(): string
    {
        return match ($this->results_level) {
            'pdo' => 'PDO',
            'intermediate_results' => 'Intermediate Results',
            default => 'Not classified',
        };
    }

    public function disaggregationChain(): string
    {
        $requirements = $this->relationLoaded('disaggregationRequirements')
            ? $this->disaggregationRequirements
            : $this->disaggregationRequirements()->get();
        $configured = $requirements
            ->pluck('dimension.name')
            ->filter()
            ->implode(' × ');
        if ($configured !== '') {
            return $configured;
        }

        return $this->disaggregations
            ->pluck('dimension')
            ->filter()
            ->implode(' → ');
    }

    // M&E Configuration relationships
    public function level()
    {
        return $this->belongsTo(IndicatorLevel::class, 'indicator_level_id');
    }

    public function frequency()
    {
        return $this->belongsTo(ReportingFrequency::class, 'frequency_of_reporting_id');
    }

    public function unit()
    {
        return $this->belongsTo(IndicatorUnit::class, 'unit_id');
    }

    // Nested project indicators (if this indicator belongs to a program)
    public function projectIndicators()
    {
        return $this->hasMany(Indicator::class, 'parent_indicator_id');
    }

    // Parent program indicator (if this is a project indicator)
    public function parentIndicator()
    {
        return $this->belongsTo(Indicator::class, 'parent_indicator_id');
    }

    // Targets and actuals
    public function targets()
    {
        return $this->hasMany(IndicatorTarget::class);
    }

    public function setupTarget()
    {
        return $this->hasOne(IndicatorTarget::class)
            ->where('target_context', self::SETUP_TARGET_CONTEXT);
    }

    public function annualTarget()
    {
        return $this->hasOne(IndicatorTarget::class)
            ->where('target_context', self::ANNUAL_TARGET_CONTEXT);
    }

    public function aggregationMethodLabel(): string
    {
        return self::AGGREGATION_METHODS[$this->aggregation_method] ?? 'Latest reported value';
    }

    public function isAdditive(): bool
    {
        return $this->aggregation_method === 'sum';
    }

    public function responsiblePerson()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function results()
    {
        return $this->hasMany(IndicatorResult::class);
    }

    public function dataEntryFields()
    {
        return $this->hasMany(MeDataEntryFormField::class, 'indicator_id');
    }

    public function dataEntryForms(): HasMany
    {
        return $this->hasMany(MeDataEntryForm::class, 'indicator_id');
    }

    public function linkedDataEntryForms(): BelongsToMany
    {
        return $this->belongsToMany(
            MeDataEntryForm::class,
            'me_data_entry_form_indicators',
            'indicator_id',
            'form_id'
        )
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps();
    }

    public function surveyLink()
    {
        return $this->hasOne(IndicatorSurveyLink::class, 'indicator_id')
            ->where('is_active', true);
    }

    public function surveyResponses()
    {
        return $this->hasMany(IndicatorSurveyResponse::class, 'indicator_id');
    }

    public function dataSourceSyncLogs()
    {
        return $this->hasMany(IndicatorDataSourceSyncLog::class, 'indicator_id');
    }

    public function latestDataSourceSyncLog()
    {
        return $this->hasOne(IndicatorDataSourceSyncLog::class, 'indicator_id')
            ->latestOfMany('synced_at');
    }
}
