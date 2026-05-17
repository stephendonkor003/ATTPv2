<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequestItem extends BaseModel
{
    protected $table = 'myb_purchase_request_items';

    protected $fillable = [
        'purchase_request_id',
        'resource_category_id',
        'resource_id',
        'milestone',
        'milestone_date',
        'amount',
        'work_plan_source',
        'work_plan_sort_order',
        'work_plan_serial',
        'implemented_by',
        'budget_code',
        'object_type',
        'estimated_amount',
        'work_plan_months',
        'work_plan_audience',
        'work_plan_units',
        'work_plan_payment_basis',
        'work_plan_unit_rate',
        'work_plan_person_months',
        'work_plan_monthly_amount',
        'intermediate_indicator',
        'result_indicator',
        'observations',
        'world_bank_comments',
        'attp_secretariat_comments',
        'world_bank_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'estimated_amount' => 'decimal:2',
        'work_plan_months' => 'array',
        'work_plan_unit_rate' => 'decimal:2',
        'work_plan_person_months' => 'integer',
        'work_plan_monthly_amount' => 'decimal:2',
        'world_bank_amount' => 'decimal:2',
        'work_plan_sort_order' => 'integer',
        'milestone_date' => 'date',
    ];

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function resourceCategory(): BelongsTo
    {
        return $this->belongsTo(ResourceCategory::class, 'resource_category_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function awpReview()
    {
        return $this->hasOne(ApprovedWorkPlanItemReview::class, 'purchase_request_item_id');
    }
}
