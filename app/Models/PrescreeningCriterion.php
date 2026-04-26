<?php

namespace App\Models;

use App\Models\BaseModel;

class PrescreeningCriterion extends BaseModel
{
    protected $table = 'prescreening_criteria';

    protected $fillable = [
        'prescreening_template_id',
        'prescreening_section_id',
        'name',
        'description',
        'field_key',
        'evaluation_type',
        'min_value',
        'is_mandatory',
        'sort_order',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    public function template()
    {
        return $this->belongsTo(PrescreeningTemplate::class, 'prescreening_template_id');
    }

    public function section()
    {
        return $this->belongsTo(PrescreeningSection::class, 'prescreening_section_id');
    }
}
