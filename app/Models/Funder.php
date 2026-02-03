<?php

namespace App\Models;

use App\Models\BaseModel;

class Funder extends BaseModel
{
    protected $table = 'myb_funders';

    protected $fillable = [
        'name',
        'type',
        'currency',
        'logo',
        'has_portal_access',
        'user_id',
        'contact_person',
        'contact_email',
        'contact_phone',
        'notes',
        'welcome_shown_at',
    ];

    protected $casts = [
        'has_portal_access' => 'boolean',
    ];

    /* ==========================
     * RELATIONSHIPS
     * ========================== */

    public function programFundings()
    {
        return $this->hasMany(ProgramFunding::class, 'funder_id');
    }

    public function portalUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function informationRequests()
    {
        return $this->hasMany(PartnerInformationRequest::class, 'funder_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(PartnerActivityLog::class, 'funder_id');
    }

    /* ==========================
     * HELPER METHODS
     * ========================== */

    public function hasPortalAccess(): bool
    {
        return $this->has_portal_access && $this->user_id !== null;
    }

    public function scopeWithPortalAccess($query)
    {
        return $query->where('has_portal_access', true)->whereNotNull('user_id');
    }

    public function getLogoUrl(): ?string
    {
        if ($this->logo) {
            return asset('storage/' . $this->logo);
        }

        return null;
    }

    public function hasLogo(): bool
    {
        return !empty($this->logo);
    }
}