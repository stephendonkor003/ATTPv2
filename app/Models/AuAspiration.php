<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuAspiration extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_au_aspirations';

    protected $fillable = [
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
     * Scope for active aspirations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered aspirations.
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
        return "Aspiration {$this->number}: {$this->title}";
    }

    /**
     * Goals under this aspiration.
     */
    public function goals(): HasMany
    {
        return $this->hasMany(AuGoal::class, 'aspiration_id');
    }

    /**
     * Program fundings aligned with this aspiration.
     */
    public function programFundings(): BelongsToMany
    {
        return $this->belongsToMany(
            ProgramFunding::class,
            'myb_program_funding_aspirations',
            'aspiration_id',
            'program_funding_id'
        )->withTimestamps();
    }
}
