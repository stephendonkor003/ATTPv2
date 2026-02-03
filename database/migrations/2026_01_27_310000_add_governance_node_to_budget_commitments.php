<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_budget_commitments', function (Blueprint $table) {
            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->after('program_funding_id')
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
        });

        DB::statement("\n            UPDATE myb_budget_commitments c\n            INNER JOIN myb_program_fundings f ON c.program_funding_id = f.id\n            SET c.governance_node_id = f.governance_node_id\n            WHERE c.governance_node_id IS NULL\n        ");
    }

    public function down(): void
    {
        Schema::table('myb_budget_commitments', function (Blueprint $table) {
            $table->dropForeign(['governance_node_id']);
            $table->dropColumn('governance_node_id');
        });
    }
};
