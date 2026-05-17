<?php

namespace App\Models;

class ApprovedWorkPlanItemReview extends BaseModel
{
    protected $table = 'approved_work_plan_item_reviews';

    protected $fillable = [
        'purchase_request_item_id',
        'program_funding_id',
        'funder_id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'document_type',
        'document_path',
        'document_name',
        'document_uploaded_by',
        'document_uploaded_at',
        'tor_path',
        'tor_name',
        'tor_uploaded_at',
        'concept_note_path',
        'concept_note_name',
        'concept_note_uploaded_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'document_uploaded_at' => 'datetime',
        'tor_uploaded_at' => 'datetime',
        'concept_note_uploaded_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }

    public function programFunding()
    {
        return $this->belongsTo(ProgramFunding::class, 'program_funding_id');
    }

    public function funder()
    {
        return $this->belongsTo(Funder::class, 'funder_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function documentUploader()
    {
        return $this->belongsTo(User::class, 'document_uploaded_by');
    }
}
