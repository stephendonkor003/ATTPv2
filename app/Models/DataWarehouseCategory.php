<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataWarehouseCategory extends BaseModel
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'status',
        'created_by',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(DataWarehouseRecord::class, 'category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
