<?php

namespace App\Models;

use App\Support\DiscussionAccountEmailPolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    public const THINK_TANK_ACCESS_ADMIN = 'think_tank_admin';

    public const THINK_TANK_ACCESS_PROCUREMENT = 'procurement_officer';

    public const THINK_TANK_ACCESS_ME = 'me_officer';

    public const THINK_TANK_ACCESS_FINANCE = 'finance_officer';

    public const THINK_TANK_ACCESS_LEVELS = [
        self::THINK_TANK_ACCESS_ADMIN => 'Think Tank Admin',
        self::THINK_TANK_ACCESS_PROCUREMENT => 'Procurement Officer',
        self::THINK_TANK_ACCESS_ME => 'M&E Officer',
        self::THINK_TANK_ACCESS_FINANCE => 'Finance Officer',
    ];

    private const THINK_TANK_AREA_ACCESS = [
        'dashboard' => [
            self::THINK_TANK_ACCESS_ADMIN,
            self::THINK_TANK_ACCESS_PROCUREMENT,
            self::THINK_TANK_ACCESS_ME,
            self::THINK_TANK_ACCESS_FINANCE,
        ],
        'me' => [self::THINK_TANK_ACCESS_ADMIN, self::THINK_TANK_ACCESS_ME],
        'reports' => [self::THINK_TANK_ACCESS_ADMIN, self::THINK_TANK_ACCESS_ME],
        'finance' => [self::THINK_TANK_ACCESS_ADMIN, self::THINK_TANK_ACCESS_FINANCE],
        'procurement_plans' => [self::THINK_TANK_ACCESS_ADMIN, self::THINK_TANK_ACCESS_PROCUREMENT],
        'team' => [self::THINK_TANK_ACCESS_ADMIN],
        // Retained only for system/super-administrator oversight of retired
        // screens. No think tank access level is allowed into legacy areas.
        'legacy_admin' => [],
    ];

    private const THINK_TANK_ACCESS_PERMISSIONS = [
        self::THINK_TANK_ACCESS_ADMIN => [
            'think_tank.portal.access',
            'think_tank.dashboard.download',
            'think_tank.reports.submit',
            'think_tank.me.view',
            'think_tank.me.submit',
            'think_tank.finance.view',
            'think_tank.finance.manage',
            'think_tank.procurement_plans.view',
            'think_tank.procurement_plans.manage',
            'think_tank.team.manage',
        ],
        self::THINK_TANK_ACCESS_PROCUREMENT => [
            'think_tank.portal.access',
            'think_tank.dashboard.download',
            'think_tank.procurement_plans.view',
            'think_tank.procurement_plans.manage',
        ],
        self::THINK_TANK_ACCESS_ME => [
            'think_tank.portal.access',
            'think_tank.dashboard.download',
            'think_tank.reports.submit',
            'think_tank.me.view',
            'think_tank.me.submit',
        ],
        self::THINK_TANK_ACCESS_FINANCE => [
            'think_tank.portal.access',
            'think_tank.dashboard.download',
            'think_tank.finance.view',
            'think_tank.finance.manage',
        ],
    ];

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
        'think_tank_member_id',
        'think_tank_access_level',
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

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (
                filled($user->think_tank_access_level)
                && ! array_key_exists((string) $user->think_tank_access_level, self::THINK_TANK_ACCESS_LEVELS)
            ) {
                throw ValidationException::withMessages([
                    'think_tank_access_level' => ['The selected think tank access level is invalid.'],
                ]);
            }

            if (! $user->isDirty('email') || blank($user->email)) {
                return;
            }

            if (DiscussionAccountEmailPolicy::participantEmailExists($user->email)) {
                throw ValidationException::withMessages([
                    'email' => [DiscussionAccountEmailPolicy::UNAVAILABLE_MESSAGE],
                ]);
            }
        });
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

    public function partnerFunders(): BelongsToMany
    {
        return $this->belongsToMany(
            Funder::class,
            'funder_user'
        )
            ->withPivot(['is_primary', 'invited_by', 'invited_at'])
            ->withTimestamps();
    }

    public function partnerFunder(): ?Funder
    {
        $funder = $this->relationLoaded('partnerFunders')
            ? $this->partnerFunders->first()
            : $this->partnerFunders()->where('has_portal_access', true)->first();

        return $funder ?: $this->funderPortal;
    }

    public function thinkTankMembership(): HasOne
    {
        return $this->hasOne(ConsortiumThinkTank::class, 'portal_user_id');
    }

    /**
     * The explicit membership used by every think tank staff account.
     *
     * The older portal_user_id link is retained as a compatibility pointer to
     * the original primary account; this relation supports multiple users in
     * one think tank without creating a second identity table.
     */
    public function assignedThinkTankMembership(): BelongsTo
    {
        return $this->belongsTo(ConsortiumThinkTank::class, 'think_tank_member_id');
    }

    public function resolvedThinkTankMembership(): ?ConsortiumThinkTank
    {
        $assigned = $this->relationLoaded('assignedThinkTankMembership')
            ? $this->assignedThinkTankMembership
            : $this->assignedThinkTankMembership()->first();

        if ($assigned) {
            return $assigned;
        }

        return $this->relationLoaded('thinkTankMembership')
            ? $this->thinkTankMembership
            : $this->thinkTankMembership()->first();
    }

    public function vendorThinkTankMembership()
    {
        return $this->hasOne(ConsortiumThinkTank::class, 'vendor_user_id');
    }

    public function responsibleDataEntryForms(): HasMany
    {
        return $this->hasMany(MeDataEntryForm::class, 'responsible_user_id');
    }

    public function createdDataEntryForms(): HasMany
    {
        return $this->hasMany(MeDataEntryForm::class, 'created_by');
    }

    public function createdReportingPeriods(): HasMany
    {
        return $this->hasMany(MeReportingPeriod::class, 'created_by');
    }

    public function assignedDataCollections(): HasMany
    {
        return $this->hasMany(MeDataCollectionAssignment::class, 'assigned_by');
    }

    public function submittedDataSubmissions(): HasMany
    {
        return $this->hasMany(MeDataSubmission::class, 'submitted_by');
    }

    public function reviewedDataSubmissions(): HasMany
    {
        return $this->hasMany(MeDataSubmission::class, 'reviewed_by');
    }

    public function vendorSubActivityAssignments(): HasMany
    {
        return $this->hasMany(VendorSubActivityAssignment::class, 'vendor_id');
    }

    public function vendorSubActivities(): BelongsToMany
    {
        return $this->belongsToMany(
            SubActivity::class,
            'vendor_sub_activity_assignments',
            'vendor_id',
            'sub_activity_id'
        )
            ->withPivot(['program_id', 'project_id', 'activity_id'])
            ->withTimestamps();
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

        // The legacy Think Tank User role historically held every portal
        // permission. Keep access-level boundaries authoritative so a broad
        // role or direct grant cannot bypass area restrictions.
        if ($this->isThinkTankUser()) {
            return str_starts_with($permission, 'think_tank.')
                && in_array($permission, $this->thinkTankPermissionNames(), true);
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

    public function resolvedThinkTankAccessLevel(): ?string
    {
        $accessLevel = trim((string) $this->think_tank_access_level);

        if (array_key_exists($accessLevel, self::THINK_TANK_ACCESS_LEVELS)) {
            return $accessLevel;
        }

        // Existing primary portal users remain administrators until the
        // migration/backfill is run. Unlinked accounts never receive a role.
        if ($this->isThinkTankUser() && $this->resolvedThinkTankMembership()) {
            $legacyPrimary = $this->relationLoaded('thinkTankMembership')
                ? $this->thinkTankMembership
                : $this->thinkTankMembership()->first();

            if ($legacyPrimary) {
                return self::THINK_TANK_ACCESS_ADMIN;
            }
        }

        return null;
    }

    public function thinkTankAccessLabel(): string
    {
        $level = $this->resolvedThinkTankAccessLevel();

        return self::THINK_TANK_ACCESS_LEVELS[$level] ?? 'Unassigned';
    }

    public function canAccessThinkTankArea(string $area): bool
    {
        if ($this->isSuperAdmin() || $this->isAdmin()) {
            return true;
        }

        if (! $this->isThinkTankUser()) {
            return false;
        }

        $area = match ($area) {
            'procurement', 'procurement-plans' => 'procurement_plans',
            'report', 'report_uploads', 'report-uploads' => 'reports',
            'm&e', 'm_and_e' => 'me',
            'admin', 'legacy' => 'legacy_admin',
            default => $area,
        };

        return in_array(
            $this->resolvedThinkTankAccessLevel(),
            self::THINK_TANK_AREA_ACCESS[$area] ?? [],
            true
        );
    }

    public function thinkTankPermissionNames(): array
    {
        $accessLevel = $this->resolvedThinkTankAccessLevel();

        return self::THINK_TANK_ACCESS_PERMISSIONS[$accessLevel] ?? [];
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
        $this->notify(new ResetPassword($token));
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
