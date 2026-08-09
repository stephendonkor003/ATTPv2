<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'me.data_entry.view' => 'View M&E forms, periods, collections and submissions',
        'me.data_entry.manage' => 'Manage M&E collection configuration and submission workflow',
        'me.framework.manage' => 'Manage controlled ATTP Results Framework versions and IRS records',
        'me.targets.manage' => 'Manage project and Think Tank indicator target allocations and revisions',
        'me.submissions.review' => 'Review, verify, return, approve or reject M&E submissions',
        'me.results.view' => 'View approved-only ATTP Results Framework performance',
        'me.reports.export' => 'Export official ATTP MEL reports',
        'me.dqa.manage' => 'Review and resolve M&E data-quality findings',
    ];

    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('name', ['System Admin', 'M&E Manager', 'M&E Officer', 'M&e'])
            ->pluck('id');
        foreach (self::PERMISSIONS as $name => $description) {
            $permissionId = DB::table('permissions')->where('name', $name)->value('id');
            if (! $permissionId) {
                $permissionId = (string) Str::uuid();
                DB::table('permissions')->insert([
                    'id' => $permissionId,
                    'name' => $name,
                    'module' => 'M&E',
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ($roleIds as $roleId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
