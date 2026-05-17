<?php

namespace App\Models;

use App\Notifications\QueuedResetPasswordNotification;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Mass assignable attributes
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'user_type',
        'vendor_category',
        'payment_method_preference',
        'payment_bank_name',
        'payment_account_name',
        'payment_account_number',
        'payment_swift_code',
        'payment_iban',
        'payment_mobile_provider',
        'payment_mobile_number',
        'payment_tax_id',
        'payment_address',
        'is_disabled',
        'disabled_at',
        'disabled_until',
        'disabled_reason',
        'is_blacklisted',
        'blacklisted_at',
        'blacklisted_reason',
        'must_change_password',
        'password_changed_at',
        'otp_verified_at',
        'role_id',
        'governance_node_id',
        'member_state_id',
    ];

    /**
     * Hidden attributes
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casting
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'otp_verified_at' => 'datetime',
            'is_disabled' => 'boolean',
            'disabled_at' => 'datetime',
            'disabled_until' => 'datetime',
            'is_blacklisted' => 'boolean',
            'blacklisted_at' => 'datetime',
        ];
    }

    /* =====================================================
     | EXISTING RELATIONSHIPS (UNCHANGED)
     ===================================================== */

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    public function committeeMemberships()
    {
        return $this->hasMany(CommitteeMember::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class, 'evaluator_id');
    }

    public function assignedApplicants()
    {
        return $this->belongsToMany(Applicant::class, 'applicant_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function evaluatorTeamsLed()
    {
        return $this->hasMany(EvaluatorTeam::class, 'leader_id');
    }

    public function teamMemberships()
    {
        return $this->hasMany(TeamMember::class, 'user_id');
    }

    /* =====================================================
     | ROLE & PERMISSIONS
     ===================================================== */

    /**
     * User belongs to a role
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function governanceNode()
    {
        return $this->belongsTo(GovernanceNode::class, 'governance_node_id');
    }

    public function memberState()
    {
        return $this->belongsTo(AuMemberState::class, 'member_state_id');
    }

    /**
     * Funding partner portal relationship
     */
    public function funderPortal()
    {
        return $this->hasOne(Funder::class, 'user_id');
    }

    public function thinkTankMembership()
    {
        return $this->hasOne(ConsortiumThinkTank::class, 'portal_user_id');
    }

    /**
     * Direct permissions (override layer)
     */
    public function permissions()
    {
        return $this->belongsToMany(
            Permission::class,
            'user_permission'
        );
    }

    /**
     * FINAL permission check (ROLE + DIRECT)
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin() || $this->isAdmin()) {
            return true;
        }

        // 1️⃣ Direct user permission override
        if ($this->permissions->contains('name', $permission)) {
            return true;
        }

        // 2️⃣ Role-based permission
        return $this->role
            && $this->role->permissions->contains('name', $permission);
    }

    /* =====================================================
     | CONVENIENCE HELPERS
     ===================================================== */

    public function isAdmin(): bool
    {
        return $this->role && $this->role->name === 'System Admin';
    }

    public function hasRole(string $roleName): bool
    {
        return $this->role && $this->role->name === $roleName;
    }

    public function isFundingPartner(): bool
    {
        return $this->user_type === 'funding_partner';
    }

    public function isThinkTankUser(): bool
    {
        return $this->user_type === 'think_tank';
    }

    public function hasActiveLoginBlock(): bool
    {
        if (! $this->is_disabled) {
            return false;
        }

        // No end date means permanent block.
        if (! $this->disabled_until) {
            return true;
        }

        return $this->disabled_until->isFuture();
    }

    /* =====================================================
     | SECURITY & PASSWORD MANAGEMENT
     ===================================================== */

    /**
     * Relationship to login OTPs
     */
    public function loginOtps()
    {
        return $this->hasMany(UserLoginOtp::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPasswordNotification($token));
    }

    /**
     * Check if user must change password (first login or forced)
     */
    public function mustChangePassword(): bool
    {
        // Admin users are exempt
        if ($this->isSuperAdmin()) {
            return false;
        }

        return $this->must_change_password === true;
    }

    /**
     * Check if password has expired (older than 2 months)
     */
    public function isPasswordExpired(): bool
    {
        // Admin users are exempt
        if ($this->isSuperAdmin()) {
            return false;
        }

        // If never changed, not expired (but must_change_password will catch it)
        if (! $this->password_changed_at) {
            return false;
        }

        // Password expires after 60 days (2 months)
        return $this->password_changed_at->addDays(60)->isPast();
    }

    /**
     * Check if user requires OTP verification
     */
    public function requiresOtpVerification(): bool
    {
        if (app()->environment(['local', 'testing']) && ! (bool) config('security.require_login_otp_locally', false)) {
            return false;
        }

        // Admin users are exempt from OTP
        if ($this->isSuperAdmin()) {
            return false;
        }

        // Funding partners are exempt (they have their own flow)
        if ($this->isFundingPartner()) {
            return false;
        }

        return true;
    }

    /**
     * Check if OTP was verified in current session
     */
    public function hasVerifiedOtpRecently(): bool
    {
        // Check if OTP was verified within last 24 hours
        if (! $this->otp_verified_at) {
            return false;
        }

        return $this->otp_verified_at->isAfter(now()->subHours(24));
    }

    /**
     * Mark password as changed
     */
    public function markPasswordAsChanged(): void
    {
        $this->update([
            'password_changed_at' => now(),
            'must_change_password' => false,
        ]);
    }

    /**
     * Mark OTP as verified
     */
    public function markOtpAsVerified(): void
    {
        $this->update([
            'otp_verified_at' => now(),
        ]);
    }

    /**
     * Get days until password expires
     */
    public function daysUntilPasswordExpires(): ?int
    {
        if (! $this->password_changed_at) {
            return null;
        }

        $expiryDate = $this->password_changed_at->addDays(60);

        if ($expiryDate->isPast()) {
            return 0;
        }

        return now()->diffInDays($expiryDate);
    }

    /**
     * Check if user is Super Admin or Admin user type
     * These users bypass security checks (password expiration, OTP)
     */
    public function isSuperAdmin(): bool
    {
        // Check user_type first (admin users bypass security)
        if ($this->user_type === 'admin') {
            return true;
        }

        // Check role-based Super Admin
        return $this->role && $this->role->name === 'Super Admin';
    }
}
