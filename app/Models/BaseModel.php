<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class BaseModel extends Model
{
    use HasUuids;

    /**
     * Primary keys are UUIDs and not auto-incrementing.
     */
    public $incrementing = false;

    protected $keyType = 'string';
}
