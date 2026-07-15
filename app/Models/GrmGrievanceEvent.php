<?php

namespace App\Models;

class GrmGrievanceEvent extends BaseModel
{
    protected $fillable = [
        'grievance_id',
        'user_id',
        'event_type',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function grievance()
    {
        return $this->belongsTo(GrmGrievance::class, 'grievance_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
