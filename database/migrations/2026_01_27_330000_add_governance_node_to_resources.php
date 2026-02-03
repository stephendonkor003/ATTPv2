<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_resources', function (Blueprint $table) {
            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->after('resource_category_id')
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
        });

        DB::statement("\n            UPDATE myb_resources r\n            INNER JOIN myb_resource_categories c ON r.resource_category_id = c.id\n            SET r.governance_node_id = c.governance_node_id\n            WHERE r.governance_node_id IS NULL\n        ");
    }

    public function down(): void
    {
        Schema::table('myb_resources', function (Blueprint $table) {
            $table->dropForeign(['governance_node_id']);
            $table->dropColumn('governance_node_id');
        });
    }
};
