<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_sectors', function (Blueprint $table) {
            $table->foreignUuid('governance_node_id')
                ->nullable()
                ->after('description')
                ->constrained('myb_governance_nodes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('myb_sectors', function (Blueprint $table) {
            $table->dropForeign(['governance_node_id']);
            $table->dropColumn('governance_node_id');
        });
    }
};
