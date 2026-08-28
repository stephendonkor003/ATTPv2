<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EoiReportCommunicationRecipient extends BaseModel
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'communication_id',
        'form_submission_id',
        'user_id',
        'recipient_name',
        'recipient_email',
        'outcome_code',
        'outcome_label',
        'workflow_decision',
        'delivery_status',
        'delivery_error',
        'emailed_at',
        'read_at',
        'proposal_submitted_at',
        'proposal_message',
        'record_file_path',
        'record_file_name',
        'record_mime_type',
        'record_file_size',
        'record_sha256',
    ];

    protected function casts(): array
    {
        return [
            'emailed_at' => 'datetime',
            'read_at' => 'datetime',
            'proposal_submitted_at' => 'datetime',
            'record_file_size' => 'integer',
        ];
    }

    public function communication(): BelongsTo
    {
        return $this->belongsTo(EoiReportCommunication::class, 'communication_id');
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function proposalDocuments(): HasMany
    {
        return $this->hasMany(EoiReportProposalDocument::class, 'recipient_id');
    }
}
