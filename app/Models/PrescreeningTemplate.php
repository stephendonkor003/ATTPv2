<?php

namespace App\Models;

use App\Models\BaseModel;

class PrescreeningTemplate extends BaseModel
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections()
    {
        return $this->hasMany(PrescreeningSection::class, 'prescreening_template_id')
            ->orderBy('sort_order');
    }

    public function criteria()
    {
        return $this->hasMany(PrescreeningCriterion::class, 'prescreening_template_id')
            ->orderBy('sort_order');
    }
}
