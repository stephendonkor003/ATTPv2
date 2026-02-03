<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_projects', function (Blueprint $table) {
            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->after('program_id')
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
        });

        DB::statement("
            UPDATE myb_projects p
            INNER JOIN myb_programs pr ON p.program_id = pr.id
            SET p.governance_node_id = pr.governance_node_id
            WHERE p.governance_node_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('myb_projects', function (Blueprint $table) {
            $table->dropForeign(['governance_node_id']);
            $table->dropColumn('governance_node_id');
        });
    }
};
