<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'me.data_entry.view',
        'me.data_entry.manage',
        'me.framework.manage',
        'me.targets.manage',
        'me.submissions.review',
        'me.results.view',
        'me.reports.export',
        'me.dqa.manage',
    ];

    public function up(): void
    {
        $roleIds = DB::table('roles')->whereIn('name', [
            'System Admin',
            'M&e',
            'M&E Manager',
            'M&E Officer',
            'Monitoring and Evaluation Manager',
        ])->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Permissions may have been granted intentionally after deployment.
        // A rollback must not revoke those operational grants.
    }
};
