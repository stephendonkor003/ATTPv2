<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('myb_projects', 'governance_node_id')) {
            Schema::table('myb_projects', function (Blueprint $table) {
                $table->foreignUuid('governance_node_id')
                    ->nullable()
                    ->after('program_id')
                    ->constrained('myb_governance_nodes')
                    ->nullOnDelete();
            });
        }

        DB::statement("
            UPDATE myb_projects p
            SET governance_node_id = pr.governance_node_id
            FROM myb_programs pr
            WHERE p.program_id = pr.id
              AND p.governance_node_id IS NULL
              AND pr.governance_node_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('myb_projects', 'governance_node_id')) {
            Schema::table('myb_projects', function (Blueprint $table) {
                $table->dropForeign(['governance_node_id']);
                $table->dropColumn('governance_node_id');
            });
        }
    }
};
