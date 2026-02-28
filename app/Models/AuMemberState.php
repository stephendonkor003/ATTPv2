<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuMemberState extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_au_member_states';

    protected $fillable = [
        'name',
        'code',
        'code_alpha2',
        'flag_path',
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

    public function getFlagUrlAttribute(): ?string
    {
        return $this->flag_path ? asset($this->flag_path) : null;
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

    /**
     * Treaty status records for this member state.
     */
    public function treatyStatuses(): HasMany
    {
        return $this->hasMany(TreatyMemberStateStatus::class, 'member_state_id');
    }

    /**
     * Treaties linked to this member state.
     */
    public function treaties(): BelongsToMany
    {
        return $this->belongsToMany(
            Treaty::class,
            'myb_treaty_member_state_statuses',
            'member_state_id',
            'treaty_id'
        )->withPivot([
            'is_signed',
            'signed_at',
            'is_ratified',
            'ratified_at',
        ])->withTimestamps();
    }

    public function nationalDataEntries(): HasMany
    {
        return $this->hasMany(MemberStateNationalData::class, 'member_state_id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(MemberStateCommunication::class, 'member_state_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(MemberStateQuestion::class, 'member_state_id');
    }

    public function commodityTrends(): HasMany
    {
        return $this->hasMany(MemberStateCommodityTrend::class, 'member_state_id');
    }
}
