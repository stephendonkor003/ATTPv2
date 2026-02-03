<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('think_tank_name')->nullable();
            $table->string('country')->nullable();
            $table->string('sub_region')->nullable();
            $table->string('focus_areas')->nullable();
            $table->boolean('is_partnership')->nullable();
            $table->string('email')->nullable();
            $table->string('members_names')->nullable();
            $table->string('consortium_name')->nullable();
            $table->string('lead_think_tank_name')->nullable();
            $table->string('lead_think_tank_country')->nullable();
            $table->string('consortium_region')->nullable();
            $table->string('covered_countries')->nullable();
            $table->string('application_form')->nullable();
            $table->string('legal_registration')->nullable();
            $table->string('trustees_formation')->nullable();
            $table->string('audited_reports')->nullable();
            $table->string('commitment_letter')->nullable();
            $table->string('work_plan_budget')->nullable();
            $table->string('cv_coordinator')->nullable();
            $table->string('cv_deputy')->nullable();
            $table->string('cv_team_members')->nullable();
            $table->string('past_research')->nullable();
            $table->string('code')->nullable();
            $table->timestamps();
        });
        Schema::create('assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('applicant_id')->nullable();
            $table->foreignUuid('evaluator_id')->nullable();
            $table->string('evaluator_ids')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });
        Schema::create('bids', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->nullable();
            $table->foreignUuid('user_id')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->text('proposal')->nullable();
            $table->timestamps();
        });
        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('allocatable_id')->nullable();
            $table->string('allocatable_type')->nullable();
            $table->integer('year_number')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('committee_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('committee_id')->nullable();
            $table->foreignUuid('user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('committees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->foreignUuid('project_id')->nullable();
            $table->foreignUuid('chairperson_id')->nullable();
            $table->timestamps();
        });
        Schema::create('dynamic_form_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')->nullable();
            $table->string('label')->nullable();
            $table->string('field_key')->nullable();
            $table->string('field_type')->nullable();
            $table->boolean('is_required')->nullable();
            $table->string('options')->nullable();
            $table->string('sort_order')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('dynamic_forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('resource_id')->nullable();
            $table->string('name')->nullable();
            $table->string('applies_to')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_active')->nullable();
            $table->string('created_by')->nullable();
            $table->foreignUuid('procurement_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('approved_by')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('evaluation_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('evaluation_id')->nullable();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('user_id')->nullable();
            $table->string('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('evaluation_section_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('max_score', 15, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('evaluation_criteria_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->nullable();
            $table->foreignUuid('evaluation_criteria_id')->nullable();
            $table->decimal('score', 15, 2)->nullable();
            $table->string('decision')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();
        });
        Schema::create('evaluation_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->nullable();
            $table->foreignUuid('form_id')->nullable();
            $table->string('field_key')->nullable();
            $table->foreignUuid('evaluator_id')->nullable();
            $table->decimal('score', 15, 2)->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
        });
        Schema::create('evaluation_section_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->nullable();
            $table->foreignUuid('evaluation_section_id')->nullable();
            $table->decimal('section_score', 15, 2)->nullable();
            $table->text('strengths')->nullable();
            $table->string('weaknesses')->nullable();
            $table->timestamps();
        });
        Schema::create('evaluation_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('evaluation_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('evaluation_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('evaluation_id')->nullable();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('evaluator_id')->nullable();
            $table->foreignUuid('form_submission_id')->nullable();
            $table->decimal('overall_score', 15, 2)->nullable();
            $table->text('comments')->nullable();
            $table->string('video_path')->nullable();
            $table->string('video_duration')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->string('type')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('evaluator_teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->foreignUuid('leader_id')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('financial_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('applicant_id')->nullable();
            $table->foreignUuid('evaluator_id')->nullable();
            $table->text('strength_financial_health')->nullable();
            $table->text('gap_financial_health')->nullable();
            $table->text('strength_accuracy')->nullable();
            $table->text('gap_accuracy')->nullable();
            $table->text('strength_revenue')->nullable();
            $table->text('gap_revenue')->nullable();
            $table->text('strength_fund_use')->nullable();
            $table->text('gap_fund_use')->nullable();
            $table->text('strength_liabilities')->nullable();
            $table->text('gap_liabilities')->nullable();
            $table->text('strength_compliance')->nullable();
            $table->text('gap_compliance')->nullable();
            $table->text('overall_financial_assessment')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('form_submission_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->nullable();
            $table->string('field_key')->nullable();
            $table->string('value')->nullable();
            $table->timestamps();
        });
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')->nullable();
            $table->string('procurement_submission_code')->nullable();
            $table->foreignUuid('form_id')->nullable();
            $table->string('submitted_by')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('geo_regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('continent')->nullable();
            $table->string('sub_region')->nullable();
            $table->string('country')->nullable();
            $table->string('region_group')->nullable();
            $table->timestamps();
        });
        Schema::create('hr_applicants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('vacancy_id')->nullable();
            $table->string('full_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('gender')->nullable();
            $table->string('nationality')->nullable();
            $table->string('cv_path')->nullable();
            $table->string('cover_letter_path')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('submitted_at')->nullable();
        });
        Schema::create('hr_employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('applicant_id')->nullable();
            $table->foreignUuid('position_id')->nullable();
            $table->string('employee_code')->nullable();
            $table->date('employment_start_date')->nullable();
            $table->date('employment_end_date')->nullable();
            $table->string('contract_type')->nullable();
            $table->string('salary')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('hr_positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('resource_id')->nullable();
            $table->foreignUuid('department_id')->nullable();
            $table->string('title')->nullable();
            $table->string('employment_type')->nullable();
            $table->string('grade_level')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('hr_vacancies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('position_id')->nullable();
            $table->string('vacancy_code')->nullable();
            $table->date('open_date')->nullable();
            $table->date('close_date')->nullable();
            $table->string('number_of_positions')->nullable();
            $table->boolean('is_public')->nullable();
            $table->string('status')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('myb_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('myb_activity_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->nullable();
            $table->integer('year')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('myb_budget_commitments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_funding_id')->nullable();
            $table->decimal('allocation_level', 15, 2)->nullable();
            $table->foreignUuid('allocation_id')->nullable();
            $table->foreignUuid('resource_category_id')->nullable();
            $table->foreignUuid('resource_id')->nullable();
            $table->decimal('commitment_amount', 15, 2)->nullable();
            $table->integer('commitment_year')->nullable();
            $table->string('status')->nullable();
            $table->string('created_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
        });
        Schema::create('myb_departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->foreignUuid('head_user_id')->nullable();
            $table->string('status')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('myb_funders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('type')->nullable();
            $table->string('currency')->nullable();
            $table->timestamps();
        });
        Schema::create('myb_program_funding_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_funding_id')->nullable();
            $table->string('document_type')->nullable();
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('uploaded_by')->nullable();
            $table->timestamps();
        });
        Schema::create('myb_program_fundings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('department_id')->nullable();
            $table->foreignUuid('program_id')->nullable();
            $table->foreignUuid('funder_id')->nullable();
            $table->decimal('funding_type', 15, 2)->nullable();
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->string('currency')->nullable();
            $table->integer('start_year')->nullable();
            $table->integer('end_year')->nullable();
            $table->string('status')->nullable();
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('myb_programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->nullable();
            $table->foreignUuid('sector_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('currency')->nullable();
            $table->integer('start_year')->nullable();
            $table->integer('end_year')->nullable();
            $table->integer('total_years')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('myb_project_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->nullable();
            $table->integer('year')->nullable();
            $table->integer('year_number')->nullable();
            $table->integer('actual_year')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
        });
        Schema::create('myb_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('program_id')->nullable();
            $table->foreignUuid('project_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('currency')->nullable();
            $table->integer('start_year')->nullable();
            $table->integer('end_year')->nullable();
            $table->integer('total_years')->nullable();
            $table->decimal('total_budget', 15, 2)->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('myb_resource_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('myb_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('resource_category_id')->nullable();
            $table->string('name')->nullable();
            $table->string('reference_code')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_human_resource')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('myb_sectors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('myb_sub_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('myb_sub_activity_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sub_activity_id')->nullable();
            $table->integer('year')->nullable();
            $table->decimal('amount', 15, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('module')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('prescreening_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('user_id')->nullable();
            $table->string('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
        });
        Schema::create('prescreening_criteria', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prescreening_template_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('field_key')->nullable();
            $table->string('evaluation_type')->nullable();
            $table->string('min_value')->nullable();
            $table->boolean('is_mandatory')->nullable();
            $table->string('sort_order')->nullable();
            $table->timestamps();
        });
        Schema::create('prescreening_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->nullable();
            $table->foreignUuid('prescreening_template_id')->nullable();
            $table->foreignUuid('criterion_id')->nullable();
            $table->foreignUuid('evaluator_id')->nullable();
            $table->string('evaluation_value')->nullable();
            $table->boolean('is_passed')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('evaluated_at')->nullable();
        });
        Schema::create('prescreening_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->nullable();
            $table->foreignUuid('prescreening_template_id')->nullable();
            $table->decimal('total_criteria', 15, 2)->nullable();
            $table->string('passed_criteria')->nullable();
            $table->string('failed_criteria')->nullable();
            $table->string('final_status')->nullable();
            $table->string('evaluated_by')->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->boolean('is_locked')->nullable();
            $table->string('rework_requested_by')->nullable();
            $table->timestamp('rework_requested_at')->nullable();
        });
        Schema::create('prescreening_template_procurements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('prescreening_template_id')->nullable();
            $table->string('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
        });
        Schema::create('prescreening_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('procurement_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable();
            $table->string('action')->nullable();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('form_id')->nullable();
            $table->foreignUuid('submission_id')->nullable();
            $table->string('metadata')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('procurement_form_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('form_id')->nullable();
            $table->string('stage')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('procurement_form_maps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('form_id')->nullable();
            $table->string('stage')->nullable();
            $table->timestamps();
        });
        Schema::create('procurement_user_permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('form_id')->nullable();
            $table->string('stage')->nullable();
            $table->string('permission')->nullable();
            $table->string('assigned_by')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });
        Schema::create('applicant_user', function (Blueprint $table) {
            // Pivot table
            $table->foreignUuid('applicant_id')->nullable();
            $table->foreignUuid('user_id')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });
        Schema::create('procurements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('resource_id')->nullable();
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->string('reference_no')->nullable();
            $table->text('description')->nullable();
            $table->integer('fiscal_year')->nullable();
            $table->decimal('estimated_budget', 15, 2)->nullable();
            $table->string('status')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('program_budget_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->nullable();
            $table->foreignUuid('activity_id')->nullable();
            $table->foreignUuid('sub_activity_id')->nullable();
            $table->integer('year')->nullable();
            $table->decimal('allocated_amount', 15, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('rework_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('evaluation_id')->nullable();
            $table->foreignUuid('evaluator_id')->nullable();
            $table->string('message')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('user_permission', function (Blueprint $table) {
            // Pivot table
            $table->foreignUuid('user_id')->nullable();
            $table->foreignUuid('permission_id')->nullable();
        });
        Schema::create('role_permission', function (Blueprint $table) {
            // Pivot table
            $table->foreignUuid('role_id')->nullable();
            $table->foreignUuid('permission_id')->nullable();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('site_visit_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_visit_id')->nullable();
            $table->foreignUuid('reviewer_id')->nullable();
            $table->string('status')->nullable();
            $table->text('remarks')->nullable();
        });
        Schema::create('site_visit_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_visit_id')->nullable();
            $table->foreignUuid('user_id')->nullable();
        });
        Schema::create('site_visit_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consortium_id')->nullable();
            $table->foreignUuid('team_id')->nullable();
            $table->foreignUuid('leader_id')->nullable();
            $table->foreignUuid('evaluator_id')->nullable();
            $table->date('evaluation_date')->nullable();
            $table->decimal('s1_1_score', 15, 2)->nullable();
            $table->text('s1_1_strength')->nullable();
            $table->string('s1_1_weakness')->nullable();
            $table->decimal('s1_2_score', 15, 2)->nullable();
            $table->text('s1_2_strength')->nullable();
            $table->string('s1_2_weakness')->nullable();
            $table->decimal('s1_3_score', 15, 2)->nullable();
            $table->text('s1_3_strength')->nullable();
            $table->string('s1_3_weakness')->nullable();
            $table->decimal('s1_4_score', 15, 2)->nullable();
            $table->text('s1_4_strength')->nullable();
            $table->string('s1_4_weakness')->nullable();
            $table->text('s1_comments')->nullable();
            $table->decimal('s2_1_score', 15, 2)->nullable();
            $table->text('s2_1_strength')->nullable();
            $table->string('s2_1_weakness')->nullable();
            $table->decimal('s2_2_score', 15, 2)->nullable();
            $table->text('s2_2_strength')->nullable();
            $table->string('s2_2_weakness')->nullable();
            $table->decimal('s2_3_score', 15, 2)->nullable();
            $table->text('s2_3_strength')->nullable();
            $table->string('s2_3_weakness')->nullable();
            $table->text('s2_comments')->nullable();
            $table->decimal('s3_1_score', 15, 2)->nullable();
            $table->text('s3_1_strength')->nullable();
            $table->string('s3_1_weakness')->nullable();
            $table->decimal('s3_2_score', 15, 2)->nullable();
            $table->text('s3_2_strength')->nullable();
            $table->string('s3_2_weakness')->nullable();
            $table->decimal('s3_3_score', 15, 2)->nullable();
            $table->text('s3_3_strength')->nullable();
            $table->string('s3_3_weakness')->nullable();
            $table->text('s3_comments')->nullable();
            $table->decimal('s4_1_score', 15, 2)->nullable();
            $table->text('s4_1_strength')->nullable();
            $table->string('s4_1_weakness')->nullable();
            $table->decimal('s4_2_score', 15, 2)->nullable();
            $table->text('s4_2_strength')->nullable();
            $table->string('s4_2_weakness')->nullable();
            $table->decimal('s4_3_score', 15, 2)->nullable();
            $table->text('s4_3_strength')->nullable();
            $table->string('s4_3_weakness')->nullable();
            $table->text('s4_comments')->nullable();
            $table->decimal('s5_1_score', 15, 2)->nullable();
            $table->text('s5_1_strength')->nullable();
            $table->string('s5_1_weakness')->nullable();
            $table->decimal('s5_2_score', 15, 2)->nullable();
            $table->text('s5_2_strength')->nullable();
            $table->string('s5_2_weakness')->nullable();
            $table->decimal('s5_3_score', 15, 2)->nullable();
            $table->text('s5_3_strength')->nullable();
            $table->string('s5_3_weakness')->nullable();
            $table->text('s5_comments')->nullable();
            $table->decimal('s6_1_score', 15, 2)->nullable();
            $table->text('s6_1_strength')->nullable();
            $table->string('s6_1_weakness')->nullable();
            $table->decimal('s6_2_score', 15, 2)->nullable();
            $table->text('s6_2_strength')->nullable();
            $table->string('s6_2_weakness')->nullable();
            $table->decimal('s6_3_score', 15, 2)->nullable();
            $table->text('s6_3_strength')->nullable();
            $table->string('s6_3_weakness')->nullable();
            $table->text('s6_comments')->nullable();
            $table->decimal('total_score', 15, 2)->nullable();
            $table->text('general_observations')->nullable();
            $table->text('overall_strength')->nullable();
            $table->string('overall_weakness')->nullable();
            $table->text('additional_comments')->nullable();
            $table->string('evaluator_name')->nullable();
            $table->string('evaluator_signature')->nullable();
            $table->string('rework_status')->nullable();
            $table->text('rework_comment')->nullable();
            $table->string('rework_requested_by')->nullable();
            $table->string('rework_completed_by')->nullable();
            $table->timestamps();
        });
        Schema::create('site_visit_group_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->nullable();
            $table->foreignUuid('user_id')->nullable();
            $table->string('role')->nullable();
        });
        Schema::create('site_visit_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_visit_id')->nullable();
            $table->string('group_name')->nullable();
            $table->foreignUuid('leader_id')->nullable();
        });
        Schema::create('site_visit_medias', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_visit_id')->nullable();
            $table->foreignUuid('observation_id')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type')->nullable();
            $table->string('uploaded_by')->nullable();
        });
        Schema::create('site_visit_observations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_visit_id')->nullable();
            $table->string('category')->nullable();
            $table->text('description')->nullable();
            $table->string('severity')->nullable();
            $table->string('action_required')->nullable();
        });
        Schema::create('site_visits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')->nullable();
            $table->foreignUuid('form_submission_id')->nullable();
            $table->string('assignment_type')->nullable();
            $table->string('visit_type')->nullable();
            $table->date('visit_date')->nullable();
            $table->string('status')->nullable();
            $table->string('created_by')->nullable();
            $table->string('assigned_by')->nullable();
            $table->timestamps();
        });
        Schema::create('team_consortiums', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->nullable();
            $table->foreignUuid('consortium_id')->nullable();
            $table->string('assigned_by')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('team_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->nullable();
            $table->foreignUuid('user_id')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });
        Schema::create('think_datasets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ottd_id')->nullable();
            $table->string('tt_name_en')->nullable();
            $table->string('country')->nullable();
            $table->string('continent')->nullable();
            $table->string('sub_region')->nullable();
            $table->string('Count')->nullable();
            $table->string('website')->nullable();
            $table->string('g_email')->nullable();
            $table->string('operating_langs')->nullable();
            $table->string('tt_init')->nullable();
            $table->text('description')->nullable();
            $table->string('main_city')->nullable();
            $table->string('Region_group')->nullable();
            $table->string('other_offices')->nullable();
            $table->string('address')->nullable();
            $table->string('tt_business_model')->nullable();
            $table->string('Funding_sources')->nullable();
            $table->string('Funding_Mechanism')->nullable();
            $table->string('tt_affiliations')->nullable();
            $table->string('topics')->nullable();
            $table->string('geographies')->nullable();
            $table->date('date_founded')->nullable();
            $table->string('Date_founded_groups')->nullable();
            $table->string('founder')->nullable();
            $table->string('founder_gender')->nullable();
            $table->string('founder_other_type')->nullable();
            $table->string('staff_no')->nullable();
            $table->string('pc_staff_female')->nullable();
            $table->string('pc_res_staff_female')->nullable();
            $table->string('assc_no')->nullable();
            $table->string('assc_female_no')->nullable();
            $table->string('pub_no')->nullable();
            $table->string('fin_usd')->nullable();
            $table->string('twitter_handle_link')->nullable();
            $table->string('facebook_page')->nullable();
            $table->string('youtube_page')->nullable();
            $table->string('instagram_acc')->nullable();
            $table->string('linkedIn_acc')->nullable();
            $table->boolean('is_validated')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('user_type')->nullable();
            $table->boolean('must_change_password')->nullable();
            $table->foreignUuid('role_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('think_datasets');
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('team_consortiums');
        Schema::dropIfExists('site_visits');
        Schema::dropIfExists('site_visit_observations');
        Schema::dropIfExists('site_visit_medias');
        Schema::dropIfExists('site_visit_groups');
        Schema::dropIfExists('site_visit_group_members');
        Schema::dropIfExists('site_visit_evaluations');
        Schema::dropIfExists('site_visit_assignments');
        Schema::dropIfExists('site_visit_approvals');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('user_permission');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('rework_requests');
        Schema::dropIfExists('program_budget_allocations');
        Schema::dropIfExists('procurements');
        Schema::dropIfExists('procurement_user_permissions');
        Schema::dropIfExists('applicant_user');
        Schema::dropIfExists('procurement_form_maps');
        Schema::dropIfExists('procurement_form_assignments');
        Schema::dropIfExists('procurement_audit_logs');
        Schema::dropIfExists('prescreening_templates');
        Schema::dropIfExists('prescreening_template_procurements');
        Schema::dropIfExists('prescreening_results');
        Schema::dropIfExists('prescreening_evaluations');
        Schema::dropIfExists('prescreening_criteria');
        Schema::dropIfExists('prescreening_assignments');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('myb_sub_activity_allocations');
        Schema::dropIfExists('myb_sub_activities');
        Schema::dropIfExists('myb_sectors');
        Schema::dropIfExists('myb_resources');
        Schema::dropIfExists('myb_resource_categories');
        Schema::dropIfExists('myb_projects');
        Schema::dropIfExists('myb_project_allocations');
        Schema::dropIfExists('myb_programs');
        Schema::dropIfExists('myb_program_fundings');
        Schema::dropIfExists('myb_program_funding_documents');
        Schema::dropIfExists('myb_funders');
        Schema::dropIfExists('myb_departments');
        Schema::dropIfExists('myb_budget_commitments');
        Schema::dropIfExists('myb_activity_allocations');
        Schema::dropIfExists('myb_activities');
        Schema::dropIfExists('hr_vacancies');
        Schema::dropIfExists('hr_positions');
        Schema::dropIfExists('hr_employees');
        Schema::dropIfExists('hr_applicants');
        Schema::dropIfExists('geo_regions');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_submission_values');
        Schema::dropIfExists('financial_evaluations');
        Schema::dropIfExists('evaluator_teams');
        Schema::dropIfExists('evaluations');
        Schema::dropIfExists('evaluation_submissions');
        Schema::dropIfExists('evaluation_sections');
        Schema::dropIfExists('evaluation_section_scores');
        Schema::dropIfExists('evaluation_results');
        Schema::dropIfExists('evaluation_criteria_scores');
        Schema::dropIfExists('evaluation_criteria');
        Schema::dropIfExists('evaluation_assignments');
        Schema::dropIfExists('dynamic_forms');
        Schema::dropIfExists('dynamic_form_fields');
        Schema::dropIfExists('committees');
        Schema::dropIfExists('committee_members');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('budget_allocations');
        Schema::dropIfExists('bids');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('applicants');
    }
};
