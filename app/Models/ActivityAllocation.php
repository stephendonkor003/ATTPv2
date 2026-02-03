<?php

namespace App\Models;

use App\Models\BaseModel;

class ActivityAllocation extends BaseModel
{
    protected $table = 'myb_activity_allocations';

    protected $fillable = [
        'activity_id',
        'year',
        'amount',
    ];

    public function activity()
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }
}
