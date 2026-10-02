<?php

namespace App\Http\Controllers\Api\V1\ThinkTank;

use App\Exceptions\ThinkTankApiException;
use App\Models\ConsortiumThinkTank;
use App\Models\ThinkTankVendorCategory;
use App\Models\User;
use App\Services\AccountSetupInvitationService;
use App\Services\ThinkTank\ThinkTankApiAuditService;
use App\Services\ThinkTankVendorDirectoryService;
use App\Support\ThinkTankApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class VendorDirectoryController extends ThinkTankApiController
{
    public function __construct(
        private readonly ThinkTankVendorDirectoryService $directory,
        private readonly AccountSetupInvitationService $invitations,
        private readonly ThinkTankApiAuditService $audit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->validateOnly($request, []);
        $tenant = $this->tenant($request);

        return ThinkTankApiResponse::success($this->directory->payload($tenant));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $this->validateOnly($request, [
            'name' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);
        $tenant = $this->tenant($request);
        $name = Str::squish((string) $data['name']);
        $normalized = mb_strtolower($name);

        $category = DB::transaction(function () use ($request, $tenant, $data, $name, $normalized): ThinkTankVendorCategory {
            ConsortiumThinkTank::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
            if (ThinkTankVendorCategory::query()
                ->where('think_tank_member_id', $tenant->getKey())
                ->where('normalized_name', $normalized)
                ->exists()) {
                throw ValidationException::withMessages([
                    'name' => ['This Think Tank already has a vendor category with that name.'],
                ]);
            }

            $category = ThinkTankVendorCategory::query()->create([
                'think_tank_member_id' => $tenant->getKey(),
                'name' => $name,
                'normalized_name' => $normalized,
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'is_active' => true,
                'created_by' => $request->user()->getKey(),
            ]);
            $this->audit->required($request, 'think_tank.vendor_category.created', 'Think Tank vendor category created.', [
                'tenant_id' => (string) $tenant->getKey(),
                'category_id' => (string) $category->getKey(),
            ]);

            return $category;
        });

        return ThinkTankApiResponse::success([
            'id' => (string) $category->getKey(),
            'name' => (string) $category->name,
            'description' => $category->description ?: null,
            'isActive' => true,
            'vendorCount' => 0,
        ], 201, 'Vendor category created for this Think Tank.');
    }

    public function storeVendor(Request $request): JsonResponse
    {
        $data = $this->validateOnly($request, [
            'name' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'category_ids' => ['sometimes', 'array', 'max:50'],
            'category_ids.*' => ['uuid', 'distinct'],
        ]);
        $tenant = $this->tenant($request);
        $categoryIds = array_values($data['category_ids'] ?? []);
        $this->directory->validateTargets($tenant, $categoryIds, [], false);
        $normalizedEmail = mb_strtolower(trim((string) $data['email']));
        $newAccount = false;
        $invitationSent = null;

        $vendor = $this->withEmailLock($normalizedEmail, function () use (
            $request,
            $tenant,
            $data,
            $categoryIds,
            $normalizedEmail,
            &$newAccount,
            &$invitationSent,
        ): User {
            $vendor = DB::transaction(function () use (
                $request,
                $tenant,
                $data,
                $categoryIds,
                $normalizedEmail,
                &$newAccount,
            ): User {
                ConsortiumThinkTank::query()->whereKey($tenant->getKey())->lockForUpdate()->firstOrFail();
                $identityMatches = User::query()
                    ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
                    ->orderBy('id')
                    ->limit(2)
                    ->lockForUpdate()
                    ->get();
                if ($identityMatches->count() > 1) {
                    throw new ThinkTankApiException(
                        'IDENTITY_RECONCILIATION_REQUIRED',
                        'This email has conflicting account records. Contact the system administrator for identity reconciliation.',
                        409,
                    );
                }
                $vendor = $identityMatches->first();

                if ($vendor && $vendor->user_type !== 'vendor') {
                    throw ValidationException::withMessages([
                        'email' => ['This email belongs to an internal account and cannot be added as a vendor.'],
                    ]);
                }

                if ($vendor?->is_blacklisted) {
                    throw ValidationException::withMessages([
                        'email' => ['This vendor is globally blacklisted and cannot be added to a Think Tank directory.'],
                    ]);
                }

                if ($vendor?->is_disabled) {
                    throw ValidationException::withMessages([
                        'email' => ['This vendor account is globally disabled and cannot be added to a Think Tank directory.'],
                    ]);
                }

                if (! $vendor) {
                    $vendor = User::query()->create([
                        'name' => Str::squish((string) $data['name']),
                        'email' => $normalizedEmail,
                        'password' => $this->invitations->unknownPasswordHash(),
                        'user_type' => 'vendor',
                        'must_change_password' => true,
                        'password_changed_at' => null,
                        'otp_verified_at' => null,
                        'is_disabled' => false,
                        'is_blacklisted' => false,
                        'email_verified_at' => null,
                    ]);
                    $newAccount = true;
                }

                DB::table('attp_think_tank_vendor_user')->upsert([[
                    'think_tank_member_id' => $tenant->getKey(),
                    'vendor_user_id' => $vendor->getKey(),
                    'status' => 'active',
                    'can_manage_setup' => $newAccount,
                    'invited_by' => $request->user()->getKey(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]], ['think_tank_member_id', 'vendor_user_id'], ['status', 'invited_by', 'updated_at']);

                DB::table('attp_think_tank_vendor_category_user')
                    ->where('think_tank_member_id', $tenant->getKey())
                    ->where('vendor_user_id', $vendor->getKey())
                    ->delete();
                if ($categoryIds !== []) {
                    DB::table('attp_think_tank_vendor_category_user')->insert(collect($categoryIds)
                        ->map(fn (string $categoryId): array => [
                            'think_tank_vendor_category_id' => $categoryId,
                            'vendor_user_id' => $vendor->getKey(),
                            'think_tank_member_id' => $tenant->getKey(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])->all());
                }

                $this->audit->required($request, 'think_tank.vendor.saved', 'Vendor added to the Think Tank vendor directory.', [
                    'tenant_id' => (string) $tenant->getKey(),
                    'vendor_user_id' => (string) $vendor->getKey(),
                    'category_ids' => $categoryIds,
                    'new_account' => $newAccount,
                ]);

                return $vendor;
            });

            // Issue the initial setup token only after the membership commits,
            // while still holding the shared identity lock used by resends.
            $invitationSent = $newAccount
                ? $this->invitations->send($vendor, AccountSetupInvitationService::PURPOSE_VENDOR)
                : null;

            return $vendor;
        });

        return ThinkTankApiResponse::success([
            'id' => (string) $vendor->getKey(),
            'name' => (string) ($vendor->name ?: $vendor->email),
            'email' => (string) $vendor->email,
            'status' => 'active',
            'setupPending' => (bool) DB::table('attp_think_tank_vendor_user')
                ->where('think_tank_member_id', $tenant->getKey())
                ->where('vendor_user_id', $vendor->getKey())
                ->value('can_manage_setup')
                && (bool) $vendor->must_change_password
                && $vendor->password_changed_at === null,
            'categoryIds' => $categoryIds,
            'categoryNames' => ThinkTankVendorCategory::query()
                ->where('think_tank_member_id', $tenant->getKey())
                ->whereIn('id', $categoryIds)
                ->orderBy('name')
                ->pluck('name')
                ->all(),
            'newAccount' => $newAccount,
            'credentialsSent' => $invitationSent,
        ], $newAccount ? 201 : 200, $newAccount
            ? ($invitationSent
                ? 'Vendor created and a secure account setup link was sent.'
                : 'Vendor created, but the secure account setup link could not be delivered.')
            : 'The existing vendor was added to this Think Tank without changing other tenant memberships.', [
                'credentials_sent' => $invitationSent,
                'new_account' => $newAccount,
            ]);
    }

    public function updateVendor(Request $request, string $vendor): JsonResponse
    {
        $data = $this->validateOnly($request, [
            'status' => ['sometimes', Rule::in(ThinkTankVendorDirectoryService::MEMBERSHIP_STATUSES)],
            'category_ids' => ['sometimes', 'array', 'max:50'],
            'category_ids.*' => ['uuid', 'distinct'],
        ]);
        if ($data === []) {
            throw ValidationException::withMessages(['request' => ['At least one editable field is required.']]);
        }
        $tenant = $this->tenant($request);
        $target = User::query()
            ->whereKey($vendor)
            ->where('user_type', 'vendor')
            ->whereHas('vendorDirectoryThinkTanks', fn ($query) => $query
                ->where('attp_consortium_think_tanks.id', $tenant->getKey()))
            ->firstOrFail();
        $categoryIds = array_values($data['category_ids'] ?? []);
        if (array_key_exists('category_ids', $data)) {
            $this->directory->validateTargets($tenant, $categoryIds, [], false);
        }

        $lockedEmail = mb_strtolower(trim((string) $target->email));
        $target = $this->withEmailLock($lockedEmail, function () use (
            $request,
            $tenant,
            $target,
            $data,
            $categoryIds,
            $lockedEmail,
        ): User {
            return DB::transaction(function () use (
                $request,
                $tenant,
                $target,
                $data,
                $categoryIds,
                $lockedEmail,
            ): User {
                $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
                if ($lockedTarget->user_type !== 'vendor'
                    || mb_strtolower(trim((string) $lockedTarget->email)) !== $lockedEmail) {
                    throw new ThinkTankApiException(
                        'CONFLICT_RETRY',
                        'This vendor identity changed while the request was being processed. Please retry.',
                        409,
                    );
                }
                $membership = DB::table('attp_think_tank_vendor_user')
                    ->where('think_tank_member_id', $tenant->getKey())
                    ->where('vendor_user_id', $lockedTarget->getKey())
                    ->lockForUpdate();
                abort_unless($membership->exists(), 404);
                if (isset($data['status'])) {
                    $membership->update(['status' => $data['status'], 'updated_at' => now()]);
                }
                if (array_key_exists('category_ids', $data)) {
                    DB::table('attp_think_tank_vendor_category_user')
                        ->where('think_tank_member_id', $tenant->getKey())
                        ->where('vendor_user_id', $lockedTarget->getKey())
                        ->delete();
                    if ($categoryIds !== []) {
                        DB::table('attp_think_tank_vendor_category_user')->insert(collect($categoryIds)
                            ->map(fn (string $categoryId): array => [
                                'think_tank_vendor_category_id' => $categoryId,
                                'vendor_user_id' => $lockedTarget->getKey(),
                                'think_tank_member_id' => $tenant->getKey(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ])->all());
                    }
                }
                $this->audit->required($request, 'think_tank.vendor.updated', 'Think Tank vendor membership updated.', [
                    'tenant_id' => (string) $tenant->getKey(),
                    'vendor_user_id' => (string) $lockedTarget->getKey(),
                    'status' => $data['status'] ?? null,
                    'category_ids_changed' => array_key_exists('category_ids', $data),
                ]);

                return $lockedTarget;
            });
        });

        $categoryRows = ThinkTankVendorCategory::query()
            ->where('think_tank_member_id', $tenant->getKey())
            ->whereHas('vendors', fn ($query) => $query
                ->where('users.id', $target->getKey())
                ->where('attp_think_tank_vendor_category_user.think_tank_member_id', $tenant->getKey()))
            ->orderBy('name')
            ->get(['id', 'name']);
        $status = DB::table('attp_think_tank_vendor_user')
            ->where('think_tank_member_id', $tenant->getKey())
            ->where('vendor_user_id', $target->getKey())
            ->value('status');
        $canManageSetup = (bool) DB::table('attp_think_tank_vendor_user')
            ->where('think_tank_member_id', $tenant->getKey())
            ->where('vendor_user_id', $target->getKey())
            ->value('can_manage_setup');

        return ThinkTankApiResponse::success([
            'id' => (string) $target->getKey(),
            'name' => (string) ($target->name ?: $target->email),
            'email' => (string) $target->email,
            'setupPending' => $status === 'active'
                && $canManageSetup
                && ! $target->is_disabled
                && ! $target->is_blacklisted
                && (bool) $target->must_change_password
                && $target->password_changed_at === null,
            'status' => $target->is_blacklisted
                ? 'blacklisted'
                : ($target->is_disabled ? 'disabled' : (string) $status),
            'categoryIds' => $categoryRows->pluck('id')->map(fn ($id): string => (string) $id)->all(),
            'categoryNames' => $categoryRows->pluck('name')->map(fn ($name): string => (string) $name)->all(),
        ], 200, 'Think Tank vendor membership updated.');
    }

    public function resendInvitation(Request $request, string $vendor): JsonResponse
    {
        $this->validateOnly($request, []);
        $tenant = $this->tenant($request);
        $target = User::query()
            ->whereKey($vendor)
            ->where('user_type', 'vendor')
            ->where(fn ($query) => $query->whereNull('is_disabled')->orWhere('is_disabled', false))
            ->where(fn ($query) => $query->whereNull('is_blacklisted')->orWhere('is_blacklisted', false))
            ->whereHas('vendorDirectoryThinkTanks', fn ($query) => $query
                ->where('attp_consortium_think_tanks.id', $tenant->getKey())
                ->where('attp_think_tank_vendor_user.status', 'active')
                ->where('attp_think_tank_vendor_user.can_manage_setup', true))
            ->firstOrFail();

        $lockedEmail = mb_strtolower(trim((string) $target->email));
        $sent = $this->withEmailLock($lockedEmail, function () use ($target, $tenant, $lockedEmail): bool {
            $readyTarget = DB::transaction(function () use ($target, $tenant, $lockedEmail): User {
                $lockedTarget = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
                abort_unless(
                    $lockedTarget->user_type === 'vendor'
                    && ! $lockedTarget->is_disabled
                    && ! $lockedTarget->is_blacklisted
                    && filter_var($lockedTarget->email, FILTER_VALIDATE_EMAIL) !== false
                    && mb_strtolower(trim((string) $lockedTarget->email)) === $lockedEmail,
                    404
                );
                $membership = DB::table('attp_think_tank_vendor_user')
                    ->where('think_tank_member_id', $tenant->getKey())
                    ->where('vendor_user_id', $lockedTarget->getKey())
                    ->lockForUpdate()
                    ->first();
                abort_unless(
                    $membership
                    && $membership->status === 'active'
                    && $membership->can_manage_setup,
                    404
                );

                if (! $lockedTarget->must_change_password || $lockedTarget->password_changed_at !== null) {
                    throw ValidationException::withMessages([
                        'vendor' => ['This vendor account is already established. Use Forgot password for account recovery.'],
                    ]);
                }

                return $lockedTarget;
            });

            // The DB locks are short-lived. The shared email lock remains held
            // while the external transport issues the single-use setup link.
            return $this->invitations->send(
                $readyTarget,
                AccountSetupInvitationService::PURPOSE_VENDOR,
            );
        });
        $this->audit->bestEffort($request, 'think_tank.vendor.invitation_sent', 'Vendor setup invitation processed.', [
            'tenant_id' => (string) $tenant->getKey(),
            'vendor_user_id' => (string) $target->getKey(),
            'delivered' => $sent,
        ]);

        return ThinkTankApiResponse::success([
            'id' => (string) $target->getKey(),
            'setupPending' => true,
            'credentialsSent' => $sent,
        ], 202, $sent
            ? 'A secure vendor account setup link was sent.'
            : 'The secure vendor account setup link could not be delivered.');
    }

    private function tenant(Request $request): ConsortiumThinkTank
    {
        /** @var ConsortiumThinkTank $tenant */
        $tenant = $request->attributes->get('think_tank.membership');

        return $tenant;
    }

    private function withEmailLock(string $email, callable $callback): mixed
    {
        $store = (string) config('think_tank_portal.email_lock_store', config('cache.default'));
        if (app()->environment('production') && in_array($store, ['array', 'file', 'null'], true)) {
            throw new ThinkTankApiException(
                'CONFIGURATION_ERROR',
                'Vendor management requires a shared production lock store.',
                503,
            );
        }

        $lock = Cache::store($store)->lock(
            'think-tank-user-email:'.hash('sha256', mb_strtolower(trim($email))),
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
                'This vendor identity is being changed by another request. Please retry.',
                409,
            );
        }
    }
}
