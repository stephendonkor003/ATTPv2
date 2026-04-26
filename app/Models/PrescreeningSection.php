<?php

namespace App\Models;

use App\Models\BaseModel;

class PrescreeningSection extends BaseModel
{
    protected $fillable = [
        'prescreening_template_id',
        'name',
        'description',
        'sort_order',
    ];

    public function template()
    {
        return $this->belongsTo(PrescreeningTemplate::class, 'prescreening_template_id');
    }

    public function criteria()
    {
        return $this->hasMany(PrescreeningCriterion::class, 'prescreening_section_id')
            ->orderBy('sort_order');
    }
}
