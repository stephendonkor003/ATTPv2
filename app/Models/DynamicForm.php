<?php

namespace App\Models;

use App\Models\BaseModel;

class DynamicForm extends BaseModel
{
    protected $table = 'dynamic_forms';

    protected $fillable = [
        'resource_id',
        'name',
        'applies_to',
        'status',
        'is_active',
        'created_by',
        'procurement_id',
        'submitted_at',
        'approved_at',
        'approved_by',
        'rejection_reason',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
    ];

    /* ================= RELATIONSHIPS ================= */

    public function fields()
    {
        return $this->hasMany(DynamicFormField::class, 'form_id')
                    ->orderBy('sort_order', 'asc');
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function procurement()
    {
        return $this->belongsTo(Procurement::class, 'procurement_id');
    }

    /* ================= BUSINESS HELPERS ================= */

    public function canEdit(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /* ================= SCOPES ================= */

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved')
                     ->where('is_active', true);
    }

    public function scopeForStage($query, string $stage)
    {
        return $query->where('applies_to', $stage);
    }


}