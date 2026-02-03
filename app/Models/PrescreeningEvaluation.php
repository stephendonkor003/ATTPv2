<?php

namespace App\Models;

use App\Models\BaseModel;

class PrescreeningEvaluation extends BaseModel
{
    protected $fillable = [
        'submission_id',
        'prescreening_template_id',
        'criterion_id',
        'evaluator_id',
        'evaluation_value',
        'is_passed',
        'remarks',
        'evaluated_at',
    ];

    public $timestamps = false;

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function criterion()
    {
        return $this->belongsTo(PrescreeningCriterion::class, 'criterion_id');
    }
}
