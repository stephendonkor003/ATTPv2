<?php

namespace App\Models;

use App\Models\BaseModel;

class VendorCategory extends BaseModel
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
