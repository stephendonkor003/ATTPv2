<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('myb_sub_activities', 'governance_node_id')) {
            Schema::table('myb_sub_activities', function (Blueprint $table) {
                $table->foreignUuid('governance_node_id')
                    ->nullable()
                    ->after('activity_id')
                    ->constrained('myb_governance_nodes')
                    ->nullOnDelete();
            });
        }

        DB::statement("
            UPDATE myb_sub_activities s
            SET governance_node_id = a.governance_node_id
            FROM myb_activities a
            WHERE s.activity_id = a.id
              AND s.governance_node_id IS NULL
              AND a.governance_node_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('myb_sub_activities', 'governance_node_id')) {
            Schema::table('myb_sub_activities', function (Blueprint $table) {
                $table->dropForeign(['governance_node_id']);
                $table->dropColumn('governance_node_id');
            });
        }
    }
};
