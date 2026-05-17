<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->foreignUuid('budget_commitment_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('myb_budget_commitments')
                ->nullOnDelete();

            $table->index(['budget_commitment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['budget_commitment_id']);
            $table->dropIndex(['budget_commitment_id', 'status']);
            $table->dropColumn('budget_commitment_id');
        });
    }
};
