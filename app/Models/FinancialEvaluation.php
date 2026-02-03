<?php

namespace App\Models;

use App\Models\BaseModel;

class FinancialEvaluation extends BaseModel
{
    protected $fillable = [
        'applicant_id',
        'evaluator_id',
        'strength_financial_health',
        'gap_financial_health',
        'strength_accuracy',
        'gap_accuracy',
        'strength_revenue',
        'gap_revenue',
        'strength_fund_use',
        'gap_fund_use',
        'strength_liabilities',
        'gap_liabilities',
        'strength_compliance',
        'gap_compliance',
        'overall_financial_assessment',
        'status',
    ];

    public function applicant()
    {
        return $this->belongsTo(Applicant::class, 'applicant_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}
