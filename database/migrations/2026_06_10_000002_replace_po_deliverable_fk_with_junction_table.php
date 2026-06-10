<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the single-FK column added in the previous migration
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['deliverable_id']);
            $table->dropColumn('deliverable_id');
        });

        // Create the many-to-many junction table
        Schema::create('procurement_purchase_order_deliverables', function (Blueprint $table) {
            $table->foreignUuid('purchase_order_id')
                ->constrained('procurement_purchase_orders')
                ->cascadeOnDelete();
            $table->foreignUuid('deliverable_id')
                ->constrained('procurement_deliverables')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['purchase_order_id', 'deliverable_id']);
            $table->index('deliverable_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_purchase_order_deliverables');

        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->foreignUuid('deliverable_id')
                ->nullable()
                ->after('procurement_id')
                ->constrained('procurement_deliverables')
                ->nullOnDelete();
        });
    }
};
