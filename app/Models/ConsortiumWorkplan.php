<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsortiumWorkplan extends BaseModel
{
    protected $table = 'attp_workplans';

    protected $fillable = [
        'consortium_id',
        'program_funding_id',
        'title',
        'period_label',
        'starts_on',
        'ends_on',
        'planned_budget',
        'status',
        'objectives',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'planned_budget' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function consortium(): BelongsTo
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }

    public function programFunding(): BelongsTo
    {
        return $this->belongsTo(ProgramFunding::class, 'program_funding_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ConsortiumActivityReport::class, 'workplan_id');
    }
}
