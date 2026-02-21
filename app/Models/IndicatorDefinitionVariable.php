<?php

namespace App\Models;

use App\Models\BaseModel;

class IndicatorDefinitionVariable extends BaseModel
{
    protected $table = 'indicator_definition_variables';

    protected $fillable = [
        'indicator_definition_id',
        'name',
        'color',
        'created_by',
        'updated_by',
    ];

    public function definition()
    {
        return $this->belongsTo(IndicatorDefinition::class, 'indicator_definition_id');
    }
}
