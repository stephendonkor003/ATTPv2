<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Procurement Geographics
        Schema::create('myb_procurement_geographics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('is_active');
        });

        // 2. Procurement Method Planned
        Schema::create('myb_procurement_method_planned', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('method_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('method_name');
            $table->index('is_active');
        });

        // 3. Procurement Stage
        Schema::create('myb_procurement_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('stage_name');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('stage_name');
            $table->index('sort_order');
            $table->index('is_active');
        });

        // 4. Procurement Status
        Schema::create('myb_procurement_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('color')->nullable()->default('#6c757d');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('is_active');
        });

        // 5. Procurement Step Stage
        Schema::create('myb_procurement_step_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignUuid('stage_id')->nullable()->constrained('myb_procurement_stages')->nullOnDelete();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('sort_order');
            $table->index('is_active');
        });

        // 6. Procurement Step Approval Process
        Schema::create('myb_procurement_step_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->foreignUuid('step_stage_id')->nullable()->constrained('myb_procurement_step_stages')->nullOnDelete();
            $table->foreignUuid('governance_node_id')->nullable()->constrained('myb_governance_nodes')->nullOnDelete();
            $table->text('description')->nullable();
            $table->integer('approval_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('approval_order');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('myb_procurement_step_approvals');
        Schema::dropIfExists('myb_procurement_step_stages');
        Schema::dropIfExists('myb_procurement_statuses');
        Schema::dropIfExists('myb_procurement_stages');
        Schema::dropIfExists('myb_procurement_method_planned_milestones');
        Schema::dropIfExists('myb_procurement_method_planned');
        Schema::dropIfExists('myb_procurement_geographics');
    }
};
