<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EoiTechnicalProposalRuleApplication extends BaseModel
{
    protected $dateFormat = 'Y-m-d H:i:s';

    public const FINDING_COMPLIANT = 'compliant';

    public const FINDING_NON_COMPLIANT = 'non_compliant';

    public const FINDING_WAIVED = 'waived';

    public const FINDING_NOT_APPLICABLE = 'not_applicable';

    public const EFFECT_NONE = 'none';

    public const EFFECT_DISQUALIFY = 'disqualify';

    protected $fillable = [
        'candidate_id',
        'rule_id',
        'proposal_submission_id',
        'rule_code_snapshot',
        'rule_title_snapshot',
        'rule_is_disqualifying_snapshot',
        'finding',
        'effect',
        'rationale',
        'applied_by',
        'applied_at',
        'revoked_by',
        'revoked_at',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'rule_is_disqualifying_snapshot' => 'boolean',
            'applied_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(EoiTechnicalProposalCandidate::class, 'candidate_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(EoiTechnicalProposalRule::class, 'rule_id');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(EoiTechnicalProposalSubmission::class, 'proposal_submission_id');
    }

    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function revoker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
