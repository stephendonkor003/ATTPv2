<?php

namespace App\Models;

use App\Models\BaseModel;

class Bid extends BaseModel
{
    protected $fillable = [
        'project_id',
        'user_id',
        'amount',
        'proposal',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
