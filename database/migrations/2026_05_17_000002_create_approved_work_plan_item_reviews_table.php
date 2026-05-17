<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approved_work_plan_item_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_request_item_id')
                ->unique()
                ->constrained('myb_purchase_request_items')
                ->cascadeOnDelete();
            $table->foreignUuid('program_funding_id')
                ->nullable()
                ->constrained('myb_program_fundings')
                ->nullOnDelete();
            $table->foreignUuid('funder_id')
                ->nullable()
                ->constrained('myb_funders')
                ->nullOnDelete();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignUuid('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['program_funding_id', 'status']);
            $table->index(['funder_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approved_work_plan_item_reviews');
    }
};
