<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_purchase_order_item_evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')
                ->constrained('procurement_purchase_orders')
                ->cascadeOnDelete();
            $table->foreignUuid('purchase_request_item_id')
                ->constrained('myb_purchase_request_items')
                ->cascadeOnDelete();
            $table->foreignUuid('deliverable_id')
                ->nullable()
                ->constrained('procurement_deliverables')
                ->nullOnDelete();
            $table->boolean('is_met')->default(false);
            $table->text('notes')->nullable();
            $table->json('documents')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->unique(['purchase_order_id', 'purchase_request_item_id'], 'po_item_evidence_unique');
            $table->index('deliverable_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_purchase_order_item_evidence');
    }
};
