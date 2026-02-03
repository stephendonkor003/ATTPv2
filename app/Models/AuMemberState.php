<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AuMemberState extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_au_member_states';

    protected $fillable = [
        'name',
        'code',
        'code_alpha2',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope for active member states.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordered member states.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Program fundings that benefit this member state.
     */
    public function programFundings(): BelongsToMany
    {
        return $this->belongsToMany(
            ProgramFunding::class,
            'myb_program_funding_member_states',
            'member_state_id',
            'program_funding_id'
        )->withTimestamps();
    }
}
