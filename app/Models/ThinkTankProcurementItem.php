<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThinkTankProcurementItem extends BaseModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVISION_REQUESTED = 'revision_requested';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_NO_OBJECTION = 'no_objection_obtained';
    public const STATUS_PUBLISHED = 'published';

    public const ACTIVITY_STATUS_DRAFT = 'Draft';
    public const ACTIVITY_STATUS_SUBMITTED = 'Submitted';
    public const ACTIVITY_STATUS_ATTP_APPROVED = 'Approved by ATTP Secretariat';
    public const ACTIVITY_STATUS_WORLD_BANK_APPROVED = 'Approved by World Bank — No Objection';

    protected $table = 'attp_think_tank_procurement_items';

    protected $fillable = [
        'plan_id', 'item_code', 'source_reference', 'loan_credit_no', 'component',
        'source_in_process', 'source_process_status', 'source_activity_status',
        'source_document_type', 'source_sea_sh_risk', 'title', 'description',
        'procurement_category', 'procurement_method', 'market_approach', 'review_type',
        'quantity', 'unit', 'estimated_unit_cost', 'estimated_amount', 'currency',
        'planned_quarter', 'planned_start_date', 'planned_end_date', 'status',
        'review_reason', 'reviewed_by', 'reviewed_at', 'step_reference',
        'step_exported_at', 'step_exported_by', 'no_objection_reference',
        'no_objection_date', 'no_objection_notes', 'no_objection_by',
        'no_objection_recorded_at', 'procurement_id', 'source_file', 'source_sheet',
        'source_row', 'source_payload', 'planned_milestones', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'estimated_unit_cost' => 'decimal:2',
        'estimated_amount' => 'decimal:2',
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'reviewed_at' => 'datetime',
        'step_exported_at' => 'datetime',
        'no_objection_date' => 'date',
        'no_objection_recorded_at' => 'datetime',
        'source_payload' => 'array',
        'planned_milestones' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ThinkTankProcurementPlan::class, 'plan_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ThinkTankProcurementDocument::class, 'item_id')->oldest();
    }

    public function events(): HasMany
    {
        return $this->hasMany(ThinkTankProcurementEvent::class, 'item_id')->latest('created_at');
    }

    public function procurement(): BelongsTo
    {
        return $this->belongsTo(Procurement::class)->withTrashed();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hasTermsOfReference(): bool
    {
        if ($this->relationLoaded('documents')) {
            return $this->documents->contains('document_type', 'tor');
        }

        return $this->documents()->where('document_type', 'tor')->exists();
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_REVISION_REQUESTED,
            self::STATUS_REJECTED,
        ], true) && in_array($this->plan?->status, [
            ThinkTankProcurementPlan::STATUS_DRAFT,
            ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
            ThinkTankProcurementPlan::STATUS_REJECTED,
        ], true);
    }

    public static function activityStatusFor(?string $workflowStatus): string
    {
        return match ($workflowStatus) {
            self::STATUS_SUBMITTED => self::ACTIVITY_STATUS_SUBMITTED,
            self::STATUS_APPROVED => self::ACTIVITY_STATUS_ATTP_APPROVED,
            self::STATUS_NO_OBJECTION, self::STATUS_PUBLISHED => self::ACTIVITY_STATUS_WORLD_BANK_APPROVED,
            default => self::ACTIVITY_STATUS_DRAFT,
        };
    }

    public function workflowActivityStatus(): string
    {
        return self::activityStatusFor($this->status);
    }
}
