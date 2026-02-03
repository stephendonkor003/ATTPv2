<?php

namespace App\Models;

use App\Models\BaseModel;

class SiteVisitApproval extends BaseModel
{
    public $timestamps = false;

    protected $fillable = [
        'site_visit_id',
        'reviewer_id',
        'status',
        'remarks',
    ];

    public function siteVisit()
    {
        return $this->belongsTo(SiteVisit::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}