<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcurementPlan extends BaseModel
{
    protected $table = 'myb_procurement_plans';

    protected $fillable = [
        'procurement_code',
        'is_code_auto_generated',
        'title',
        'description',
        'activity_id',
        'sub_activity_id',
        'method_planned_id',
        'program_plan_id',
        'geographic_id',
        'stage_id',
        'status_id',
        'step_stage_id',
        'step_approval_id',
        'is_launched',
        'launched_at',
        'estimated_start_date',
        'estimated_end_date',
        'actual_start_date',
        'actual_end_date',
        'estimated_budget',
        'currency',
        'remarks',
        'fiscal_year',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_code_auto_generated' => 'boolean',
        'is_launched' => 'boolean',
        'launched_at' => 'datetime',
        'estimated_start_date' => 'date',
        'estimated_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'estimated_budget' => 'decimal:2',
    ];

    /* =====================================================
     | RELATIONSHIPS
     ===================================================== */

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    public function subActivity(): BelongsTo
    {
        return $this->belongsTo(SubActivity::class, 'sub_activity_id');
    }

    public function methodPlanned(): BelongsTo
    {
        return $this->belongsTo(ProcurementMethodPlanned::class, 'method_planned_id');
    }

    public function programPlan(): BelongsTo
    {
        return $this->belongsTo(ProcurementProgramPlan::class, 'program_plan_id');
    }

    public function geographic(): BelongsTo
    {
        return $this->belongsTo(ProcurementGeographic::class, 'geographic_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProcurementStage::class, 'stage_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProcurementStatus::class, 'status_id');
    }

    public function stepStage(): BelongsTo
    {
        return $this->belongsTo(ProcurementStepStage::class, 'step_stage_id');
    }

    public function stepApproval(): BelongsTo
    {
        return $this->belongsTo(ProcurementStepApproval::class, 'step_approval_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /* =====================================================
     | SCOPES
     ===================================================== */

    public function scopeLaunched($query)
    {
        return $query->where('is_launched', true);
    }

    public function scopeNotLaunched($query)
    {
        return $query->where('is_launched', false);
    }

    public function scopeByFiscalYear($query, $year)
    {
        return $query->where('fiscal_year', $year);
    }

    /* =====================================================
     | HELPERS
     ===================================================== */

    /**
     * Generate a unique procurement code
     * Format: ET-AUC-{random6digits}-{methodAbbr}-{geoAbbr}
     */
    public static function generateCode(?string $methodAbbr = 'CS', ?string $geoAbbr = 'CQS'): string
    {
        $randomNumber = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $code = "ET-AUC-{$randomNumber}-{$methodAbbr}-{$geoAbbr}";

        // Ensure uniqueness
        while (self::where('procurement_code', $code)->exists()) {
            $randomNumber = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $code = "ET-AUC-{$randomNumber}-{$methodAbbr}-{$geoAbbr}";
        }

        return $code;
    }

    /**
     * Calculate estimated end date based on method target days
     */
    public function calculateEstimatedEndDate(): ?string
    {
        if (!$this->estimated_start_date || !$this->methodPlanned) {
            return null;
        }

        return $this->estimated_start_date
            ->addDays($this->methodPlanned->method_target_days)
            ->format('Y-m-d');
    }

    /**
     * Get duration in days
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->estimated_start_date || !$this->estimated_end_date) {
            return null;
        }

        return $this->estimated_start_date->diffInDays($this->estimated_end_date);
    }

    /**
     * Get progress percentage based on dates
     */
    public function getProgressPercentageAttribute(): int
    {
        if (!$this->estimated_start_date || !$this->estimated_end_date) {
            return 0;
        }

        $totalDays = $this->estimated_start_date->diffInDays($this->estimated_end_date);
        if ($totalDays === 0) {
            return 100;
        }

        $elapsedDays = $this->estimated_start_date->diffInDays(now());

        if ($elapsedDays <= 0) {
            return 0;
        }

        if ($elapsedDays >= $totalDays) {
            return 100;
        }

        return (int) round(($elapsedDays / $totalDays) * 100);
    }

    /**
     * Check if the plan is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->estimated_end_date) {
            return false;
        }

        return $this->estimated_end_date->isPast() && !$this->is_launched;
    }

    /**
     * Mark a procurement plan as launched by procurement code.
     */
    public static function markLaunchedByCode(?string $procurementCode): void
    {
        if (!$procurementCode) {
            return;
        }

        self::where('procurement_code', $procurementCode)->update([
            'is_launched' => true,
            'launched_at' => now(),
        ]);
    }
}
