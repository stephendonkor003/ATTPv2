<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeFocalUnitContact extends BaseModel
{
    protected $table = 'me_focal_unit_contacts';

    protected $fillable = [
        'consortium_name', 'think_tank_member_id', 'think_tank_label', 'user_id',
        'focal_person_name', 'email', 'is_primary', 'notes', 'source', 'is_active',
    ];

    protected $casts = ['is_primary' => 'boolean', 'is_active' => 'boolean'];

    public function thinkTank(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
