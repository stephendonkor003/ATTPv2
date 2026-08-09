<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeFramework extends BaseModel
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RETIRED = 'retired';

    protected $table = 'me_frameworks';

    protected $fillable = [
        'code', 'version', 'title', 'project_development_objective', 'status',
        'effective_from', 'effective_to', 'is_current', 'notes', 'approved_by',
        'approved_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_current' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function indicators(): HasMany
    {
        return $this->hasMany(Indicator::class, 'framework_id')->orderBy('display_order');
    }

    public function thresholds(): HasMany
    {
        return $this->hasMany(MePerformanceThreshold::class, 'framework_id')->orderBy('display_order');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true)->where('status', self::STATUS_ACTIVE);
    }
}
