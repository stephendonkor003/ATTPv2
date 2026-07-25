<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeMissionReport extends BaseModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_ARCHIVED = 'archived';

    protected $table = 'me_mission_reports';

    protected $fillable = [
        'template_id', 'portfolio_id', 'project_component_id', 'think_tank_member_id',
        'report_number', 'title', 'location', 'mission_start_date', 'mission_end_date',
        'action_due_at', 'team_members', 'objectives', 'methodology', 'executive_summary',
        'key_findings', 'recommendations', 'corrective_actions', 'responsible_parties',
        'lessons_learned', 'conclusion', 'status', 'created_by', 'updated_by',
        'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at', 'review_notes',
        'archived_by', 'archived_at', 'archive_notes',
    ];

    protected $casts = [
        'mission_start_date' => 'date',
        'mission_end_date' => 'date',
        'action_due_at' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function template(): BelongsTo { return $this->belongsTo(MeMissionReportTemplate::class, 'template_id'); }
    public function portfolio(): BelongsTo { return $this->belongsTo(Sector::class, 'portfolio_id'); }
    public function projectComponent(): BelongsTo { return $this->belongsTo(Project::class, 'project_component_id'); }
    public function thinkTank(): BelongsTo { return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id'); }
    public function createdBy(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function archivedBy(): BelongsTo { return $this->belongsTo(User::class, 'archived_by'); }
    public function documents(): HasMany { return $this->hasMany(MeMissionReportDocument::class, 'mission_report_id'); }

    public function isEditable(): bool { return $this->status === self::STATUS_DRAFT; }
    public function isSubmitted(): bool { return $this->status === self::STATUS_SUBMITTED; }
    public function isReviewed(): bool { return $this->status === self::STATUS_REVIEWED; }
    public function isArchived(): bool { return $this->status === self::STATUS_ARCHIVED; }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => $this->review_notes ? 'Returned for Revision' : 'Draft',
            self::STATUS_SUBMITTED => 'Submitted for Review',
            self::STATUS_REVIEWED => 'Approved',
            self::STATUS_ARCHIVED => 'Archived',
            default => str($this->status)->headline()->toString(),
        };
    }

    public function completionIssues(): array
    {
        $required = [
            'Mission title' => $this->title,
            'Location' => $this->location,
            'Mission team' => $this->team_members,
            'Objectives and scope' => $this->objectives,
            'Methodology' => $this->methodology,
            'Executive summary' => $this->executive_summary,
            'Key findings' => $this->key_findings,
            'Recommendations' => $this->recommendations,
            'Corrective actions' => $this->corrective_actions,
            'Responsible parties' => $this->responsible_parties,
            'Lessons learned' => $this->lessons_learned,
            'Conclusion' => $this->conclusion,
        ];

        return collect($required)->filter(fn ($value) => blank($value))->keys()->values()->all();
    }
}
