<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_sub_activities', function (Blueprint $table) {
            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->after('activity_id')
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
        });

        DB::statement("\n            UPDATE myb_sub_activities s\n            INNER JOIN myb_activities a ON s.activity_id = a.id\n            SET s.governance_node_id = a.governance_node_id\n            WHERE s.governance_node_id IS NULL\n        ");
    }

    public function down(): void
    {
        Schema::table('myb_sub_activities', function (Blueprint $table) {
            $table->dropForeign(['governance_node_id']);
            $table->dropColumn('governance_node_id');
        });
    }
};
