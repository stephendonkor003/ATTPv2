<?php

namespace App\Models;

use App\Models\BaseModel;

class Category extends BaseModel
{
    protected $fillable = [
        'name',
        'description',
    ];
}
