<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ReworkRequest extends BaseModel
{
    use HasFactory;

    private const SOURCE_AUDIT_FIELDS = [
        'evaluation_submission_id',
        'evaluation_assignment_id',
        'procurement_id',
        'form_submission_id',
        'evaluation_id',
        'evaluator_id',
        'requested_by',
        'cycle',
        'message',
        'reason',
        'snapshot_schema_version',
        'requested_at',
        'original_submitted_at',
        'source_revision_number',
        'source_snapshot',
        'source_snapshot_hash',
    ];

    private const COMPLETION_AUDIT_FIELDS = [
        'status',
        'completed_by',
        'completed_at',
        'completed_revision_number',
        'completed_snapshot',
        'completed_snapshot_hash',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'evaluation_submission_id',
        'evaluation_assignment_id',
        'procurement_id',
        'form_submission_id',
        'evaluation_id',
        'evaluator_id',
        'requested_by',
        'completed_by',
        'cycle',
        'message',
        'reason',
        'snapshot_schema_version',
        'status',
        'requested_at',
        'original_submitted_at',
        'completed_at',
        'source_revision_number',
        'completed_revision_number',
        'source_snapshot',
        'source_snapshot_hash',
        'completed_snapshot',
        'completed_snapshot_hash',
        'notified_at',
        'notification_error',
    ];

    protected $casts = [
        'cycle' => 'integer',
        'snapshot_schema_version' => 'integer',
        'requested_at' => 'datetime',
        'original_submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'source_revision_number' => 'integer',
        'completed_revision_number' => 'integer',
        'source_snapshot' => 'array',
        'completed_snapshot' => 'array',
        'notified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            if ($request->getRawOriginal('evaluation_submission_id') !== null
                && $request->isDirty(self::SOURCE_AUDIT_FIELDS)) {
                throw new LogicException('The source evaluation rework audit record is immutable.');
            }

            if ($request->getRawOriginal('completed_snapshot') !== null
                && $request->isDirty(self::COMPLETION_AUDIT_FIELDS)) {
                throw new LogicException('The completed evaluation rework audit record is immutable.');
            }
        });
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(EvaluationSubmission::class, 'evaluation_submission_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(EvaluationAssignment::class, 'evaluation_assignment_id');
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class, 'form_submission_id');
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
