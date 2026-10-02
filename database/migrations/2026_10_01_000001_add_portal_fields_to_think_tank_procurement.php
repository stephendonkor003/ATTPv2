<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attp_think_tank_procurement_plans')
            && ! Schema::hasColumn('attp_think_tank_procurement_plans', 'portal_lock_version')) {
            Schema::table('attp_think_tank_procurement_plans', function (Blueprint $table): void {
                $table->unsignedBigInteger('portal_lock_version')->default(1);
            });
        }

        if (! Schema::hasTable('attp_think_tank_procurement_items')) {
            return;
        }

        $columns = [
            'limited_selection_justification' => fn (Blueprint $table) => $table->text('limited_selection_justification')->nullable(),
            'budget_reference' => fn (Blueprint $table) => $table->text('budget_reference')->nullable(),
            'bank_comment' => fn (Blueprint $table) => $table->text('bank_comment')->nullable(),
            'action_taken' => fn (Blueprint $table) => $table->text('action_taken')->nullable(),
            'portal_lock_version' => fn (Blueprint $table) => $table->unsignedBigInteger('portal_lock_version')->default(1),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('attp_think_tank_procurement_items', $column)) {
                Schema::table('attp_think_tank_procurement_items', $definition);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attp_think_tank_procurement_items')) {
            foreach ([
                'portal_lock_version',
                'action_taken',
                'bank_comment',
                'budget_reference',
                'limited_selection_justification',
            ] as $column) {
                if (Schema::hasColumn('attp_think_tank_procurement_items', $column)) {
                    Schema::table('attp_think_tank_procurement_items', function (Blueprint $table) use ($column): void {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        if (Schema::hasTable('attp_think_tank_procurement_plans')
            && Schema::hasColumn('attp_think_tank_procurement_plans', 'portal_lock_version')) {
            Schema::table('attp_think_tank_procurement_plans', function (Blueprint $table): void {
                $table->dropColumn('portal_lock_version');
            });
        }
    }
};
