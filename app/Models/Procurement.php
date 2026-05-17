<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Support\Str;
use App\Models\EvaluationAssignment;

class Procurement extends BaseModel
{
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
        'vendor_categories',
        'awarded_submission_id',
        'awarded_vendor_id',
        'awarded_at',
        'created_by',
    ];

    protected $casts = [
        'application_start_date' => 'date',
        'application_end_date' => 'date',
        'vendor_categories' => 'array',
        'awarded_at' => 'datetime',
    ];

    public function isApplicationOpen(): bool
    {
        if ($this->status !== 'published') {
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

    public function autoCloseIfExpired(): bool
    {
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

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
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

    public function evaluatorAssignments()
    {
        return $this->hasMany(EvaluationAssignment::class);
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
                while (self::where('slug', $slug)->exists()) {
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
                    self::where('slug', $slug)
                        ->where('id', '!=', $procurement->id)
                        ->exists()
                ) {
                    $slug = $baseSlug . '-' . $counter++;
                }

                $procurement->slug = $slug;
            }
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
