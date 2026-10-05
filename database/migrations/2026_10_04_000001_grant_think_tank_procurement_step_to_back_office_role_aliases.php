<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSION = 'think_tank.procurement.step';

    private const ROLE_ALIASES = [
        'System Admin',
        'Super Admin',
        'Super Administrator',
        'Admin',
        'Administrator',
        'Procurement Officer',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles')
            || ! Schema::hasTable('permissions')
            || ! Schema::hasTable('role_permission')) {
            return;
        }

        $permissionId = DB::table('permissions')
            ->where('name', self::PERMISSION)
            ->value('id');
        if (! $permissionId) {
            return;
        }

        DB::table('roles')
            ->whereIn('name', self::ROLE_ALIASES)
            ->pluck('id')
            ->each(fn ($roleId) => DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]));
    }

    public function down(): void
    {
        // Intentionally non-destructive: these roles may have held STEP access
        // before this drift-repair migration, so rollback must not revoke it.
    }
};
