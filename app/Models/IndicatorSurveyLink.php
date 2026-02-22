<?php

namespace App\Models;

class IndicatorSurveyLink extends BaseModel
{
    protected $table = 'me_indicator_survey_links';

    protected $fillable = [
        'indicator_id',
        'methodology_id',
        'public_token',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function indicator()
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    public function methodology()
    {
        return $this->belongsTo(IndicatorMethodology::class, 'methodology_id');
    }

    public function responses()
    {
        return $this->hasMany(IndicatorSurveyResponse::class, 'survey_link_id');
    }
}

