<?php

namespace App\Models;

use App\Models\BaseModel;

class Permission extends BaseModel
{
    protected $fillable = [
        'name',
        'module',
        'description',
    ];

    /* ===============================
     | RELATIONSHIPS
     =============================== */

    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_permission'
        );
    }
}