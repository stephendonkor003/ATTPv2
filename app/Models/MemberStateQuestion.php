<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberStateQuestion extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_member_state_questions';

    protected $fillable = [
        'member_state_id',
        'asked_on',
        'subject',
        'question_text',
        'priority',
        'status',
        'responsible_officer_id',
        'responsible_officer_email',
        'answer_text',
        'answered_by',
        'answered_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'asked_on' => 'date',
        'answered_at' => 'datetime',
    ];

    public function memberState(): BelongsTo
    {
        return $this->belongsTo(AuMemberState::class, 'member_state_id');
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function responsibleOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_officer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
