<?php

namespace App\Models;

use App\Models\BaseModel;

class DynamicForm extends BaseModel
{
    protected $table = 'dynamic_forms';

    public const GLOBAL_FIELDS = [
        [
            'label' => 'Name',
            'field_key' => 'official_name',
            'field_type' => 'text',
            'is_required' => true,
            'sort_order' => '0',
        ],
        [
            'label' => 'Email',
            'field_key' => 'official_email',
            'field_type' => 'email',
            'is_required' => true,
            'sort_order' => '1',
        ],
    ];

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

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class, 'form_id');
    }

    /* ================= BUSINESS HELPERS ================= */

    public function canEdit(): bool
    {
        return !$this->hasSubmissions();
    }

    public function hasSubmissions(): bool
    {
        return $this->submissions()->exists();
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

    protected static function booted(): void
    {
        static::created(function (DynamicForm $form): void {
            // Always enforce required default fields for procurement forms,
            // regardless of where the form was created from.
            $form->ensureGlobalFields();
        });
    }

    public static function globalFieldKeys(): array
    {
        return array_column(self::GLOBAL_FIELDS, 'field_key');
    }

    public function ensureGlobalFields(): void
    {
        $createdBy = $this->created_by ?: auth()->id();

        foreach (self::GLOBAL_FIELDS as $field) {
            DynamicFormField::updateOrCreate(
                [
                    'form_id' => $this->id,
                    'field_key' => $field['field_key'],
                ],
                [
                    'label' => $field['label'],
                    'field_type' => $field['field_type'],
                    'is_required' => $field['is_required'],
                    'options' => null,
                    'sort_order' => $field['sort_order'],
                    'created_by' => $createdBy,
                ]
            );
        }
    }

}
