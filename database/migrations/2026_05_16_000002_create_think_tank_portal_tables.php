<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attp_think_tank_research_outputs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consortium_id')->constrained('attp_consortia')->cascadeOnDelete();
            $table->foreignUuid('think_tank_member_id')->constrained('attp_consortium_think_tanks')->cascadeOnDelete();
            $table->string('title');
            $table->string('output_type', 50)->default('research');
            $table->date('published_on')->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->longText('abstract')->nullable();
            $table->string('file_path')->nullable();
            $table->text('external_url')->nullable();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attp_think_tank_procurement_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consortium_id')->constrained('attp_consortia')->cascadeOnDelete();
            $table->foreignUuid('think_tank_member_id')->constrained('attp_consortium_think_tanks')->cascadeOnDelete();
            $table->string('plan_code')->unique();
            $table->string('title');
            $table->string('fiscal_year')->nullable();
            $table->decimal('estimated_budget', 18, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->date('planned_publish_date')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('description')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::table('procurements', function (Blueprint $table) {
            $table->foreignUuid('consortium_id')->nullable()->after('id')->constrained('attp_consortia')->nullOnDelete();
            $table->foreignUuid('think_tank_member_id')->nullable()->after('consortium_id')->constrained('attp_consortium_think_tanks')->nullOnDelete();
            $table->foreignUuid('think_tank_procurement_plan_id')->nullable()->after('think_tank_member_id')->constrained('attp_think_tank_procurement_plans')->nullOnDelete();
            $table->string('procurement_owner_type', 40)->default('secretariat')->after('think_tank_procurement_plan_id')->index();
            $table->string('oversight_status', 30)->default('visible')->after('procurement_owner_type')->index();
        });

        Schema::create('attp_think_tank_procurement_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')->constrained('procurements')->cascadeOnDelete();
            $table->foreignUuid('form_submission_id')->constrained('form_submissions')->cascadeOnDelete();
            $table->foreignUuid('think_tank_member_id')->constrained('attp_consortium_think_tanks')->cascadeOnDelete();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('technical_score', 8, 2)->default(0);
            $table->decimal('financial_score', 8, 2)->default(0);
            $table->decimal('total_score', 8, 2)->default(0);
            $table->string('recommendation', 30)->default('pending')->index();
            $table->text('comments')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['procurement_id', 'form_submission_id'], 'attp_tt_proc_review_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attp_think_tank_procurement_reviews');

        Schema::table('procurements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('think_tank_procurement_plan_id');
            $table->dropConstrainedForeignId('think_tank_member_id');
            $table->dropConstrainedForeignId('consortium_id');
            $table->dropColumn(['procurement_owner_type', 'oversight_status']);
        });

        Schema::dropIfExists('attp_think_tank_procurement_plans');
        Schema::dropIfExists('attp_think_tank_research_outputs');
    }
};
