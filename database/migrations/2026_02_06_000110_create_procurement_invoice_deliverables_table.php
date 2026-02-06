<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_invoice_deliverables', function (Blueprint $table) {
            $table->foreignUuid('invoice_id');
            $table->foreignUuid('deliverable_id');
            $table->timestamps();

            $table->unique(['invoice_id', 'deliverable_id'], 'invoice_deliverable_unique');
            $table->index('invoice_id');
            $table->index('deliverable_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_invoice_deliverables');
    }
};
