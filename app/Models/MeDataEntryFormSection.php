<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeDataEntryFormSection extends BaseModel
{
    public const DEFAULT_NAME = 'General information';

    public const DEFAULT_COLOR = '#EFF6FF';

    public const SOFT_BACKGROUND_COLORS = [
        '#EFF6FF',
        '#F0FDF4',
        '#FFFBEB',
        '#FDF2F8',
        '#F5F3FF',
        '#ECFEFF',
        '#FFF7ED',
        '#F8FAFC',
    ];

    protected $table = 'me_data_entry_form_sections';

    protected $fillable = [
        'form_id',
        'section_key',
        'name',
        'description',
        'background_color',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(MeDataEntryForm::class, 'form_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(MeDataEntryFormField::class, 'section_id')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }
}
