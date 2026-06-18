<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class WebsiteVisit extends BaseModel
{
    protected $fillable = [
        'visitor_uuid',
        'session_id',
        'ip_address',
        'ip_hash',
        'user_agent',
        'referrer',
        'landing_url',
        'current_url',
        'current_path',
        'country_name',
        'country_iso2',
        'continent',
        'latitude',
        'longitude',
        'page_views',
        'duration_seconds',
        'first_seen_at',
        'last_seen_at',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'page_views' => 'integer',
        'duration_seconds' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function activities(): HasMany
    {
        return $this->hasMany(WebsiteVisitActivity::class);
    }
}
