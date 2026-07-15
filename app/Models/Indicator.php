<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Indicator extends BaseModel
{
    public const SETUP_TARGET_CONTEXT = 'setup';

    protected $table = 'myb_indicators';

    protected $fillable = [
        'indicator_code',
        'indicatorable_type',
        'indicatorable_id',
        'name',
        'baseline_year',
        'baseline_type',
        'baseline_value',
        'indicator_level_id',
        'methodology',
        'notes',
        'responsible_user_id',
        'responsible_party',
        'frequency_of_reporting_id',
        'unit_id',
        'primary_source',
        'definitions',
        'created_by',
    ];

    protected $casts = [
        'baseline_type' => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (Indicator $indicator): void {
            if (blank($indicator->indicator_code)) {
                $indicator->indicator_code = static::generateIndicatorCode();
            }
        });

        static::updating(function (Indicator $indicator): void {
            if ($indicator->isDirty('indicator_code')) {
                $indicator->indicator_code = $indicator->getOriginal('indicator_code');
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
