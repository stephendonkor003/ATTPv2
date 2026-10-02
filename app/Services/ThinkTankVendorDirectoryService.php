<?php

namespace App\Services;

use App\Models\ConsortiumThinkTank;
use App\Models\Procurement;
use App\Models\ThinkTankVendorCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ThinkTankVendorDirectoryService
{
    public const MEMBERSHIP_STATUSES = ['active', 'disabled'];

    /** @return array{categories: array<int, array<string, mixed>>, vendors: array<int, array<string, mixed>>} */
    public function payload(ConsortiumThinkTank $tenant): array
    {
        return [
            'categories' => $this->categories($tenant)->map(fn (ThinkTankVendorCategory $category): array => [
                'id' => (string) $category->getKey(),
                'name' => (string) $category->name,
                'description' => $category->description ?: null,
                'isActive' => (bool) $category->is_active,
                'vendorCount' => (int) $category->vendor_count,
            ])->values()->all(),
            'vendors' => $this->vendors($tenant)->map(function (User $vendor): array {
                $membership = $vendor->vendorDirectoryThinkTanks->first()?->pivot;
                $categories = $vendor->thinkTankVendorCategories;

                return [
                    'id' => (string) $vendor->getKey(),
                    'name' => (string) ($vendor->name ?: $vendor->email),
                    'email' => (string) $vendor->email,
                    'setupPending' => (bool) ($membership?->can_manage_setup)
                        && $membership?->status === 'active'
                        && ! $vendor->is_disabled
                        && ! $vendor->is_blacklisted
                        && (bool) $vendor->must_change_password
                        && $vendor->password_changed_at === null,
                    'status' => $vendor->is_blacklisted
                        ? 'blacklisted'
                        : ($vendor->is_disabled ? 'disabled' : (string) ($membership?->status ?: 'disabled')),
                    'categoryIds' => $categories->pluck('id')->map(fn ($id): string => (string) $id)->values()->all(),
                    'categoryNames' => $categories->pluck('name')->map(fn ($name): string => (string) $name)->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    /** @return Collection<int, ThinkTankVendorCategory> */
    public function categories(ConsortiumThinkTank $tenant, bool $activeOnly = false): Collection
    {
        return ThinkTankVendorCategory::query()
            ->where('think_tank_member_id', $tenant->getKey())
            ->when($activeOnly, fn (Builder $query) => $query->where('is_active', true))
            ->withCount(['vendors as vendor_count' => fn (Builder $query) => $query
                ->where('users.user_type', 'vendor')
                ->where('attp_think_tank_vendor_category_user.think_tank_member_id', $tenant->getKey())])
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, User> */
    public function vendors(ConsortiumThinkTank $tenant, bool $activeOnly = false): Collection
    {
        return User::query()
            ->where('user_type', 'vendor')
            ->whereHas('vendorDirectoryThinkTanks', fn (Builder $query) => $query
                ->where('attp_consortium_think_tanks.id', $tenant->getKey())
                ->when($activeOnly, fn (Builder $membership) => $membership
                    ->where('attp_think_tank_vendor_user.status', 'active')))
            ->with(['thinkTankVendorCategories' => fn ($query) => $query
                ->where('attp_think_tank_vendor_categories.think_tank_member_id', $tenant->getKey())
                ->wherePivot('think_tank_member_id', $tenant->getKey())
                ->orderBy('name')])
            ->with(['vendorDirectoryThinkTanks' => fn ($query) => $query
                ->where('attp_consortium_think_tanks.id', $tenant->getKey())])
            ->orderBy('name')
            ->orderBy('email')
            ->get();
    }

    /**
     * @param array<int, string> $categoryIds
     * @param array<int, string> $vendorIds
     * @return array{categoryIds: array<int, string>, vendorIds: array<int, string>, categoryNames: array<int, string>}
     */
    public function validateTargets(
        ConsortiumThinkTank $tenant,
        array $categoryIds,
        array $vendorIds,
        bool $required,
    ): array {
        $categoryIds = $this->uniqueIds($categoryIds);
        $vendorIds = $this->uniqueIds($vendorIds);

        $categories = ThinkTankVendorCategory::query()
            ->where('think_tank_member_id', $tenant->getKey())
            ->where('is_active', true)
            ->whereIn('id', $categoryIds)
            ->orderBy('name')
            ->get(['id', 'name']);
        if ($categories->count() !== count($categoryIds)) {
            throw ValidationException::withMessages([
                'tenant_vendor_category_ids' => ['One or more vendor categories are inactive or do not belong to this Think Tank.'],
            ]);
        }

        $validVendorIds = DB::table('attp_think_tank_vendor_user')
            ->join('users', 'users.id', '=', 'attp_think_tank_vendor_user.vendor_user_id')
            ->where('attp_think_tank_vendor_user.think_tank_member_id', $tenant->getKey())
            ->where('attp_think_tank_vendor_user.status', 'active')
            ->where('users.user_type', 'vendor')
            ->where(fn ($query) => $query->whereNull('users.is_disabled')->orWhere('users.is_disabled', false))
            ->where(fn ($query) => $query->whereNull('users.is_blacklisted')->orWhere('users.is_blacklisted', false))
            ->whereIn('users.id', $vendorIds)
            ->pluck('users.id')
            ->map(fn ($id): string => (string) $id)
            ->all();
        if (count($validVendorIds) !== count($vendorIds)) {
            throw ValidationException::withMessages([
                'tenant_vendor_ids' => ['One or more vendors are inactive or do not belong to this Think Tank.'],
            ]);
        }

        if ($required && $categoryIds === [] && $vendorIds === []) {
            throw ValidationException::withMessages([
                'tenant_vendor_category_ids' => ['Choose at least one tenant vendor category or individual vendor.'],
            ]);
        }

        return [
            'categoryIds' => $categoryIds,
            'vendorIds' => $vendorIds,
            'categoryNames' => $categories->pluck('name')->map(fn ($name): string => (string) $name)->all(),
        ];
    }

    public function vendorCanAccess(User $vendor, Procurement $procurement): bool
    {
        if ($vendor->user_type !== 'vendor' || $vendor->is_disabled || $vendor->is_blacklisted) {
            return false;
        }

        if ($procurement->procurement_owner_type !== 'think_tank' || ! $procurement->think_tank_member_id) {
            return $this->legacyVendorCanAccess($vendor, $procurement);
        }

        $tenantId = (string) $procurement->think_tank_member_id;
        $hasActiveMembership = DB::table('attp_think_tank_vendor_user')
            ->where('think_tank_member_id', $tenantId)
            ->where('vendor_user_id', $vendor->getKey())
            ->where('status', 'active')
            ->exists();
        if (! $hasActiveMembership) {
            return false;
        }

        $targetVendorIds = $this->uniqueIds((array) $procurement->tenant_vendor_ids);
        if (in_array((string) $vendor->getKey(), $targetVendorIds, true)) {
            return true;
        }

        $targetCategoryIds = $this->uniqueIds((array) $procurement->tenant_vendor_category_ids);
        if ($targetCategoryIds !== []) {
            return DB::table('attp_think_tank_vendor_category_user')
                ->join(
                    'attp_think_tank_vendor_categories',
                    'attp_think_tank_vendor_categories.id',
                    '=',
                    'attp_think_tank_vendor_category_user.think_tank_vendor_category_id'
                )
                ->where('attp_think_tank_vendor_category_user.think_tank_member_id', $tenantId)
                ->where('attp_think_tank_vendor_category_user.vendor_user_id', $vendor->getKey())
                ->where('attp_think_tank_vendor_categories.think_tank_member_id', $tenantId)
                ->where('attp_think_tank_vendor_categories.is_active', true)
                ->whereIn('attp_think_tank_vendor_categories.id', $targetCategoryIds)
                ->exists();
        }

        // Compatibility for tenant opportunities created before UUID targeting:
        // membership is still mandatory, so another Think Tank cannot gain access.
        return $targetVendorIds === []
            && $targetCategoryIds === []
            && $this->legacyVendorCanAccess($vendor, $procurement);
    }

    private function legacyVendorCanAccess(User $vendor, Procurement $procurement): bool
    {
        $categories = array_values((array) $procurement->vendor_categories);

        return filled($vendor->vendor_category)
            && in_array((string) $vendor->vendor_category, $categories, true)
            && \App\Models\VendorCategory::query()
                ->where('name', $vendor->vendor_category)
                ->where('is_active', true)
                ->exists();
    }

    /** @param array<int, mixed> $ids
     * @return array<int, string>
     */
    private function uniqueIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
