<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeReportingPeriod extends BaseModel
{
    public const TYPE_YEAR = 'year';

    public const TYPE_QUARTER = 'quarter';

    public const TYPE_MONTH = 'month';

    public const TYPE_CUSTOM = 'custom';

    public const TYPE_SEMI_ANNUAL = 'semi_annual';

    public const TYPE_ANNUAL = 'annual';

    public const LIFECYCLE_PLANNED = 'planned';
    public const LIFECYCLE_OPEN = 'open';
    public const LIFECYCLE_CLOSED = 'closed';
    public const LIFECYCLE_UNDER_REVIEW = 'under_review';
    public const LIFECYCLE_COMPLETED = 'completed';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'me_reporting_periods';

    protected $fillable = [
        'portfolio_id',
        'code',
        'label',
        'period_type',
        'period_start',
        'period_end',
        'status',
        'created_by',
        'updated_by',
        'reporting_year',
        'submission_opens_at',
        'submission_deadline',
        'review_deadline',
        'lifecycle_status',
        'instructions',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'reporting_year' => 'integer',
        'submission_opens_at' => 'datetime',
        'submission_deadline' => 'datetime',
        'review_deadline' => 'datetime',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'portfolio_id');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(MeDataCollection::class, 'reporting_period_id');
    }

    public function indicatorResults(): HasMany
    {
        return $this->hasMany(IndicatorResult::class, 'reporting_period_id');
    }

    public function performanceReports(): HasMany
    {
        return $this->hasMany(MePerformanceReport::class, 'reporting_period_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && in_array($this->lifecycle_status, [null, self::LIFECYCLE_OPEN], true);
    }

    public function isOpenForSubmission(): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return (! $this->submission_opens_at || now()->greaterThanOrEqualTo($this->submission_opens_at))
            && (! $this->submission_deadline || now()->lessThanOrEqualTo($this->submission_deadline));
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
