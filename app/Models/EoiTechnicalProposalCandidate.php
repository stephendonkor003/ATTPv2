<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EoiTechnicalProposalCandidate extends BaseModel
{
    protected $dateFormat = 'Y-m-d H:i:s';

    public const STATUS_INVITED = 'invited';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_LATE = 'late';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_QUALIFIED = 'qualified';

    public const STATUS_DISQUALIFIED = 'disqualified';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'round_id',
        'form_submission_id',
        'user_id',
        'eoi_outcome_code',
        'eoi_outcome_label',
        'workflow_decision',
        'status',
        'invited_at',
        'first_submitted_at',
        'last_submitted_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'first_submitted_at' => 'datetime',
            'last_submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(EoiTechnicalProposalRound::class, 'round_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(EoiTechnicalProposalSubmission::class, 'candidate_id')
            ->orderBy('revision_number');
    }

    public function latestSubmission(): HasOne
    {
        return $this->hasOne(EoiTechnicalProposalSubmission::class, 'candidate_id')
            // Avoid Eloquent's ofMany UUID tie-breaker: PostgreSQL does not
            // support MAX(uuid). Ordered HasOne eager loading still selects
            // the highest revision for each candidate.
            ->orderByDesc('revision_number')
            ->orderByDesc('created_at');
    }

    public function ruleApplications(): HasMany
    {
        return $this->hasMany(EoiTechnicalProposalRuleApplication::class, 'candidate_id');
    }
}
