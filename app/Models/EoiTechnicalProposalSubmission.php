<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EoiTechnicalProposalSubmission extends BaseModel
{
    protected $dateFormat = 'Y-m-d H:i:s';

    public const SOURCE_VENDOR_PORTAL = 'vendor_portal';

    public const SOURCE_ADMIN_CAPTURE = 'admin_capture';

    public const CHANNEL_PORTAL = 'portal';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_PHYSICAL = 'physical';

    public const CHANNEL_COURIER = 'courier';

    public const CHANNEL_OTHER = 'other';

    protected $fillable = [
        'candidate_id',
        'revision_number',
        'source',
        'received_via',
        'received_at',
        'uploaded_at',
        'is_late',
        'minutes_late',
        'cover_note',
        'capture_note',
        'submitted_by',
        'captured_by',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'received_at' => 'datetime',
            'uploaded_at' => 'datetime',
            'is_late' => 'boolean',
            'minutes_late' => 'integer',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(EoiTechnicalProposalCandidate::class, 'candidate_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EoiTechnicalProposalDocument::class, 'proposal_submission_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function capturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }

    public function ruleApplications(): HasMany
    {
        return $this->hasMany(EoiTechnicalProposalRuleApplication::class, 'proposal_submission_id');
    }
}
