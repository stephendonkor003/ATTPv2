<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Support\Str;

class Funder extends BaseModel
{
    public const TYPES = [
        'government',
        'donor',
        'internal',
        'private',
    ];

    public const PARTNERSHIP_STATUSES = [
        'prospect',
        'active',
        'at_risk',
        'dormant',
        'closed',
    ];

    public const COMMUNICATION_STATUSES = [
        'pending',
        'attended',
        'follow_up_needed',
    ];

    protected $table = 'myb_funders';

    protected $fillable = [
        'name',
        'type',
        'currency',
        'logo',
        'has_portal_access',
        'user_id',
        'relationship_manager_id',
        'partnership_status',
        'partnership_started_at',
        'next_follow_up_at',
        'contact_person',
        'contact_email',
        'contact_phone',
        'notes',
        'last_contact_at',
        'last_contact_subject',
        'last_contact_status',
        'last_contact_user_id',
        'last_contact_notes',
        'welcome_shown_at',
    ];

    protected $casts = [
        'has_portal_access' => 'boolean',
        'partnership_started_at' => 'date',
        'next_follow_up_at' => 'date',
        'last_contact_at' => 'datetime',
        'welcome_shown_at' => 'datetime',
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

    public function relationshipManager()
    {
        return $this->belongsTo(User::class, 'relationship_manager_id');
    }

    public function lastContactOwner()
    {
        return $this->belongsTo(User::class, 'last_contact_user_id');
    }

    public function informationRequests()
    {
        return $this->hasMany(PartnerInformationRequest::class, 'funder_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(PartnerActivityLog::class, 'funder_id');
    }

    public function consortia()
    {
        return $this->hasMany(Consortium::class, 'funder_id');
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

    public function getTypeBadgeClass(): string
    {
        return match ($this->type) {
            'government' => 'bg-primary',
            'donor' => 'bg-success',
            'private' => 'bg-warning text-dark',
            'internal' => 'bg-info text-dark',
            default => 'bg-secondary',
        };
    }

    public function getPartnershipStatusBadgeClass(): string
    {
        return match ($this->partnership_status) {
            'active' => 'bg-success',
            'prospect' => 'bg-secondary',
            'at_risk' => 'bg-warning text-dark',
            'dormant' => 'bg-info text-dark',
            'closed' => 'bg-dark',
            default => 'bg-light text-dark',
        };
    }

    public function getCommunicationStatusBadgeClass(?string $status = null): string
    {
        return match ($status ?? $this->last_contact_status) {
            'attended' => 'bg-success',
            'follow_up_needed' => 'bg-warning text-dark',
            'pending' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    public function formatStatusLabel(?string $value): string
    {
        return $value ? Str::headline(str_replace('_', ' ', $value)) : 'Not set';
    }
}
