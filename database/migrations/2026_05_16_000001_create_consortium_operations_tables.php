<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attp_consortia', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignUuid('lead_applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignUuid('lead_think_dataset_id')->nullable()->constrained('think_datasets')->nullOnDelete();
            $table->foreignUuid('program_funding_id')->nullable()->constrained('myb_program_fundings')->nullOnDelete();
            $table->foreignUuid('funder_id')->nullable()->constrained('myb_funders')->nullOnDelete();
            $table->foreignUuid('secretariat_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->json('covered_countries')->nullable();
            $table->decimal('approved_budget', 18, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->text('mandate')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attp_consortium_think_tanks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consortium_id')->constrained('attp_consortia')->cascadeOnDelete();
            $table->foreignUuid('think_dataset_id')->nullable()->constrained('think_datasets')->nullOnDelete();
            $table->foreignUuid('applicant_id')->nullable()->constrained('applicants')->nullOnDelete();
            $table->foreignUuid('portal_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('country')->nullable();
            $table->string('email')->nullable();
            $table->string('role', 30)->default('member');
            $table->decimal('budget_allocated', 18, 2)->default(0);
            $table->string('status', 30)->default('active')->index();
            $table->date('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['consortium_id', 'think_dataset_id'], 'attp_consortium_think_dataset_unique');
        });

        Schema::create('attp_workplans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consortium_id')->constrained('attp_consortia')->cascadeOnDelete();
            $table->foreignUuid('program_funding_id')->nullable()->constrained('myb_program_fundings')->nullOnDelete();
            $table->string('title');
            $table->string('period_label')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('planned_budget', 18, 2)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->text('objectives')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attp_fund_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consortium_id')->constrained('attp_consortia')->cascadeOnDelete();
            $table->foreignUuid('think_tank_member_id')->nullable()->constrained('attp_consortium_think_tanks')->nullOnDelete();
            $table->foreignUuid('program_funding_id')->nullable()->constrained('myb_program_fundings')->nullOnDelete();
            $table->string('budget_line');
            $table->string('currency', 10)->default('USD');
            $table->decimal('amount_allocated', 18, 2)->default(0);
            $table->decimal('amount_committed', 18, 2)->default(0);
            $table->decimal('amount_disbursed', 18, 2)->default(0);
            $table->decimal('amount_spent', 18, 2)->default(0);
            $table->string('status', 30)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attp_activity_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consortium_id')->constrained('attp_consortia')->cascadeOnDelete();
            $table->foreignUuid('think_tank_member_id')->nullable()->constrained('attp_consortium_think_tanks')->nullOnDelete();
            $table->foreignUuid('workplan_id')->nullable()->constrained('attp_workplans')->nullOnDelete();
            $table->foreignUuid('activity_id')->nullable()->constrained('myb_activities')->nullOnDelete();
            $table->foreignUuid('sub_activity_id')->nullable()->constrained('myb_sub_activities')->nullOnDelete();
            $table->string('title');
            $table->date('reporting_period_start')->nullable();
            $table->date('reporting_period_end')->nullable();
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->decimal('funds_spent', 18, 2)->default(0);
            $table->string('status', 30)->default('draft')->index();
            $table->longText('summary')->nullable();
            $table->longText('achievements')->nullable();
            $table->longText('challenges')->nullable();
            $table->longText('next_steps')->nullable();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attp_report_evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_report_id')->constrained('attp_activity_reports')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('evidence_type', 40)->default('document');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->text('external_url')->nullable();
            $table->string('visibility', 30)->default('secretariat_partner');
            $table->timestamps();
        });

        Schema::create('attp_disbursement_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consortium_id')->constrained('attp_consortia')->cascadeOnDelete();
            $table->foreignUuid('think_tank_member_id')->nullable()->constrained('attp_consortium_think_tanks')->nullOnDelete();
            $table->foreignUuid('fund_allocation_id')->nullable()->constrained('attp_fund_allocations')->nullOnDelete();
            $table->string('request_code')->unique();
            $table->decimal('amount_requested', 18, 2);
            $table->decimal('amount_approved', 18, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->string('status', 30)->default('submitted')->index();
            $table->text('purpose')->nullable();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attp_expense_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consortium_id')->constrained('attp_consortia')->cascadeOnDelete();
            $table->foreignUuid('think_tank_member_id')->nullable()->constrained('attp_consortium_think_tanks')->nullOnDelete();
            $table->foreignUuid('activity_report_id')->nullable()->constrained('attp_activity_reports')->nullOnDelete();
            $table->foreignUuid('fund_allocation_id')->nullable()->constrained('attp_fund_allocations')->nullOnDelete();
            $table->foreignUuid('disbursement_request_id')->nullable()->constrained('attp_disbursement_requests')->nullOnDelete();
            $table->string('expense_code')->unique();
            $table->string('description');
            $table->string('vendor_name')->nullable();
            $table->date('expense_date')->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('currency', 10)->default('USD');
            $table->string('receipt_path')->nullable();
            $table->string('status', 30)->default('submitted')->index();
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attp_risk_flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consortium_id')->constrained('attp_consortia')->cascadeOnDelete();
            $table->foreignUuid('think_tank_member_id')->nullable()->constrained('attp_consortium_think_tanks')->nullOnDelete();
            $table->foreignUuid('activity_report_id')->nullable()->constrained('attp_activity_reports')->nullOnDelete();
            $table->string('title');
            $table->string('category', 40)->default('operational');
            $table->string('severity', 20)->default('medium')->index();
            $table->string('status', 30)->default('open')->index();
            $table->text('description')->nullable();
            $table->text('mitigation_plan')->nullable();
            $table->foreignUuid('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attp_risk_flags');
        Schema::dropIfExists('attp_expense_reports');
        Schema::dropIfExists('attp_disbursement_requests');
        Schema::dropIfExists('attp_report_evidence');
        Schema::dropIfExists('attp_activity_reports');
        Schema::dropIfExists('attp_fund_allocations');
        Schema::dropIfExists('attp_workplans');
        Schema::dropIfExists('attp_consortium_think_tanks');
        Schema::dropIfExists('attp_consortia');
    }
};
