<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_contract_negotiations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('submission_id')->nullable();
            $table->foreignUuid('vendor_id')->nullable();
            $table->decimal('proposed_amount', 15, 2)->nullable();
            $table->decimal('agreed_amount', 15, 2)->nullable();
            $table->string('status')->default('in_progress');
            $table->text('notes')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('agreed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_contract_negotiations');
    }
};
