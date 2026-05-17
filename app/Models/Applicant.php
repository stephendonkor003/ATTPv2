<?php

namespace App\Models;

use App\Models\BaseModel;

class Applicant extends BaseModel
{
    protected $fillable = [
        'think_tank_name',
        'country',
        'sub_region',
        'focus_areas',
        'is_partnership',
        'email',
        'members_names',
        'consortium_name',
        'lead_think_tank_name',
        'lead_think_tank_country',
        'consortium_region',
        'covered_countries',
        'application_form',
        'legal_registration',
        'trustees_formation',
        'audited_reports',
        'commitment_letter',
        'work_plan_budget',
        'cv_coordinator',
        'cv_deputy',
        'cv_team_members',
        'past_research',
        'code',
    ];

    /**
     * Many-to-many: Applicants <-> Users (Evaluators/Reviewers)
     */
    public function evaluators()
    {
        return $this->belongsToMany(User::class, 'applicant_user')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    /**
     * One-to-many: Applicant has many Evaluations
     */
    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'applicant_id');
    }

    public function prescreeningCriteria()
    {
        return $this->hasOne(PrescreeningCriterion::class, 'applicant_id');
    }

    public function prescreening()
    {
        return $this->hasOne(PrescreeningCriterion::class, 'applicant_id');
    }

    public function siteVisitEvaluations()
    {
        return $this->hasMany(SiteVisitEvaluation::class, 'consortium_id');
    }

    public function consortium()
    {
        return $this->hasOne(Consortium::class, 'lead_applicant_id');
    }

    public function consortiumMemberships()
    {
        return $this->hasMany(ConsortiumThinkTank::class, 'applicant_id');
    }
}
