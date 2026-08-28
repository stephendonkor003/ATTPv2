<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EoiTechnicalProposalRound extends BaseModel
{
    protected $dateFormat = 'Y-m-d H:i:s';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_CANCELLED = 'cancelled';

    public const LATE_REJECT = 'reject';

    public const LATE_ALLOW_FLAGGED = 'allow_flagged';

    public const LATE_ADMIN_CAPTURE_ONLY = 'admin_capture_only';

    public const REQUIREMENT_REQUIRED = 'required';

    public const REQUIREMENT_ALLOWED = 'allowed';

    public const REQUIREMENT_NOT_ALLOWED = 'not_allowed';

    protected $fillable = [
        'procurement_id',
        'round_number',
        'title',
        'instructions',
        'opens_at',
        'deadline_at',
        'timezone',
        'late_policy',
        'portal_requirement',
        'email_requirement',
        'physical_requirement',
        'status',
        'created_by',
        'published_by',
        'published_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'round_number' => 'integer',
            'opens_at' => 'datetime',
            'deadline_at' => 'datetime',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(EoiTechnicalProposalRule::class, 'round_id')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function templates(): HasMany
    {
        return $this->hasMany(EoiTechnicalProposalTemplate::class, 'round_id')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(EoiTechnicalProposalCandidate::class, 'round_id');
    }

    public function communications(): HasMany
    {
        return $this->hasMany(EoiReportCommunication::class, 'technical_proposal_round_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }
}
