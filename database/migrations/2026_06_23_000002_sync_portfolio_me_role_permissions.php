<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $portfolioPermissionDescriptions = [
            'sector.view' => 'View portfolios',
            'sector.create' => 'Create portfolios',
            'sector.edit' => 'Edit portfolios',
            'sector.delete' => 'Delete portfolios',
        ];

        foreach ($portfolioPermissionDescriptions as $name => $description) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'id' => DB::table('permissions')->where('name', $name)->value('id') ?: (string) Str::uuid(),
                    'module' => 'Budget',
                    'description' => $description,
                    'updated_at' => $now,
                    'created_at' => DB::table('permissions')->where('name', $name)->value('created_at') ?: $now,
                ]
            );
        }

        $role = DB::table('roles')->where('name', 'Monitoring and Evaluation Manager')->first();
        if (!$role) {
            $roleId = (string) Str::uuid();
            DB::table('roles')->insert([
                'id' => $roleId,
                'name' => 'Monitoring and Evaluation Manager',
                'description' => 'Owns monitoring and evaluation setup, indicators, and portfolio reporting.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $roleId = $role->id;
            DB::table('roles')
                ->where('id', $roleId)
                ->update([
                    'description' => $role->description ?: 'Owns monitoring and evaluation setup, indicators, and portfolio reporting.',
                    'updated_at' => $now,
                ]);
        }

        $permissionNames = [
            'me.configuration.view',
            'me.configuration.manage',
            'budget.access',
            'budget.reports.view',
            'budget.summary.view',
            'sector.view',
            'program.view',
            'project.view',
            'activities.view',
            'subactivities.view',
        ];

        $permissionIds = DB::table('permissions')
            ->whereIn('name', $permissionNames)
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            $exists = DB::table('role_permission')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists();

            if (!$exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')
            ->where('name', 'Monitoring and Evaluation Manager')
            ->value('id');

        if ($roleId) {
            DB::table('role_permission')
                ->where('role_id', $roleId)
                ->delete();

            $hasUsers = DB::table('users')
                ->where('role_id', $roleId)
                ->exists();

            if (!$hasUsers) {
                DB::table('roles')
                    ->where('id', $roleId)
                    ->delete();
            }
        }
    }
};
