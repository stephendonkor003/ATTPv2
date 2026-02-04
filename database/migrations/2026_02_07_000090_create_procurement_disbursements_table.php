<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_disbursements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')->nullable();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('vendor_id')->nullable();
            $table->foreignUuid('sub_activity_id')->nullable();
            $table->foreignUuid('governance_node_id')->nullable();
            $table->string('reference_no')->nullable()->unique();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('status')->default('completed');
            $table->timestamp('paid_at')->nullable();
            $table->string('created_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_disbursements');
    }
};
