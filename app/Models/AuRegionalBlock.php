<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AuRegionalBlock extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_au_regional_blocks';

    protected $fillable = [
        'name',
        'abbreviation',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope for active regional blocks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered regional blocks.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get display name with abbreviation.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->abbreviation
            ? "{$this->name} ({$this->abbreviation})"
            : $this->name;
    }

    /**
     * Program fundings targeting this regional block.
     */
    public function programFundings(): BelongsToMany
    {
        return $this->belongsToMany(
            ProgramFunding::class,
            'myb_program_funding_regional_blocks',
            'regional_block_id',
            'program_funding_id'
        )->withTimestamps();
    }
}
