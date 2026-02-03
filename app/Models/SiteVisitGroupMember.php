<?php

namespace App\Models;

use App\Models\BaseModel;

class SiteVisitGroupMember extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'user_id',
        'role',
    ];

    public function group()
    {
        return $this->belongsTo(SiteVisitGroup::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}