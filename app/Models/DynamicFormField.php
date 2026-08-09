<?php

namespace App\Models;

use App\Models\BaseModel;

class DynamicFormField extends BaseModel
{
    protected $table = 'dynamic_form_fields';

    protected $fillable = [
        'form_id',
        'label',
        'help_text',
        'placeholder',
        'field_key',
        'field_type',
        'is_required',
        'options',
        'validation_rules',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'validation_rules' => 'array',
    ];

    public function optionValues(): array
    {
        return collect(preg_split('/[\r\n,]+/', (string) $this->options))
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Parent Form
     */
    public function form()
    {
        return $this->belongsTo(DynamicForm::class, 'form_id');
    }
}
