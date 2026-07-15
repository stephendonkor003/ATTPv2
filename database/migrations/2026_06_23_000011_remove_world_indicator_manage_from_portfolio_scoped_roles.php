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

        $roleIds = DB::table('roles')
            ->whereIn('name', [
                'Evaluation Evaluator',
                'Monitoring and Evaluation Manager',
                'Portfolio Manager',
                'Portfolio Coordinator',
            ])
            ->pluck('id');

        if ($roleIds->isEmpty()) {
            return;
        }

        DB::table('role_permission')
            ->where('permission_id', $permissionId)
            ->whereIn('role_id', $roleIds)
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
