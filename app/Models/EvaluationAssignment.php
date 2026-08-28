<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationAssignment extends BaseModel
{
    use HasFactory;

    public const STAGE_APPLICATION = 'application';

    public const STAGE_TECHNICAL_PROPOSAL = 'technical_proposal';

    public const WORKFLOW_STAGES = [
        self::STAGE_APPLICATION,
        self::STAGE_TECHNICAL_PROPOSAL,
    ];

    protected $fillable = [
        'evaluation_id',
        'procurement_id',
        'form_submission_id',
        'workflow_stage',
        'technical_proposal_round_id',
        'user_id',
        'assigned_by',
        'assigned_at',
        'status',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function technicalProposalRound(): BelongsTo
    {
        return $this->belongsTo(EoiTechnicalProposalRound::class, 'technical_proposal_round_id');
    }

    public function evaluationSubmissions(): HasMany
    {
        return $this->hasMany(EvaluationSubmission::class, 'evaluation_assignment_id');
    }

    public function workflowStage(): string
    {
        return filled($this->workflow_stage)
            ? (string) $this->workflow_stage
            : self::STAGE_APPLICATION;
    }

    public function isApplicationStage(): bool
    {
        return $this->workflowStage() === self::STAGE_APPLICATION;
    }

    public function isTechnicalProposalStage(): bool
    {
        return $this->workflowStage() === self::STAGE_TECHNICAL_PROPOSAL;
    }

    public function isTechnicalProposal(): bool
    {
        return $this->isTechnicalProposalStage();
    }
}
