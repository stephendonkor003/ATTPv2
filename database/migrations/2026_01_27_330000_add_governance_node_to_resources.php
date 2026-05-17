<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('myb_resources', 'governance_node_id')) {
            Schema::table('myb_resources', function (Blueprint $table) {
                $table->foreignUuid('governance_node_id')
                    ->nullable()
                    ->after('resource_category_id')
                    ->constrained('myb_governance_nodes')
                    ->nullOnDelete();
            });
        }

        DB::statement("
            UPDATE myb_resources r
            SET governance_node_id = c.governance_node_id
            FROM myb_resource_categories c
            WHERE r.resource_category_id = c.id
              AND r.governance_node_id IS NULL
              AND c.governance_node_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('myb_resources', 'governance_node_id')) {
            Schema::table('myb_resources', function (Blueprint $table) {
                $table->dropForeign(['governance_node_id']);
                $table->dropColumn('governance_node_id');
            });
        }
    }
};
