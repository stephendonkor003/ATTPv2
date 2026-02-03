<?php

// App\Models\EvaluationResult.php
namespace App\Models;

use App\Models\BaseModel;

class EvaluationResult extends BaseModel
{
    protected $fillable = [
        'submission_id',
        'form_id',
        'field_key',
        'evaluator_id',
        'score',
        'comment',
        'evaluated_at'
    ];

    protected $dates = ['evaluated_at'];

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class);
    }

    public function form()
    {
        return $this->belongsTo(DynamicForm::class);
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }
}