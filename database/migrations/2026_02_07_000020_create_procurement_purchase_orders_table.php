<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('negotiation_id')->nullable();
            $table->foreignUuid('vendor_id')->nullable();
            $table->foreignUuid('sub_activity_id')->nullable();
            $table->foreignUuid('governance_node_id')->nullable();
            $table->string('reference_no')->nullable()->unique();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('status')->default('draft');
            $table->string('created_by')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_purchase_orders');
    }
};
