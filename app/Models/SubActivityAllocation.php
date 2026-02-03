<?php

namespace App\Models;

use App\Models\BaseModel;

class SubActivityAllocation extends BaseModel
{
    protected $table = 'myb_sub_activity_allocations';

    protected $fillable = [
        'sub_activity_id',
        'year',
        'amount',
    ];

    public function subActivity()
    {
        return $this->belongsTo(SubActivity::class, 'sub_activity_id');
    }
}
