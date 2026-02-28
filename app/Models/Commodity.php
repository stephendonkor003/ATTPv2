<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Commodity extends BaseModel
{
    use HasFactory;

    protected $table = 'myb_commodities';

    protected $fillable = [
        'name',
        'category',
        'unit_of_measure',
        'description',
        'created_by',
        'updated_by',
    ];

    public function trends(): HasMany
    {
        return $this->hasMany(MemberStateCommodityTrend::class, 'commodity_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
