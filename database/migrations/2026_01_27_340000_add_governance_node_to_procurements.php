<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('procurements', 'governance_node_id')) {
            Schema::table('procurements', function (Blueprint $table) {
                $table->foreignUuid('governance_node_id')
                    ->nullable()
                    ->after('resource_id')
                    ->constrained('myb_governance_nodes')
                    ->nullOnDelete();
            });
        }

        DB::statement("
            UPDATE procurements p
            SET governance_node_id = r.governance_node_id
            FROM myb_resources r
            WHERE p.resource_id = r.id
              AND p.governance_node_id IS NULL
              AND r.governance_node_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('procurements', 'governance_node_id')) {
            Schema::table('procurements', function (Blueprint $table) {
                $table->dropForeign(['governance_node_id']);
                $table->dropColumn('governance_node_id');
            });
        }
    }
};
