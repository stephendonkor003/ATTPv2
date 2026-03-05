<?php

namespace App\Models;

class WorldIndicatorSetting extends BaseModel
{
    protected $table = 'world_indicator_settings';

    protected $fillable = [
        'page_title',
        'page_intro',
        'is_public_enabled',
        'enabled_regions',
        'default_region',
        'imf_source_enabled',
        'world_bank_source_enabled',
        'imf_api_base_url',
        'world_bank_api_base_url',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_public_enabled' => 'boolean',
        'enabled_regions' => 'array',
        'imf_source_enabled' => 'boolean',
        'world_bank_source_enabled' => 'boolean',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
