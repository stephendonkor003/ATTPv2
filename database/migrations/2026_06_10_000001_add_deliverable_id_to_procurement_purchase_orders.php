<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->foreignUuid('deliverable_id')
                ->nullable()
                ->after('procurement_id')
                ->constrained('procurement_deliverables')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['deliverable_id']);
            $table->dropColumn('deliverable_id');
        });
    }
};
