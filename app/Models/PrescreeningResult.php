<?php

namespace App\Models;

use App\Models\BaseModel;

class PrescreeningResult extends BaseModel
{
    protected $fillable = [
        'submission_id',
        'prescreening_template_id',
        'total_criteria',
        'passed_criteria',
        'failed_criteria',
        'final_status',
        'evaluated_by',
        'evaluated_at',

        // 🔑 REWORK / LOCKING COLUMNS
        'is_locked',
        'rework_requested_by',
        'rework_requested_at',
    ];

    public $timestamps = false;

    protected $casts = [
        'evaluated_at'        => 'datetime',
        'rework_requested_at' => 'datetime',
        'is_locked'           => 'boolean',
    ];

    /* ===============================
     | RELATIONSHIPS
     =============================== */

    public function submission()
    {
        return $this->belongsTo(FormSubmission::class, 'submission_id');
    }

    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public function reworkRequester()
    {
        return $this->belongsTo(User::class, 'rework_requested_by');
    }

    /* ===============================
     | HELPERS
     =============================== */

    public function isEditableBy($user): bool
    {
        // 🔒 Locked → not editable
        if ($this->is_locked) {
            return false;
        }

        // 👤 Only evaluator can edit
        return $this->evaluated_by === $user->id;
    }
}
