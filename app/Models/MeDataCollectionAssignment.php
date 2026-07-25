<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeDataCollectionAssignment extends BaseModel
{
    protected $table = 'me_data_collection_assignments';

    protected $fillable = [
        'collection_id',
        'think_tank_member_id',
        'assigned_by',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function collection(): BelongsTo
    {
        return $this->belongsTo(MeDataCollection::class, 'collection_id');
    }

    public function thinkTank(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function submission(): HasOne
    {
        return $this->hasOne(MeDataSubmission::class, 'assignment_id');
    }

    public function performanceReports(): HasMany
    {
        return $this->hasMany(MePerformanceReport::class, 'assignment_id');
    }
}
