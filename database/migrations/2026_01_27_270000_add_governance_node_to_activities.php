<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_activities', function (Blueprint $table) {
            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->after('project_id')
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
        });

        DB::statement("\n            UPDATE myb_activities a\n            INNER JOIN myb_projects p ON a.project_id = p.id\n            SET a.governance_node_id = p.governance_node_id\n            WHERE a.governance_node_id IS NULL\n        ");
    }

    public function down(): void
    {
        Schema::table('myb_activities', function (Blueprint $table) {
            $table->dropForeign(['governance_node_id']);
            $table->dropColumn('governance_node_id');
        });
    }
};
