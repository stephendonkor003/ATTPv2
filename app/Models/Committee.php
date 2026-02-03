<?php

namespace App\Models;

use App\Models\BaseModel;

class Committee extends BaseModel
{
    protected $fillable = [
        'name',
        'project_id',
        'chairperson_id',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function chairperson()
    {
        return $this->belongsTo(User::class, 'chairperson_id');
    }

    public function members()
    {
        return $this->hasMany(CommitteeMember::class, 'committee_id');
    }
}
