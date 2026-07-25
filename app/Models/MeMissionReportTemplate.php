<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class MeMissionReportTemplate extends BaseModel
{
    protected $table = 'me_mission_report_templates';

    protected $fillable = [
        'code',
        'name',
        'description',
        'sections',
        'version',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'version' => 'integer',
        'is_active' => 'boolean',
    ];

    public function reports(): HasMany
    {
        return $this->hasMany(MeMissionReport::class, 'template_id');
    }
}
