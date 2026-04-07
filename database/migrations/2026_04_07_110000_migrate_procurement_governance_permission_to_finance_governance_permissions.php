<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $this->ensurePermission(
            'finance.governance_structure.view',
            'Finance',
            'View governance structure'
        );
        $this->ensurePermission(
            'finance.governance_structure.manage',
            'Finance',
            'Manage governance structure records'
        );

        $legacyPermissionId = DB::table('permissions')
            ->where('name', 'procurement.settings.governance')
            ->value('id');

        if (! $legacyPermissionId) {
            return;
        }

        $viewPermissionId = DB::table('permissions')
            ->where('name', 'finance.governance_structure.view')
            ->value('id');
        $managePermissionId = DB::table('permissions')
            ->where('name', 'finance.governance_structure.manage')
            ->value('id');

        $this->copyRoleAssignments($legacyPermissionId, array_filter([$viewPermissionId, $managePermissionId]));
        $this->copyUserAssignments($legacyPermissionId, array_filter([$viewPermissionId, $managePermissionId]));

        if (Schema::hasTable('role_permission')) {
            DB::table('role_permission')
                ->where('permission_id', $legacyPermissionId)
                ->delete();
        }

        if (Schema::hasTable('user_permission')) {
            DB::table('user_permission')
                ->where('permission_id', $legacyPermissionId)
                ->delete();
        }

        DB::table('permissions')
            ->where('id', $legacyPermissionId)
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $exists = DB::table('permissions')
            ->where('name', 'procurement.settings.governance')
            ->exists();

        if (! $exists) {
            DB::table('permissions')->insert($this->permissionPayload([
                'id' => (string) Str::uuid(),
                'name' => 'procurement.settings.governance',
                'module' => 'Procurement Settings',
                'description' => 'Manage governance structure',
            ]));
        }
    }

    private function ensurePermission(string $name, string $module, string $description): void
    {
        $exists = DB::table('permissions')
            ->where('name', $name)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('permissions')->insert($this->permissionPayload([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'module' => $module,
            'description' => $description,
        ]));
    }

    private function copyRoleAssignments(string $legacyPermissionId, array $targetPermissionIds): void
    {
        if (! Schema::hasTable('role_permission') || empty($targetPermissionIds)) {
            return;
        }

        $roleIds = DB::table('role_permission')
            ->where('permission_id', $legacyPermissionId)
            ->pluck('role_id')
            ->filter()
            ->unique()
            ->values();

        foreach ($roleIds as $roleId) {
            foreach ($targetPermissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ], [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    private function copyUserAssignments(string $legacyPermissionId, array $targetPermissionIds): void
    {
        if (! Schema::hasTable('user_permission') || empty($targetPermissionIds)) {
            return;
        }

        $userIds = DB::table('user_permission')
            ->where('permission_id', $legacyPermissionId)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            foreach ($targetPermissionIds as $permissionId) {
                DB::table('user_permission')->updateOrInsert([
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                ], [
                    'user_id' => $userId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    private function permissionPayload(array $attributes): array
    {
        if (Schema::hasColumn('permissions', 'created_at') && ! array_key_exists('created_at', $attributes)) {
            $attributes['created_at'] = now();
        }

        if (Schema::hasColumn('permissions', 'updated_at') && ! array_key_exists('updated_at', $attributes)) {
            $attributes['updated_at'] = now();
        }

        return $attributes;
    }
};
