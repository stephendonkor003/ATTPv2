<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('myb_activities', 'governance_node_id')) {
            Schema::table('myb_activities', function (Blueprint $table) {
                $table->foreignUuid('governance_node_id')
                    ->nullable()
                    ->after('project_id')
                    ->constrained('myb_governance_nodes')
                    ->nullOnDelete();
            });
        }

        DB::statement("
            UPDATE myb_activities a
            SET governance_node_id = p.governance_node_id
            FROM myb_projects p
            WHERE a.project_id = p.id
              AND a.governance_node_id IS NULL
              AND p.governance_node_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('myb_activities', 'governance_node_id')) {
            Schema::table('myb_activities', function (Blueprint $table) {
                $table->dropForeign(['governance_node_id']);
                $table->dropColumn('governance_node_id');
            });
        }
    }
};
