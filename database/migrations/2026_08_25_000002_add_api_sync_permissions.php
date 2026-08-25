<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'api_sync.view' => 'View API synchronization status and pairing history',
        'api_sync.generate' => 'Generate one-time API synchronization pairing codes',
        'api_sync.revoke' => 'Revoke active API synchronization sessions',
        'api_sync.audit.view' => 'View API synchronization audit events',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $name => $description) {
            $existing = DB::table('permissions')->where('name', $name)->first();
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'id' => $existing?->id ?: (string) Str::uuid(),
                    'module' => 'API Sync',
                    'description' => $description,
                    'created_at' => $existing?->created_at ?: $now,
                    'updated_at' => $now,
                ]
            );
        }

        $role = DB::table('roles')->where('name', 'API Sync Administrator')->first();
        $roleId = $role?->id ?: (string) Str::uuid();
        DB::table('roles')->updateOrInsert(
            ['name' => 'API Sync Administrator'],
            [
                'id' => $roleId,
                'description' => 'Generates and supervises controlled ATTP data synchronization sessions.',
                'created_at' => $role?->created_at ?: $now,
                'updated_at' => $now,
            ]
        );

        $permissionIds = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        $systemRoleIds = DB::table('roles')
            ->whereIn('name', ['API Sync Administrator', 'System Admin'])
            ->pluck('id');

        foreach ($systemRoleIds as $systemRoleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $systemRoleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('user_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        $role = DB::table('roles')->where('name', 'API Sync Administrator')->first();
        if ($role) {
            DB::table('role_permission')->where('role_id', $role->id)->delete();
            DB::table('roles')->where('id', $role->id)->delete();
        }
    }
};
