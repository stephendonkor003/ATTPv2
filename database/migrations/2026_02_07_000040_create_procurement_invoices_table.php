<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('vendor_id')->nullable();
            $table->foreignUuid('sub_activity_id')->nullable();
            $table->foreignUuid('governance_node_id')->nullable();
            $table->date('invoice_month')->nullable();
            $table->string('reference_no')->nullable()->unique();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency')->nullable();
            $table->string('status')->default('submitted');
            $table->string('created_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_invoices');
    }
};
