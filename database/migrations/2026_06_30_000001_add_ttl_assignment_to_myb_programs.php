<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_programs', function (Blueprint $table) {
            if (! Schema::hasColumn('myb_programs', 'ttl_user_id')) {
                $table->foreignUuid('ttl_user_id')
                    ->nullable()
                    ->after('governance_node_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('myb_programs', 'ttl_name')) {
                $table->string('ttl_name')->nullable()->after('ttl_user_id');
            }

            if (! Schema::hasColumn('myb_programs', 'ttl_email')) {
                $table->string('ttl_email')->nullable()->after('ttl_name');
            }

            if (! Schema::hasColumn('myb_programs', 'ttl_notified_at')) {
                $table->timestamp('ttl_notified_at')->nullable()->after('ttl_email');
            }
        });

        if (Schema::hasTable('roles')) {
            $now = now();

            DB::table('roles')->updateOrInsert(
                ['name' => 'Task Team Leader'],
                [
                    'id' => DB::table('roles')->where('name', 'Task Team Leader')->value('id') ?: (string) Str::uuid(),
                    'description' => 'Program-level TTL workspace access for reviewing assigned programs, projects, activities, budgets and partner-facing progress.',
                    'created_at' => DB::table('roles')->where('name', 'Task Team Leader')->value('created_at') ?: $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('myb_programs', function (Blueprint $table) {
            if (Schema::hasColumn('myb_programs', 'ttl_user_id')) {
                $table->dropForeign(['ttl_user_id']);
            }

            foreach (['ttl_notified_at', 'ttl_email', 'ttl_name', 'ttl_user_id'] as $column) {
                if (Schema::hasColumn('myb_programs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
