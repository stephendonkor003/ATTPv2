<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const ROLE_NAME = 'Procurement Officer';

    private const PERMISSIONS = [
        'think_tank.procurement.review',
        'think_tank.procurement.reports',
        'think_tank.procurement.step',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions') || ! Schema::hasTable('role_permission')) {
            return;
        }

        $role = DB::table('roles')->where('name', self::ROLE_NAME)->first();
        $roleId = $role?->id ?: (string) Str::uuid();

        DB::table('roles')->updateOrInsert(
            ['name' => self::ROLE_NAME],
            [
                'id' => $roleId,
                'description' => 'ATTP procurement officer responsible for Think Tank plan review, STEP handoff and World Bank no-objection updates.',
                'created_at' => $role?->created_at ?: now(),
                'updated_at' => now(),
            ]
        );

        DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id')
            ->each(fn ($permissionId) => DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]));
    }

    public function down(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permission')) {
            return;
        }

        $role = DB::table('roles')->where('name', self::ROLE_NAME)->first();
        if (! $role) {
            return;
        }

        DB::table('role_permission')->where('role_id', $role->id)->delete();

        if (Schema::hasTable('users') && ! DB::table('users')->where('role_id', $role->id)->exists()) {
            DB::table('roles')->where('id', $role->id)->delete();
        }
    }
};
