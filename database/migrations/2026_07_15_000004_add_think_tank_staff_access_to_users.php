<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PORTAL_PERMISSIONS = [
        'think_tank.finance.view' => 'View think tank finance records and funding transfers',
        'think_tank.finance.manage' => 'Manage think tank finance confirmations and records',
        'think_tank.procurement_plans.view' => 'View procurement plans for the assigned think tank',
        'think_tank.procurement_plans.manage' => 'Create and maintain procurement plans for the assigned think tank',
        'think_tank.team.manage' => 'Manage portal staff and access levels for the assigned think tank',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('think_tank_member_id')->nullable()->after('member_state_id');
            $table->string('think_tank_access_level', 40)->nullable()->after('think_tank_member_id');

            $table->foreign('think_tank_member_id', 'users_think_tank_member_fk')
                ->references('id')
                ->on('attp_consortium_think_tanks')
                ->nullOnDelete();
            $table->index(
                ['think_tank_member_id', 'think_tank_access_level'],
                'users_think_tank_access_idx'
            );
        });

        // Every existing portal identity represented the think tank's primary
        // account, so administrator is the least disruptive safe backfill.
        DB::table('attp_consortium_think_tanks')
            ->whereNotNull('portal_user_id')
            ->orderBy('id')
            ->get(['id', 'portal_user_id'])
            ->each(function (object $member): void {
                DB::table('users')
                    ->where('id', $member->portal_user_id)
                    ->where('user_type', 'think_tank')
                    ->update([
                        'think_tank_member_id' => $member->id,
                        'think_tank_access_level' => 'think_tank_admin',
                        'updated_at' => now(),
                    ]);
            });

        // Keep unlinked legacy think tank users recognizable as administrators;
        // EnsureThinkTankUser still denies them until a membership is assigned.
        DB::table('users')
            ->where('user_type', 'think_tank')
            ->whereNull('think_tank_access_level')
            ->update([
                'think_tank_access_level' => 'think_tank_admin',
                'updated_at' => now(),
            ]);

        $now = now();
        foreach (self::PORTAL_PERMISSIONS as $name => $description) {
            $existing = DB::table('permissions')->where('name', $name)->first();

            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'id' => $existing?->id ?: (string) Str::uuid(),
                    'module' => 'Think Tank Portal',
                    'description' => $description,
                    'created_at' => $existing?->created_at ?: $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::PORTAL_PERMISSIONS))
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('user_permission')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign('users_think_tank_member_fk');
            $table->dropIndex('users_think_tank_access_idx');
            $table->dropColumn(['think_tank_member_id', 'think_tank_access_level']);
        });
    }
};
