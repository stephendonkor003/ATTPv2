<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiAnnualSiteVisitSection extends BaseModel
{
    protected $table = 'biannual_site_visit_sections';

    protected $fillable = [
        'template_id',
        'section_key',
        'title',
        'description',
        'guidance',
        'settings',
        'visibility',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'settings' => 'array',
        'visibility' => 'array',
        'sort_order' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(BiAnnualSiteVisitTemplate::class, 'template_id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(BiAnnualSiteVisitTopic::class, 'section_id')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    public function scopeForKey(Builder $query, string $key): Builder
    {
        return $query->where('section_key', $key);
    }
}
