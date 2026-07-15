<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('name', ['Portfolio Manager', 'Portfolio Coordinator'])
            ->pluck('id');

        $permissionId = DB::table('permissions')
            ->where('name', 'sector.create')
            ->value('id');

        if ($roleIds->isEmpty() || ! $permissionId) {
            return;
        }

        DB::table('role_permission')
            ->whereIn('role_id', $roleIds)
            ->where('permission_id', $permissionId)
            ->delete();
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('name', ['Portfolio Manager', 'Portfolio Coordinator'])
            ->pluck('id');

        $permissionId = DB::table('permissions')
            ->where('name', 'sector.create')
            ->value('id');

        if ($roleIds->isEmpty() || ! $permissionId) {
            return;
        }

        foreach ($roleIds as $roleId) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }
};
