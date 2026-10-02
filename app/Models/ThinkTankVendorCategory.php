<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ThinkTankVendorCategory extends BaseModel
{
    protected $table = 'attp_think_tank_vendor_categories';

    protected $fillable = [
        'think_tank_member_id',
        'name',
        'normalized_name',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function thinkTankMember(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function vendors(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'attp_think_tank_vendor_category_user',
            'think_tank_vendor_category_id',
            'vendor_user_id',
        )->withPivot('think_tank_member_id')->withTimestamps();
    }
}
