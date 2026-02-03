<?php

namespace App\Models;

use App\Models\BaseModel;

class Assignment extends BaseModel
{
    protected $fillable = [
        'applicant_id',
        'evaluator_id',
        'evaluator_ids',
        'role',
    ];

    protected $casts = [
        'evaluator_ids' => 'array',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
