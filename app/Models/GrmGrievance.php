<?php

namespace App\Models;

use Illuminate\Support\Str;

class GrmGrievance extends BaseModel
{
    public const STATUSES = [
        'submitted' => 'Submitted',
        'acknowledged' => 'Acknowledged',
        'under_review' => 'Under Review',
        'responded' => 'Responded',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'escalated' => 'Escalated',
    ];

    protected $fillable = [
        'case_number',
        'program_id',
        'governance_node_id',
        'level_id',
        'escalation_rule_id',
        'submitted_by',
        'assigned_to',
        'submitter_name',
        'submitter_email',
        'submitter_phone',
        'channel',
        'subject',
        'description',
        'status',
        'is_anonymous',
        'submitted_at',
        'acknowledged_at',
        'responded_at',
        'resolved_at',
        'due_response_at',
        'due_resolution_at',
        'last_reminder_sent_at',
        'last_escalated_at',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'submitted_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'responded_at' => 'datetime',
        'resolved_at' => 'datetime',
        'due_response_at' => 'datetime',
        'due_resolution_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'last_escalated_at' => 'datetime',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function level()
    {
        return $this->belongsTo(GrmLevel::class, 'level_id');
    }

    public function escalationRule()
    {
        return $this->belongsTo(GrmEscalationRule::class, 'escalation_rule_id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events()
    {
        return $this->hasMany(GrmGrievanceEvent::class, 'grievance_id')->latest();
    }

    public function attachments()
    {
        return $this->hasMany(GrmGrievanceAttachment::class, 'grievance_id')->latest();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? Str::headline((string) $this->status);
    }

    public function getIsUnattendedAttribute(): bool
    {
        return in_array($this->status, ['submitted', 'acknowledged', 'under_review', 'escalated'], true)
            && ! $this->responded_at
            && ! $this->resolved_at;
    }

    public static function generateCaseNumber(?Program $program = null): string
    {
        $prefix = 'GRM';

        if ($program && filled($program->name)) {
            $prefix .= '-' . Str::upper(Str::substr(Str::slug($program->name, ''), 0, 4));
        }

        do {
            $number = $prefix . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (static::query()->where('case_number', $number)->exists());

        return $number;
    }
}
