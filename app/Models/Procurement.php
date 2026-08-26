<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Support\Str;
use App\Models\EvaluationAssignment;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Procurement extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'resource_id',
        'consortium_id',
        'think_tank_member_id',
        'think_tank_procurement_plan_id',
        'procurement_owner_type',
        'oversight_status',
        'governance_node_id',
        'title',
        'slug',
        'reference_no',
        'description',
        'fiscal_year',
        'application_start_date',
        'application_end_date',
        'application_duration_days',
        'estimated_budget',
        'status',
        'visibility_type',
        'cover_image_path',
        'publication_version',
        'recalled_at',
        'recalled_by',
        'recall_reason',
        'republished_at',
        'vendor_categories',
        'awarded_submission_id',
        'awarded_vendor_id',
        'awarded_at',
        'created_by',
        'deleted_by',
    ];

    protected $casts = [
        'application_start_date' => 'date',
        'application_end_date' => 'date',
        'publication_version' => 'integer',
        'recalled_at' => 'datetime',
        'republished_at' => 'datetime',
        'vendor_categories' => 'array',
        'awarded_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function isApplicationOpen(): bool
    {
        if ($this->trashed() || $this->status !== 'published') {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->application_start_date && $today->lt($this->application_start_date)) {
            return false;
        }

        if (!$this->application_end_date) {
            return true;
        }

        return $today->lte($this->application_end_date);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        $path = str_replace('\\', '/', trim((string) $this->cover_image_path));

        if ($path === '' || ! Str::startsWith($path, 'procurement-covers/') || str_contains($path, '../')) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function autoCloseIfExpired(): bool
    {
        if ($this->trashed()) {
            return false;
        }

        if ($this->status === 'published' && $this->application_end_date && now()->startOfDay()->gt($this->application_end_date)) {
            $this->update(['status' => 'closed']);
            return true;
        }

        return false;
    }

    /* =========================================
     | RELATIONSHIPS
     ========================================= */

    public function resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function consortium()
    {
        return $this->belongsTo(Consortium::class, 'consortium_id');
    }

    public function thinkTankMember()
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function thinkTankProcurementPlan()
    {
        return $this->belongsTo(ThinkTankProcurementPlan::class, 'think_tank_procurement_plan_id');
    }

    /**
     * Procurement has many dynamic forms
     * (linked via dynamic_forms.procurement_id)
     */
    public function forms()
    {
        return $this->hasMany(DynamicForm::class, 'procurement_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProcurementDocument::class)->orderBy('created_at');
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function evaluations()
    {
        return $this->belongsToMany(
            Evaluation::class,
            'procurement_evaluations',
            'procurement_id',
            'evaluation_id'
        );
    }

    public function directEvaluations()
    {
        return $this->hasMany(Evaluation::class, 'procurement_id');
    }

    public function thinkTankReviews()
    {
        return $this->hasMany(ThinkTankProcurementReview::class, 'procurement_id');
    }

    public function contractNegotiations()
    {
        return $this->hasMany(ProcurementContractNegotiation::class, 'procurement_id');
    }

    public function purchaseOrders()
    {
        return $this->hasMany(ProcurementPurchaseOrder::class, 'procurement_id');
    }

    public function invoices()
    {
        return $this->hasMany(ProcurementInvoice::class, 'procurement_id');
    }

    public function deliverables()
    {
        return $this->hasMany(ProcurementDeliverable::class, 'procurement_id');
    }

    public function awardedSubmission()
    {
        return $this->belongsTo(FormSubmission::class, 'awarded_submission_id');
    }

    public function awardedVendor()
    {
        return $this->belongsTo(User::class, 'awarded_vendor_id');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function evaluatorAssignments()
    {
        return $this->hasMany(EvaluationAssignment::class);
    }

    public function thinkTankPlanningItem()
    {
        return $this->hasOne(ThinkTankProcurementItem::class, 'procurement_id');
    }

    /* =========================================
     | ROUTE MODEL BINDING
     ========================================= */

    /**
     * Use slug instead of ID for route binding
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /* =========================================
     | MODEL EVENTS – SLUG GENERATION
     ========================================= */

    protected static function booted()
    {
        static::creating(function ($procurement) {

            if (empty($procurement->slug)) {

                $baseSlug = Str::slug($procurement->title);
                $slug = $baseSlug;
                $counter = 1;

                // Ensure slug uniqueness
                while (self::withTrashed()->where('slug', $slug)->exists()) {
                    $slug = $baseSlug . '-' . $counter++;
                }

                $procurement->slug = $slug;
            }
        });

        static::updating(function ($procurement) {

            // Only regenerate slug if title changed and slug is empty
            if (
                empty($procurement->slug) &&
                $procurement->isDirty('title')
            ) {
                $baseSlug = Str::slug($procurement->title);
                $slug = $baseSlug;
                $counter = 1;

                while (
                    self::withTrashed()->where('slug', $slug)
                        ->where('id', '!=', $procurement->id)
                        ->exists()
                ) {
                    $slug = $baseSlug . '-' . $counter++;
                }

                $procurement->slug = $slug;
            }
        });

        static::forceDeleted(function (Procurement $procurement) {
            $procurementId = $procurement->getKey();
            $coverImagePath = $procurement->cover_image_path;

            DB::afterCommit(function () use ($procurementId, $coverImagePath): void {
                try {
                    $localFilesDeleted = Storage::disk('local')
                        ->deleteDirectory("procurements/{$procurementId}");
                    $coverImageDeleted = ! $coverImagePath
                        || Storage::disk('public')->delete($coverImagePath);

                    if (! $localFilesDeleted || ! $coverImageDeleted) {
                        Log::warning('Deleted procurement left files requiring manual cleanup.', [
                            'procurement_id' => $procurementId,
                            'local_directory_deleted' => $localFilesDeleted,
                            'cover_image_deleted' => $coverImageDeleted,
                        ]);
                    }
                } catch (\Throwable $exception) {
                    Log::warning('Deleted procurement files could not be fully removed.', [
                        'procurement_id' => $procurementId,
                        'exception' => $exception,
                    ]);
                }
            });
        });
    }


 public function prescreeningAssignment()
{
    return $this->hasOne(PrescreeningTemplateProcurement::class);
}

public function prescreeningTemplate()
{
    return $this->hasOneThrough(
        PrescreeningTemplate::class,
        PrescreeningTemplateProcurement::class,
        'procurement_id',
        'id',
        'id',
        'prescreening_template_id'
    );
}

 public function prescreeningUsers()
{
    return $this->belongsToMany(
        User::class,
        'prescreening_assignments',
        'procurement_id',
        'user_id'
    )->withPivot(['assigned_by', 'assigned_at']);
}


public function activeForm()
{
    return $this->hasOne(DynamicForm::class, 'procurement_id')
        ->where('status', 'approved')
        ->where('is_active', true);
}


public function evaluationAssignments()
{
        return $this->hasMany(EvaluationAssignment::class);
}

public function prescreeningAssignments()
{
    return $this->hasMany(PrescreeningAssignment::class);
}







}
