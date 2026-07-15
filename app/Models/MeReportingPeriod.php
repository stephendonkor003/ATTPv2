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
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
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
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
