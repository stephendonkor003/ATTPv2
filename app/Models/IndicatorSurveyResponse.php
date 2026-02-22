<?php

namespace App\Models;

class IndicatorSurveyResponse extends BaseModel
{
    protected $table = 'me_indicator_survey_responses';

    protected $fillable = [
        'indicator_id',
        'methodology_id',
        'survey_link_id',
        'respondent_name',
        'respondent_email',
        'respondent_phone',
        'respondent_organization',
        'answers',
        'responsible_user_ids',
        'responsible_snapshot',
        'submitted_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'answers' => 'array',
        'responsible_user_ids' => 'array',
        'responsible_snapshot' => 'array',
        'submitted_at' => 'datetime',
    ];

    public function indicator()
    {
        return $this->belongsTo(Indicator::class, 'indicator_id');
    }

    public function methodology()
    {
        return $this->belongsTo(IndicatorMethodology::class, 'methodology_id');
    }

    public function surveyLink()
    {
        return $this->belongsTo(IndicatorSurveyLink::class, 'survey_link_id');
    }
}

