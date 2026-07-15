<?php

namespace App\Models;

use App\Models\BaseModel;

class IndicatorMethodology extends BaseModel
{
    protected $table = 'indicator_methodologies';

    protected $fillable = [
        'portfolio_id',
        'name',
        'description',
        'steps',
        'metadata',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function definitions()
    {
        return $this->hasMany(IndicatorDefinition::class, 'methodology_id');
    }

    public function portfolio()
    {
        return $this->belongsTo(Sector::class, 'portfolio_id');
    }

    public function surveyLinks()
    {
        return $this->hasMany(IndicatorSurveyLink::class, 'methodology_id');
    }

    public function surveyResponses()
    {
        return $this->hasMany(IndicatorSurveyResponse::class, 'methodology_id');
    }
}
