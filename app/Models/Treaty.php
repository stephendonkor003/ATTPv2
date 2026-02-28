<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Treaty extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_treaties';

    protected $fillable = [
        'title',
        'short_title',
        'reference_code',
        'description',
        'overview',
        'key_provisions',
        'implementation_framework',
        'monitoring_and_reporting',
        'read_more_url',
        'adoption_date',
        'entry_into_force_date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'adoption_date' => 'date',
        'entry_into_force_date' => 'date',
    ];

    public function memberStateStatuses(): HasMany
    {
        return $this->hasMany(TreatyMemberStateStatus::class, 'treaty_id');
    }

    public function supportingDocuments(): HasMany
    {
        return $this->hasMany(TreatySupportingDocument::class, 'treaty_id')
            ->latest();
    }

    public function memberStates(): BelongsToMany
    {
        return $this->belongsToMany(
            AuMemberState::class,
            'myb_treaty_member_state_statuses',
            'treaty_id',
            'member_state_id'
        )->withPivot([
            'is_signed',
            'signed_at',
            'is_ratified',
            'ratified_at',
        ])->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
