<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Support\Str;
use App\Models\EvaluationAssignment;

class Procurement extends BaseModel
{
    protected $fillable = [
        'resource_id',
        'governance_node_id',
        'title',
        'slug',
        'reference_no',
        'description',
        'fiscal_year',
        'estimated_budget',
        'status',
        'created_by',
    ];

    /* =========================================
     | RELATIONSHIPS
     ========================================= */

    public function resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id');
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
