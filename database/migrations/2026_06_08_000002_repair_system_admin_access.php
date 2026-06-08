<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles') || !Schema::hasTable('permissions') || !Schema::hasTable('users')) {
            return;
        }

        $now = now();

        $roleId = DB::table('roles')->where('name', 'System Admin')->value('id');

        if (!$roleId) {
            $roleId = (string) Str::uuid();

            DB::table('roles')->insert([
                'id' => $roleId,
                'name' => 'System Admin',
                'description' => 'Full system administrator',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $permissionId = DB::table('permissions')->where('name', 'users.manage')->value('id');

        if (!$permissionId) {
            $permissionId = (string) Str::uuid();

            DB::table('permissions')->insert([
                'id' => $permissionId,
                'name' => 'users.manage',
                'module' => 'system',
                'description' => 'Manage system users',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('role_permission')) {
            $permissionIds = DB::table('permissions')->pluck('id')->all();
            $existingPermissionIds = DB::table('role_permission')
                ->where('role_id', $roleId)
                ->pluck('permission_id')
                ->all();

            $missingPermissionIds = array_diff($permissionIds, $existingPermissionIds);

            foreach ($missingPermissionIds as $missingPermissionId) {
                DB::table('role_permission')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $missingPermissionId,
                ]);
            }
        }

        DB::table('users')
            ->where('user_type', 'admin')
            ->whereNull('role_id')
            ->update(['role_id' => $roleId]);
    }

    public function down(): void
    {
        // Data repair only; do not remove roles, permissions, or admin assignments.
    }
};
