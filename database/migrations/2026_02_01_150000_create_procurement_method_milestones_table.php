<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('myb_procurement_method_planned_milestones');
        Schema::create('myb_procurement_method_planned_milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('procurement_method_planned_id');
            $table->foreign('procurement_method_planned_id', 'fk_pmpm_proc_method')
                ->references('id')
                ->on('myb_procurement_method_planned')
                ->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('target_days')->default(0);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('procurement_method_planned_id', 'idx_pmpm_method_id');
            $table->index('target_days', 'idx_pmpm_target_days');
            $table->index('sort_order', 'idx_pmpm_sort_order');
            $table->index('is_active', 'idx_pmpm_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('myb_procurement_method_planned_milestones');
    }
};
