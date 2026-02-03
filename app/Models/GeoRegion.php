<?php

namespace App\Models;

use App\Models\BaseModel;

class GeoRegion extends BaseModel
{
    protected $fillable = ['continent', 'sub_region', 'country', 'region_group'];
}