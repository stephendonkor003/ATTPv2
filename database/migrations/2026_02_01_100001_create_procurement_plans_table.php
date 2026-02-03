<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('myb_procurement_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Procurement Code - format: ET-AUC-469498-CS-CQS
            $table->string('procurement_code')->unique();
            $table->boolean('is_code_auto_generated')->default(true);

            // Title/Description
            $table->string('title');
            $table->text('description')->nullable();

            // Activity & Sub-Activity relationship
            $table->foreignUuid('activity_id')->nullable()->constrained('myb_activities')->nullOnDelete();
            $table->foreignUuid('sub_activity_id')->nullable()->constrained('myb_sub_activities')->nullOnDelete();

            // Procurement settings relationships
            $table->foreignUuid('method_planned_id')->nullable()->constrained('myb_procurement_method_planned')->nullOnDelete();
            $table->foreignUuid('geographic_id')->nullable()->constrained('myb_procurement_geographics')->nullOnDelete();
            $table->foreignUuid('stage_id')->nullable()->constrained('myb_procurement_stages')->nullOnDelete();
            $table->foreignUuid('status_id')->nullable()->constrained('myb_procurement_statuses')->nullOnDelete();
            $table->foreignUuid('step_stage_id')->nullable()->constrained('myb_procurement_step_stages')->nullOnDelete();
            $table->foreignUuid('step_approval_id')->nullable()->constrained('myb_procurement_step_approvals')->nullOnDelete();

            // Launch status
            $table->boolean('is_launched')->default(false);
            $table->timestamp('launched_at')->nullable();

            // Dates
            $table->date('estimated_start_date')->nullable();
            $table->date('estimated_end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();

            // Budget information
            $table->decimal('estimated_budget', 18, 2)->nullable();
            $table->string('currency', 10)->default('USD');

            // Additional fields
            $table->text('remarks')->nullable();
            $table->integer('fiscal_year')->nullable();

            // Audit fields
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('procurement_code');
            $table->index('is_launched');
            $table->index('estimated_start_date');
            $table->index('fiscal_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('myb_procurement_plans');
    }
};
