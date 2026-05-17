<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approved_work_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('awp_code')->unique();
            $table->string('title');
            $table->foreignUuid('budget_commitment_id')->nullable()->constrained('myb_budget_commitments')->nullOnDelete();
            $table->foreignUuid('program_funding_id')->nullable()->constrained('myb_program_fundings')->nullOnDelete();
            $table->foreignUuid('governance_node_id')->nullable()->constrained('myb_governance_nodes')->nullOnDelete();
            $table->string('fiscal_year', 20)->nullable();
            $table->decimal('planned_amount', 18, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->longText('description')->nullable();
            $table->longText('expected_outputs')->nullable();
            $table->longText('implementation_notes')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approved_work_plans');
    }
};
