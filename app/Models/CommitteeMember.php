<?php

namespace App\Models;

use App\Models\BaseModel;

class CommitteeMember extends BaseModel
{
    protected $fillable = [
        'committee_id',
        'user_id',
    ];

    public function committee()
    {
        return $this->belongsTo(Committee::class, 'committee_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
