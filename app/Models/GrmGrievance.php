<?php

namespace App\Models;

use Illuminate\Support\Str;

class GrmGrievance extends BaseModel
{
    public const CHANNELS = [
        'portal' => 'Internal Portal (Legacy)',
        'internal_portal' => 'ATTP Internal Portal',
        'public_portal' => 'Public Website',
        'think_tank_portal' => 'Think Tank Portal',
        'funding_partner_portal' => 'Funding Partner Portal',
        'vendor_portal' => 'Vendor Portal',
        'ttl_portal' => 'Task Team Leader Portal',
        'member_state_portal' => 'Member State Portal',
        'email' => 'Email',
        'phone' => 'Phone',
        'walk_in' => 'Walk-in',
        'field_visit' => 'Field Visit',
        'other' => 'Other',
    ];

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
        'anonymous_contact_method',
        'anonymous_contact_value',
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
        'anonymous_contact_value' => 'encrypted',
        'submitted_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'responded_at' => 'datetime',
        'resolved_at' => 'datetime',
        'due_response_at' => 'datetime',
        'due_resolution_at' => 'datetime',
        'last_reminder_sent_at' => 'datetime',
        'last_escalated_at' => 'datetime',
    ];

    protected $hidden = [
        'anonymous_contact_value',
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

    public function getChannelLabelAttribute(): string
    {
        return self::CHANNELS[$this->channel] ?? Str::headline((string) $this->channel);
    }

    public function getIsUnattendedAttribute(): bool
    {
        return in_array($this->status, ['submitted', 'acknowledged', 'under_review', 'escalated'], true)
            && ! $this->responded_at
            && ! $this->resolved_at;
    }

    public function replyEmail(): ?string
    {
        if ($this->is_anonymous) {
            return $this->anonymous_contact_method === 'email'
                ? $this->anonymous_contact_value
                : null;
        }

        return $this->submitter_email;
    }

    public function replyPhone(): ?string
    {
        if ($this->is_anonymous) {
            return $this->anonymous_contact_method === 'phone'
                ? $this->anonymous_contact_value
                : null;
        }

        return $this->submitter_phone;
    }

    public function confidentialReplyContact(): ?string
    {
        return $this->replyEmail() ?: $this->replyPhone();
    }

    public static function generateCaseNumber(?Program $program = null): string
    {
        $prefix = 'GRM';

        if ($program && filled($program->name)) {
            $prefix .= '-'.Str::upper(Str::substr(Str::slug($program->name, ''), 0, 4));
        }

        do {
            $number = $prefix.'-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (static::query()->where('case_number', $number)->exists());

        return $number;
    }
}
