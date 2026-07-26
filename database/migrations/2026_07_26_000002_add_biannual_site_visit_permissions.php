<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'biannual_site_visits.view' => 'View bi-annual monitoring site visits',
        'biannual_site_visits.create' => 'Schedule bi-annual monitoring site visits and assign teams',
        'biannual_site_visits.respond' => 'Complete assigned bi-annual monitoring questionnaires',
        'biannual_site_visits.submit' => 'Submit completed bi-annual monitoring questionnaires',
        'biannual_site_visits.approve' => 'Review, return, and approve bi-annual monitoring site visits',
        'biannual_site_visits.templates.manage' => 'Build, import, version, and publish bi-annual questionnaires',
        'biannual_site_visits.export' => 'Export bi-annual monitoring site visit reports',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $permissionIds = collect(self::PERMISSIONS)->mapWithKeys(function (
            string $description,
            string $name
        ): array {
            $existing = DB::table('permissions')->where('name', $name)->first();
            $id = $existing?->id ?: (string) Str::uuid();

            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'id' => $id,
                    'module' => 'M&E',
                    'description' => $description,
                    'updated_at' => now(),
                    'created_at' => $existing?->created_at ?: now(),
                ]
            );

            return [$name => $id];
        });

        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permission')) {
            return;
        }

        $rolePermissions = [
            'System Admin' => array_keys(self::PERMISSIONS),
            'Monitoring and Evaluation Manager' => array_keys(self::PERMISSIONS),
            'Portfolio Manager' => [
                'biannual_site_visits.view',
                'biannual_site_visits.create',
                'biannual_site_visits.respond',
                'biannual_site_visits.submit',
                'biannual_site_visits.approve',
                'biannual_site_visits.export',
            ],
            'Portfolio Coordinator' => [
                'biannual_site_visits.view',
                'biannual_site_visits.create',
                'biannual_site_visits.respond',
                'biannual_site_visits.submit',
                'biannual_site_visits.approve',
                'biannual_site_visits.export',
            ],
        ];

        foreach ($rolePermissions as $roleName => $names) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');

            if (! $roleId) {
                continue;
            }

            foreach ($names as $name) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionIds->get($name),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $ids = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSIONS))
            ->pluck('id');

        if (Schema::hasTable('role_permission')) {
            DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        }

        if (Schema::hasTable('user_permission')) {
            DB::table('user_permission')->whereIn('permission_id', $ids)->delete();
        }

        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
