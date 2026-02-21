<?php

namespace App\Models;

use App\Models\BaseModel;
use App\Models\IndicatorDefinitionVariable;

class IndicatorDefinition extends BaseModel
{
    protected $table = 'indicator_definitions';

    protected $fillable = [
        'name',
        'code',
        'description',
        'variables',
        'formula',
        'methodology_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'variables' => 'array',
        'formula' => 'array',
        'is_active' => 'boolean',
    ];

    public function methodology()
    {
        return $this->belongsTo(IndicatorMethodology::class, 'methodology_id');
    }

    public function variableRows()
    {
        return $this->hasMany(IndicatorDefinitionVariable::class, 'indicator_definition_id');
    }
}
