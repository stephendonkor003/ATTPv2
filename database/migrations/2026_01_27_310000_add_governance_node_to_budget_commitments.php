<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('myb_budget_commitments', 'governance_node_id')) {
            Schema::table('myb_budget_commitments', function (Blueprint $table) {
                $table->foreignUuid('governance_node_id')
                    ->nullable()
                    ->after('program_funding_id')
                    ->constrained('myb_governance_nodes')
                    ->nullOnDelete();
            });
        }

        DB::statement("
            UPDATE myb_budget_commitments c
            SET governance_node_id = f.governance_node_id
            FROM myb_program_fundings f
            WHERE c.program_funding_id = f.id
              AND c.governance_node_id IS NULL
              AND f.governance_node_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('myb_budget_commitments', 'governance_node_id')) {
            Schema::table('myb_budget_commitments', function (Blueprint $table) {
                $table->dropForeign(['governance_node_id']);
                $table->dropColumn('governance_node_id');
            });
        }
    }
};
