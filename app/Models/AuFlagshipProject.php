<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AuFlagshipProject extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_au_flagship_projects';

    protected $fillable = [
        'number',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'number' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active flagship projects.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered flagship projects.
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
        return "#{$this->number}: {$this->name}";
    }

    /**
     * Program fundings aligned with this flagship project.
     */
    public function programFundings(): BelongsToMany
    {
        return $this->belongsToMany(
            ProgramFunding::class,
            'myb_program_funding_flagship_projects',
            'flagship_project_id',
            'program_funding_id'
        )->withTimestamps();
    }
}
