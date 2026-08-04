<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSION = 'administrative_assistant.evidence.manage';

    private const ROLE_NAMES = [
        'Administrative Assistant',
        'Administrative Assistatant',
    ];

    public function up(): void
    {
        if (Schema::hasTable('procurement_purchase_order_item_evidence')
            && ! Schema::hasColumn('procurement_purchase_order_item_evidence', 'invoice_id')) {
            Schema::table('procurement_purchase_order_item_evidence', function (Blueprint $table): void {
                $table->foreignUuid('invoice_id')
                    ->nullable()
                    ->after('deliverable_id')
                    ->constrained('procurement_invoices')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('permissions')) {
            return;
        }

        $existing = DB::table('permissions')->where('name', self::PERMISSION)->first();
        $permissionId = $existing?->id ?: (string) Str::uuid();

        DB::table('permissions')->updateOrInsert(
            ['name' => self::PERMISSION],
            [
                'id' => $permissionId,
                'module' => 'Finance',
                'description' => 'Use the Administrative Assistant invoice and evidence upload workspace',
                'created_at' => $existing?->created_at ?: now(),
                'updated_at' => now(),
            ]
        );

        if (! Schema::hasTable('roles') || ! Schema::hasTable('role_permission')) {
            return;
        }

        DB::table('roles')
            ->whereIn('name', self::ROLE_NAMES)
            ->pluck('id')
            ->each(function ($roleId) use ($permissionId): void {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->value('id');

            if ($permissionId && Schema::hasTable('role_permission')) {
                DB::table('role_permission')->where('permission_id', $permissionId)->delete();
            }

            if ($permissionId && Schema::hasTable('user_permission')) {
                DB::table('user_permission')->where('permission_id', $permissionId)->delete();
            }

            if ($permissionId) {
                DB::table('permissions')->where('id', $permissionId)->delete();
            }
        }

        if (Schema::hasTable('procurement_purchase_order_item_evidence')
            && Schema::hasColumn('procurement_purchase_order_item_evidence', 'invoice_id')) {
            Schema::table('procurement_purchase_order_item_evidence', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('invoice_id');
            });
        }
    }
};
