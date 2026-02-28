<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberStateNationalData extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_member_state_national_data';

    protected $fillable = [
        'member_state_id',
        'recorded_on',
        'reporting_period_type',
        'reporting_year',
        'reporting_month',
        'reporting_day',
        'aspiration_id',
        'goal_id',
        'indicator_name',
        'indicator_value',
        'unit',
        'cooperation_score',
        'progress_status',
        'people_reached',
        'households_impacted',
        'budget_allocated_usd',
        'budget_executed_usd',
        'agenda_relevance_summary',
        'policy_actions',
        'institutional_steps',
        'livelihood_impact_summary',
        'public_engagement_summary',
        'awareness_outreach_channels',
        'national_projects_programs',
        'youth_women_inclusion_actions',
        'partnerships_support',
        'commodity_preservation_policies',
        'commodity_value_addition',
        'risk_challenges',
        'next_steps_commitments',
        'citizen_feedback_summary',
        'evidence_links',
        'flagship_projects_supported',
        'commodity_focus',
        'agenda_awareness_score',
        'flagship_awareness_score',
        'outreach_coverage_score',
        'data_source',
        'notes',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'recorded_on' => 'date',
        'reporting_year' => 'integer',
        'reporting_month' => 'integer',
        'reporting_day' => 'integer',
        'indicator_value' => 'decimal:4',
        'cooperation_score' => 'decimal:2',
        'people_reached' => 'integer',
        'households_impacted' => 'integer',
        'budget_allocated_usd' => 'decimal:2',
        'budget_executed_usd' => 'decimal:2',
        'flagship_projects_supported' => 'array',
        'commodity_focus' => 'array',
        'agenda_awareness_score' => 'decimal:2',
        'flagship_awareness_score' => 'decimal:2',
        'outreach_coverage_score' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function scopeApproved($query)
    {
        return $query->where('review_status', 'approved');
    }

    public function memberState(): BelongsTo
    {
        return $this->belongsTo(AuMemberState::class, 'member_state_id');
    }

    public function aspiration(): BelongsTo
    {
        return $this->belongsTo(AuAspiration::class, 'aspiration_id');
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(AuGoal::class, 'goal_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
