<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'world.indicators.manage')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $systemAdminRoleId = DB::table('roles')
            ->where('name', 'System Admin')
            ->value('id');

        DB::table('role_permission')
            ->join('roles', 'roles.id', '=', 'role_permission.role_id')
            ->where('role_permission.permission_id', $permissionId)
            ->when($systemAdminRoleId, function ($query) use ($systemAdminRoleId): void {
                $query->where('roles.id', '!=', $systemAdminRoleId);
            })
            ->delete();
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'world.indicators.manage')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->whereIn('name', [
                'Evaluation Evaluator',
                'M&E Officer',
                'Member State',
                'Monitoring and Evaluation Manager',
                'Portfolio Manager',
                'Portfolio Coordinator',
            ])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }
};
