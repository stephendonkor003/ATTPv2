<?php

namespace App\Services\ThinkTank;

use App\Data\ThinkTank\CreateThinkTankUserData;
use App\Data\ThinkTank\UpdateThinkTankUserData;
use App\Exceptions\ThinkTankApiException;
use App\Models\ConsortiumThinkTank;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ThinkTankUserManagementService
{
    public function __construct(
        private readonly ThinkTankApiAuditService $audit,
        private readonly ThinkTankInvitationService $invitations,
        private readonly ThinkTankSessionService $sessions,
    ) {}

    /** @param array<string, mixed> $filters */
    public function paginate(ConsortiumThinkTank $tenant, array $filters): LengthAwarePaginator
    {
        $perPage = max(1, min((int) ($filters['per_page'] ?? 25), 100));
        $query = $this->tenantQuery($tenant);

        if (filled($filters['q'] ?? null)) {
            $term = mb_strtolower(trim((string) $filters['q']));
            $query->where(function (Builder $query) use ($term): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%'.$term.'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.$term.'%']);
            });
        }

        if (filled($filters['access_level'] ?? null)) {
            $query->where('think_tank_access_level', $filters['access_level']);
        }

        if (filled($filters['account_status'] ?? null)) {
            match ($filters['account_status']) {
                'active' => $query->where('is_blacklisted', false)
                    ->where(function (Builder $query): void {
                        $query->where('is_disabled', false)
                            ->orWhere(function (Builder $query): void {
                                $query->where('is_disabled', true)->where('disabled_until', '<=', now());
                            });
                    }),
                'disabled' => $query->where('is_blacklisted', false)
                    ->where('is_disabled', true)
                    ->where(function (Builder $query): void {
                        $query->whereNull('disabled_until')->orWhere('disabled_until', '>', now());
                    }),
                'blacklisted' => $query->where('is_blacklisted', true),
                default => null,
            };
        }

        return $query->orderBy('name')->orderBy('id')->paginate($perPage);
    }

    public function findForTenant(ConsortiumThinkTank $tenant, string $userId): User
    {
        return $this->tenantQuery($tenant)->whereKey($userId)->firstOrFail();
    }

    /** @return array{user: User, invitation_sent: bool} */
    public function create(Request $request, User $actor, ConsortiumThinkTank $tenant, CreateThinkTankUserData $data): array
    {
        return $this->createAuthorized($request, $actor, $tenant, $data, false);
    }

    /** @return array{user: User, invitation_sent: bool} */
    public function createForSystemOversight(
        Request $request,
        User $actor,
        ConsortiumThinkTank $tenant,
        CreateThinkTankUserData $data,
    ): array {
        return $this->createAuthorized($request, $actor, $tenant, $data, true);
    }

    /** @return array{user: User, invitation_sent: bool} */
    private function createAuthorized(
        Request $request,
        User $actor,
        ConsortiumThinkTank $tenant,
        CreateThinkTankUserData $data,
        bool $systemOversight,
    ): array {
        $user = $this->withEmailLock($data->email, function () use ($request, $actor, $tenant, $data, $systemOversight): User {
            return DB::transaction(function () use ($request, $actor, $tenant, $data, $systemOversight): User {
                [$lockedTenant, $lockedActor] = $this->lockMutationAuthority(
                    $tenant,
                    $actor,
                    $systemOversight,
                );
                $this->assertEmailAvailable($data->email);

                $role = Role::query()->firstOrCreate(
                    ['name' => 'Think Tank User'],
                    ['description' => 'Think tank staff account; portal access is controlled by its assigned access level.']
                );

                $user = User::query()->create([
                    'name' => $data->name,
                    'email' => $data->email,
                    'password' => Str::password(64),
                    'user_type' => 'think_tank',
                    'role_id' => $role->getKey(),
                    'think_tank_member_id' => $lockedTenant->getKey(),
                    'think_tank_access_level' => $data->accessLevel,
                    'must_change_password' => true,
                    'password_changed_at' => null,
                    'otp_verified_at' => null,
                    'is_disabled' => false,
                    'is_blacklisted' => false,
                    'email_verified_at' => null,
                ]);

                $this->audit->required($request, 'think_tank.user.created', 'Think tank portal user created.', [
                    'tenant_id' => (string) $lockedTenant->getKey(),
                    'target_user_id' => (string) $user->getKey(),
                    'access_level' => $data->accessLevel,
                ], $lockedActor);

                return $user;
            });
        });

        $sent = $this->invitations->send($user, true);
        $this->audit->bestEffort($request, 'think_tank.user.invitation_sent', 'Think tank portal invitation processed.', [
            'tenant_id' => (string) $tenant->getKey(),
            'target_user_id' => (string) $user->getKey(),
            'delivered' => $sent,
        ], $actor);

        return ['user' => $user, 'invitation_sent' => $sent];
    }

    /** @return array{user: User, invitation_sent: ?bool} */
    public function update(
        Request $request,
        User $actor,
        ConsortiumThinkTank $tenant,
        User $target,
        UpdateThinkTankUserData $data,
    ): array {
        return $this->updateAuthorized($request, $actor, $tenant, $target, $data, false);
    }

    /** @return array{user: User, invitation_sent: ?bool} */
    public function updateForSystemOversight(
        Request $request,
        User $actor,
        ConsortiumThinkTank $tenant,
        User $target,
        UpdateThinkTankUserData $data,
    ): array {
        return $this->updateAuthorized($request, $actor, $tenant, $target, $data, true);
    }

    /** @return array{user: User, invitation_sent: ?bool} */
    private function updateAuthorized(
        Request $request,
        User $actor,
        ConsortiumThinkTank $tenant,
        User $target,
        UpdateThinkTankUserData $data,
        bool $systemOversight,
    ): array {
        $newEmail = $data->has('email') ? (string) $data->get('email') : (string) $target->email;

        $emailChanged = false;
        $securityChanged = false;

        $target = $this->withEmailLock($newEmail, function () use (
            $request,
            $actor,
            $tenant,
            $target,
            $data,
            $systemOversight,
            &$emailChanged,
            &$securityChanged,
        ): User {
            return DB::transaction(function () use (
                $request,
                $actor,
                $tenant,
                $target,
                $data,
                $systemOversight,
                &$emailChanged,
                &$securityChanged,
            ): User {
                [$lockedTenant, $lockedActor] = $this->lockMutationAuthority(
                    $tenant,
                    $actor,
                    $systemOversight,
                );
                $members = $this->tenantQuery($lockedTenant)->lockForUpdate()->get();
                $lockedTarget = $members->firstWhere('id', $target->getKey());

                if (! $lockedTarget) {
                    abort(404);
                }

                if ($data->has('email')
                    && (string) $lockedActor->getKey() === (string) $lockedTarget->getKey()
                    && (string) $data->get('email') !== mb_strtolower((string) $lockedTarget->email)) {
                    throw ValidationException::withMessages([
                        'email' => ['You cannot change your own administrator email here. Use the verified profile email-change process.'],
                    ]);
                }

                if ($data->has('email')
                    && (string) $data->get('email') !== mb_strtolower((string) $lockedTarget->email)) {
                    $this->assertEmailAvailable((string) $data->get('email'), (string) $lockedTarget->getKey());
                }

                $currentAdmin = $lockedTarget->think_tank_access_level === User::THINK_TANK_ACCESS_ADMIN;
                $currentActive = ! $lockedTarget->is_blacklisted && ! $lockedTarget->hasActiveLoginBlock();
                $nextAccess = (string) $data->get('access_level', $lockedTarget->think_tank_access_level);
                $nextDisabled = (bool) $data->get('is_disabled', $lockedTarget->hasActiveLoginBlock());
                $removesActiveAdmin = $currentAdmin && $currentActive
                    && ($nextAccess !== User::THINK_TANK_ACCESS_ADMIN || $nextDisabled);

                if ((string) $actor->getKey() === (string) $lockedTarget->getKey()
                    && ($nextAccess !== User::THINK_TANK_ACCESS_ADMIN || $nextDisabled)) {
                    throw ValidationException::withMessages([
                        'access_level' => ['You cannot demote or disable your own administrator account.'],
                    ]);
                }

                if ($removesActiveAdmin) {
                    $anotherActiveAdmin = $members->contains(function (User $member) use ($lockedTarget): bool {
                        return (string) $member->getKey() !== (string) $lockedTarget->getKey()
                            && $member->think_tank_access_level === User::THINK_TANK_ACCESS_ADMIN
                            && ! $member->is_blacklisted
                            && ! $member->hasActiveLoginBlock();
                    });

                    if (! $anotherActiveAdmin) {
                        throw ValidationException::withMessages([
                            'access_level' => ['At least one active think tank administrator is required.'],
                        ]);
                    }
                }

                $updates = [];

                if ($data->has('name')) {
                    $updates['name'] = $data->get('name');
                }

                if ($data->has('access_level')) {
                    $updates['think_tank_access_level'] = $nextAccess;
                    $securityChanged = $nextAccess !== $lockedTarget->think_tank_access_level;
                }

                if ($data->has('is_disabled')) {
                    $updates['is_disabled'] = $nextDisabled;
                    $updates['disabled_at'] = $nextDisabled ? ($lockedTarget->disabled_at ?: now()) : null;
                    $updates['disabled_until'] = null;
                    $updates['disabled_reason'] = $nextDisabled ? 'Disabled by a think tank administrator.' : null;
                    $securityChanged = $securityChanged || $nextDisabled !== $lockedTarget->hasActiveLoginBlock();
                }

                if ($data->has('email') && (string) $data->get('email') !== mb_strtolower((string) $lockedTarget->email)) {
                    $updates['email'] = $data->get('email');
                    $updates['email_verified_at'] = null;
                    $updates['password'] = Str::password(64);
                    $updates['must_change_password'] = true;
                    $updates['password_changed_at'] = null;
                    $emailChanged = true;
                    $securityChanged = true;
                }

                $lockedTarget->forceFill($updates)->save();

                if ($securityChanged) {
                    $this->sessions->invalidateMfa($lockedTarget);
                    $this->sessions->revokeOtherSessions($lockedTarget, $request);
                }

                $this->audit->required($request, 'think_tank.user.updated', 'Think tank portal user updated.', [
                    'tenant_id' => (string) $lockedTenant->getKey(),
                    'target_user_id' => (string) $lockedTarget->getKey(),
                    'changed_fields' => array_keys($updates),
                    'access_level' => $lockedTarget->think_tank_access_level,
                    'is_disabled' => $lockedTarget->hasActiveLoginBlock(),
                ], $lockedActor);

                return $lockedTarget->fresh();
            });
        });

        $invitationSent = null;

        if ($emailChanged) {
            $invitationSent = $this->invitations->send($target, true);
        }

        return ['user' => $target, 'invitation_sent' => $invitationSent];
    }

    public function resendInvitation(Request $request, User $actor, ConsortiumThinkTank $tenant, User $target): bool
    {
        $sent = DB::transaction(function () use ($actor, $tenant, $target): bool {
            [$lockedTenant] = $this->lockMutationAuthority($tenant, $actor);
            $lockedTarget = $this->tenantQuery($lockedTenant)
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return $this->invitations->send($lockedTarget, true);
        });

        $this->audit->bestEffort($request, 'think_tank.user.invitation_resent', 'Think tank portal invitation resent.', [
            'tenant_id' => (string) $tenant->getKey(),
            'target_user_id' => (string) $target->getKey(),
            'delivered' => $sent,
        ], $actor);

        return $sent;
    }

    public function resetPasswordForSystemOversight(
        Request $request,
        User $actor,
        ConsortiumThinkTank $tenant,
        User $target,
    ): bool {
        $lockedTarget = DB::transaction(function () use ($request, $actor, $tenant, $target): User {
            [$lockedTenant, $lockedActor] = $this->lockMutationAuthority($tenant, $actor, true);
            $lockedTarget = $this->tenantQuery($lockedTenant)
                ->whereKey($target->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedTarget->forceFill([
                'password' => Str::password(64),
                'must_change_password' => true,
                'password_changed_at' => null,
                'otp_verified_at' => null,
            ])->save();
            $this->sessions->invalidateMfa($lockedTarget);
            $this->sessions->revokeAllSessions($lockedTarget);
            $this->audit->required(
                $request,
                'think_tank.user.password_reset_initiated',
                'Think tank portal password invalidated by system oversight.',
                [
                    'tenant_id' => (string) $lockedTenant->getKey(),
                    'target_user_id' => (string) $lockedTarget->getKey(),
                ],
                $lockedActor,
            );

            return $lockedTarget->fresh();
        });

        return $this->invitations->send($lockedTarget, false);
    }

    /** @return array{user: User, created: bool} */
    public function resolveOrCreateUnassignedAdministrator(string $name, string $email): array
    {
        $normalizedEmail = mb_strtolower(trim($email));

        return $this->withEmailLock($normalizedEmail, function () use ($name, $normalizedEmail): array {
            return DB::transaction(function () use ($name, $normalizedEmail): array {
                $matches = User::query()
                    ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                    ->lockForUpdate()
                    ->get();

                if ($matches->count() > 1) {
                    throw ValidationException::withMessages([
                        'email' => ['This email cannot be used because multiple matching accounts require administrator review.'],
                    ]);
                }

                /** @var User|null $user */
                $user = $matches->first();

                if ($user) {
                    $this->assertCanBeAssignedToMembership($user, null, 'email');

                    return ['user' => $user, 'created' => false];
                }

                $role = Role::query()->firstOrCreate(
                    ['name' => 'Think Tank User'],
                    ['description' => 'Think tank staff account; portal access is controlled by its assigned access level.']
                );

                $user = User::query()->create([
                    'name' => trim($name),
                    'email' => $normalizedEmail,
                    'password' => Str::password(64),
                    'user_type' => 'think_tank',
                    'role_id' => $role->getKey(),
                    'think_tank_member_id' => null,
                    'think_tank_access_level' => null,
                    'must_change_password' => true,
                    'password_changed_at' => null,
                    'otp_verified_at' => null,
                    'is_disabled' => false,
                    'is_blacklisted' => false,
                    'email_verified_at' => null,
                ]);

                return ['user' => $user, 'created' => true];
            });
        });
    }

    public function assertCanBeAssignedToMembership(
        User $user,
        ?ConsortiumThinkTank $expectedMembership = null,
        string $field = 'portal_user_id',
    ): void {
        $expectedId = $expectedMembership?->getKey();
        $assignedId = $user->think_tank_member_id;
        $claimedElsewhere = ConsortiumThinkTank::query()
            ->where('portal_user_id', $user->getKey())
            ->when($expectedId, fn (Builder $query) => $query->whereKeyNot($expectedId))
            ->exists();

        if ($user->user_type !== 'think_tank'
            || (filled($assignedId) && (string) $assignedId !== (string) $expectedId)
            || $claimedElsewhere) {
            throw ValidationException::withMessages([
                $field => ['This account is already assigned to a different think tank or cannot be used for this portal.'],
            ]);
        }
    }

    public function assertNotManagedPortalIdentity(User $user, string $field = 'email'): void
    {
        $claimedByMembership = ConsortiumThinkTank::query()
            ->where('portal_user_id', $user->getKey())
            ->exists();

        if ($user->user_type === 'think_tank'
            || filled($user->think_tank_member_id)
            || $claimedByMembership) {
            throw ValidationException::withMessages([
                $field => ['This identity belongs to the Think Tank portal and can only be changed through the dedicated portal-user workflow.'],
            ]);
        }
    }

    public function assignAdministrator(User $user, ConsortiumThinkTank $membership): User
    {
        return DB::transaction(function () use ($user, $membership): User {
            $lockedMembership = ConsortiumThinkTank::query()
                ->whereKey($membership->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            ConsortiumThinkTank::query()
                ->where('portal_user_id', $lockedUser->getKey())
                ->lockForUpdate()
                ->get();

            $this->assertCanBeAssignedToMembership($lockedUser, $lockedMembership);

            $role = Role::query()->firstOrCreate(
                ['name' => 'Think Tank User'],
                ['description' => 'Think tank staff account; portal access is controlled by its assigned access level.']
            );

            $lockedUser->forceFill([
                'role_id' => $lockedUser->role_id ?: $role->getKey(),
                'think_tank_member_id' => $lockedMembership->getKey(),
                'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
            ])->save();

            if ((string) $lockedMembership->portal_user_id !== (string) $lockedUser->getKey()) {
                $lockedMembership->forceFill(['portal_user_id' => $lockedUser->getKey()])->save();
            }

            return $lockedUser->fresh();
        });
    }

    public function emailExists(string $email, ?string $exceptUserId = null): bool
    {
        return User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->when($exceptUserId, fn (Builder $query) => $query->whereKeyNot($exceptUserId))
            ->exists();
    }

    private function assertEmailAvailable(string $email, ?string $exceptUserId = null): void
    {
        if ($this->emailExists($email, $exceptUserId)) {
            throw ValidationException::withMessages([
                'email' => ['This email cannot be used because an account already exists.'],
            ]);
        }
    }

    private function withEmailLock(string $email, callable $callback): mixed
    {
        $store = (string) config('think_tank_portal.email_lock_store', config('cache.default'));

        if (app()->environment('production') && in_array($store, ['array', 'file', 'null'], true)) {
            throw new ThinkTankApiException(
                'CONFIGURATION_ERROR',
                'User management requires a shared production lock store.',
                503,
            );
        }

        $name = 'think-tank-user-email:'.hash('sha256', mb_strtolower(trim($email)));
        $lock = Cache::store($store)->lock(
            $name,
            (int) config('think_tank_portal.email_lock_seconds', 30),
        );

        try {
            return $lock->block(
                (int) config('think_tank_portal.email_lock_wait_seconds', 5),
                $callback,
            );
        } catch (LockTimeoutException) {
            throw new ThinkTankApiException(
                'CONFLICT_RETRY',
                'This account is being changed by another request. Please retry.',
                409,
            );
        }
    }

    /** @return array{0: ConsortiumThinkTank, 1: User} */
    private function lockMutationAuthority(
        ConsortiumThinkTank $tenant,
        User $actor,
        bool $systemOversight = false,
    ): array {
        $lockedTenant = ConsortiumThinkTank::query()
            ->whereKey($tenant->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $lockedActor = User::query()
            ->whereKey($actor->getKey())
            ->lockForUpdate()
            ->firstOrFail();
        $activeActor = ! $lockedActor->is_blacklisted && ! $lockedActor->is_disabled;
        $authorized = $lockedTenant->status === 'active'
            && $activeActor
            && ($systemOversight
                ? ($lockedActor->hasPermission('think_tank.users.manage')
                    || $lockedActor->hasPermission('users.manage'))
                : ($lockedActor->user_type === 'think_tank'
                    && (string) $lockedActor->think_tank_member_id === (string) $lockedTenant->getKey()
                    && $lockedActor->think_tank_access_level === User::THINK_TANK_ACCESS_ADMIN));

        if (! $authorized) {
            throw new ThinkTankApiException(
                'AUTHORIZATION_CHANGED',
                'Your account no longer has permission to manage think tank portal users.',
                403,
            );
        }

        return [$lockedTenant, $lockedActor];
    }

    private function tenantQuery(ConsortiumThinkTank $tenant): Builder
    {
        return User::query()
            ->where('user_type', 'think_tank')
            ->where('think_tank_member_id', $tenant->getKey());
    }
}
