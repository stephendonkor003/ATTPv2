<?php

// App\Models\FormSubmissionValue.php
namespace App\Models;

use App\Models\BaseModel;

class FormSubmissionValue extends BaseModel
{
    protected $fillable = [
        'submission_id',
        'field_key',
        'value'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }
}