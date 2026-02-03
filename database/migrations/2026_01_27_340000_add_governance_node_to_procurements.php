<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->after('resource_id')
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
        });

        DB::statement("\n            UPDATE procurements p\n            INNER JOIN myb_resources r ON p.resource_id = r.id\n            SET p.governance_node_id = r.governance_node_id\n            WHERE p.governance_node_id IS NULL\n        ");
    }

    public function down(): void
    {
        Schema::table('procurements', function (Blueprint $table) {
            $table->dropForeign(['governance_node_id']);
            $table->dropColumn('governance_node_id');
        });
    }
};
