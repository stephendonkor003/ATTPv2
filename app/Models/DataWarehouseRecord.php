<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataWarehouseRecord extends BaseModel
{
    protected $fillable = [
        'category_id',
        'title',
        'source_name',
        'reference_period',
        'data_owner',
        'tags',
        'description',
        'status',
        'created_by',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(DataWarehouseCategory::class, 'category_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DataWarehouseFile::class, 'record_id')->latest();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
