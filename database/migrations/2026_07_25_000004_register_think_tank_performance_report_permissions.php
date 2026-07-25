<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'think_tank.me.reports.view' => 'View performance reports owned by the think tank or implementing partner',
        'think_tank.me.reports.manage' => 'Create and edit draft performance reports for assigned indicators',
        'think_tank.me.reports.submit' => 'Submit complete performance reports to the Secretariat or M&E Officer',
    ];

    public function up(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'System Admin')->value('id');

        foreach (self::PERMISSIONS as $name => $description) {
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');
            if (! $permissionId) {
                $permissionId = (string) Str::uuid();
                DB::table('permissions')->insert([
                    'id' => $permissionId,
                    'name' => $name,
                    'module' => 'Think Tank Portal',
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($adminRoleId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $adminRoleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
