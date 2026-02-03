<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AuGoal extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_au_goals';

    protected $fillable = [
        'aspiration_id',
        'number',
        'title',
        'description',
        'is_active',
    ];

    protected $casts = [
        'number' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active goals.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered goals.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('number');
    }

    /**
     * Get display name with number.
     */
    public function getDisplayNameAttribute(): string
    {
        return "Goal {$this->number}: {$this->title}";
    }

    /**
     * The aspiration this goal belongs to.
     */
    public function aspiration(): BelongsTo
    {
        return $this->belongsTo(AuAspiration::class, 'aspiration_id');
    }

    /**
     * Program fundings aligned with this goal.
     */
    public function programFundings(): BelongsToMany
    {
        return $this->belongsToMany(
            ProgramFunding::class,
            'myb_program_funding_goals',
            'goal_id',
            'program_funding_id'
        )->withTimestamps();
    }
}
