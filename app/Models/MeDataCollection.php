<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class MeDataCollection extends BaseModel
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'me_data_collections';

    protected $fillable = [
        'form_id',
        'reporting_period_id',
        'instructions',
        'opens_at',
        'due_at',
        'closes_at',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opens_at' => 'datetime',
        'due_at' => 'datetime',
        'closes_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(MeDataEntryForm::class, 'form_id');
    }

    public function reportingPeriod(): BelongsTo
    {
        return $this->belongsTo(MeReportingPeriod::class, 'reporting_period_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MeDataCollectionAssignment::class, 'collection_id');
    }

    public function submissions(): HasManyThrough
    {
        return $this->hasManyThrough(
            MeDataSubmission::class,
            MeDataCollectionAssignment::class,
            'collection_id',
            'assignment_id'
        );
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

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isAcceptingSubmissions(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if (! $this->isOpen()) {
            return false;
        }

        if ($this->opens_at && $at->isBefore($this->opens_at)) {
            return false;
        }

        return ! $this->closes_at || ! $at->isAfter($this->closes_at);
    }

    public function accepting(?CarbonInterface $at = null): bool
    {
        return $this->isAcceptingSubmissions($at);
    }

    public function isPastDue(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->due_at !== null && $at->isAfter($this->due_at);
    }
}
