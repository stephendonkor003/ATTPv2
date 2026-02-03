<?php

namespace App\Models;

use App\Models\BaseModel;

class SiteVisitAssignment extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'site_visit_id',
        'user_id',
    ];

    public function siteVisit()
    {
        return $this->belongsTo(SiteVisit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}