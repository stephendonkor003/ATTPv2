<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eoi_technical_proposal_rounds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id');
            $table->unsignedSmallInteger('round_number')->default(1);
            $table->string('title', 180);
            $table->longText('instructions')->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('deadline_at')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('late_policy', 30)->default('reject');
            $table->string('portal_requirement', 20)->default('required');
            $table->string('email_requirement', 20)->default('allowed');
            $table->string('physical_requirement', 20)->default('not_allowed');
            $table->string('status', 20)->default('draft');
            $table->foreignUuid('created_by')->nullable();
            $table->foreignUuid('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('procurement_id', 'eoi_tp_round_procurement_fk')
                ->references('id')->on('procurements')->cascadeOnDelete();
            $table->foreign('created_by', 'eoi_tp_round_creator_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('published_by', 'eoi_tp_round_publisher_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->unique(['procurement_id', 'round_number'], 'eoi_tp_round_procurement_number_uq');
            $table->index(['procurement_id', 'status'], 'eoi_tp_round_procurement_status_idx');
        });

        Schema::create('eoi_technical_proposal_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('round_id');
            $table->string('code', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 60)->default('general');
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_disqualifying')->default(false);
            $table->boolean('requires_acknowledgement')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('round_id', 'eoi_tp_rule_round_fk')
                ->references('id')->on('eoi_technical_proposal_rounds')->cascadeOnDelete();
            $table->foreign('created_by', 'eoi_tp_rule_creator_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->unique(['round_id', 'code'], 'eoi_tp_rule_round_code_uq');
            $table->index(['round_id', 'sort_order'], 'eoi_tp_rule_round_sort_idx');
        });

        Schema::create('eoi_technical_proposal_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('round_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('extension', 20);
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->foreignUuid('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('round_id', 'eoi_tp_template_round_fk')
                ->references('id')->on('eoi_technical_proposal_rounds')->cascadeOnDelete();
            $table->foreign('uploaded_by', 'eoi_tp_template_uploader_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index(['round_id', 'sort_order'], 'eoi_tp_template_round_sort_idx');
            $table->index(['round_id', 'sha256'], 'eoi_tp_template_round_hash_idx');
        });

        Schema::create('eoi_technical_proposal_candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('round_id');
            $table->foreignUuid('form_submission_id');
            $table->foreignUuid('user_id')->nullable();
            $table->string('eoi_outcome_code', 40);
            $table->string('eoi_outcome_label', 80);
            $table->string('workflow_decision', 120);
            $table->string('status', 30)->default('invited');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('first_submitted_at')->nullable();
            $table->timestamp('last_submitted_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('round_id', 'eoi_tp_candidate_round_fk')
                ->references('id')->on('eoi_technical_proposal_rounds')->cascadeOnDelete();
            $table->foreign('form_submission_id', 'eoi_tp_candidate_application_fk')
                ->references('id')->on('form_submissions')->cascadeOnDelete();
            $table->foreign('user_id', 'eoi_tp_candidate_user_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by', 'eoi_tp_candidate_reviewer_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->unique(['round_id', 'form_submission_id'], 'eoi_tp_candidate_round_application_uq');
            $table->index(['user_id', 'status'], 'eoi_tp_candidate_user_status_idx');
            $table->index(['round_id', 'status'], 'eoi_tp_candidate_round_status_idx');
        });

        Schema::create('eoi_technical_proposal_submissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('candidate_id');
            $table->unsignedInteger('revision_number');
            $table->string('source', 30);
            $table->string('received_via', 30);
            $table->timestamp('received_at');
            $table->timestamp('uploaded_at');
            $table->boolean('is_late')->default(false);
            $table->unsignedBigInteger('minutes_late')->default(0);
            $table->text('cover_note')->nullable();
            $table->text('capture_note')->nullable();
            $table->foreignUuid('submitted_by')->nullable();
            $table->foreignUuid('captured_by')->nullable();
            $table->timestamps();

            $table->foreign('candidate_id', 'eoi_tp_submission_candidate_fk')
                ->references('id')->on('eoi_technical_proposal_candidates')->cascadeOnDelete();
            $table->foreign('submitted_by', 'eoi_tp_submission_submitter_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('captured_by', 'eoi_tp_submission_capturer_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->unique(['candidate_id', 'revision_number'], 'eoi_tp_submission_candidate_revision_uq');
            $table->index(['candidate_id', 'received_at'], 'eoi_tp_submission_candidate_received_idx');
            $table->index(['source', 'received_via'], 'eoi_tp_submission_source_channel_idx');
        });

        Schema::create('eoi_technical_proposal_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('proposal_submission_id');
            $table->string('document_label')->nullable();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('extension', 20);
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('file_size');
            $table->string('sha256', 64);
            $table->foreignUuid('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('proposal_submission_id', 'eoi_tp_document_submission_fk')
                ->references('id')->on('eoi_technical_proposal_submissions')->cascadeOnDelete();
            $table->foreign('uploaded_by', 'eoi_tp_document_uploader_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index(['proposal_submission_id', 'created_at'], 'eoi_tp_document_submission_created_idx');
            $table->index(['proposal_submission_id', 'sha256'], 'eoi_tp_document_submission_hash_idx');
        });

        Schema::create('eoi_technical_proposal_rule_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('candidate_id');
            $table->foreignUuid('rule_id');
            $table->foreignUuid('proposal_submission_id')->nullable();
            $table->string('rule_code_snapshot', 40);
            $table->string('rule_title_snapshot');
            $table->boolean('rule_is_disqualifying_snapshot')->default(false);
            $table->string('finding', 30);
            $table->string('effect', 20)->default('none');
            $table->text('rationale')->nullable();
            $table->foreignUuid('applied_by')->nullable();
            $table->timestamp('applied_at');
            $table->foreignUuid('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->text('revocation_reason')->nullable();
            $table->timestamps();

            $table->foreign('candidate_id', 'eoi_tp_finding_candidate_fk')
                ->references('id')->on('eoi_technical_proposal_candidates')->cascadeOnDelete();
            $table->foreign('rule_id', 'eoi_tp_finding_rule_fk')
                ->references('id')->on('eoi_technical_proposal_rules')->cascadeOnDelete();
            $table->foreign('proposal_submission_id', 'eoi_tp_finding_submission_fk')
                ->references('id')->on('eoi_technical_proposal_submissions')->nullOnDelete();
            $table->foreign('applied_by', 'eoi_tp_finding_applier_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('revoked_by', 'eoi_tp_finding_revoker_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index(['candidate_id', 'rule_id', 'revoked_at'], 'eoi_tp_finding_current_idx');
            $table->index(['candidate_id', 'effect', 'revoked_at'], 'eoi_tp_finding_effect_idx');
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX eoi_tp_finding_one_active_uq '
                .'ON eoi_technical_proposal_rule_applications (candidate_id, rule_id) '
                .'WHERE revoked_at IS NULL'
            );
        }

        Schema::table('eoi_report_communications', function (Blueprint $table): void {
            $table->foreignUuid('technical_proposal_round_id')->nullable();
            $table->foreign('technical_proposal_round_id', 'eoi_communication_tp_round_fk')
                ->references('id')->on('eoi_technical_proposal_rounds')->nullOnDelete();
            $table->index('technical_proposal_round_id', 'eoi_communication_tp_round_idx');
        });
    }

    public function down(): void
    {
        Schema::table('eoi_report_communications', function (Blueprint $table): void {
            $table->dropForeign('eoi_communication_tp_round_fk');
            $table->dropIndex('eoi_communication_tp_round_idx');
            $table->dropColumn('technical_proposal_round_id');
        });

        Schema::dropIfExists('eoi_technical_proposal_rule_applications');
        Schema::dropIfExists('eoi_technical_proposal_documents');
        Schema::dropIfExists('eoi_technical_proposal_submissions');
        Schema::dropIfExists('eoi_technical_proposal_candidates');
        Schema::dropIfExists('eoi_technical_proposal_templates');
        Schema::dropIfExists('eoi_technical_proposal_rules');
        Schema::dropIfExists('eoi_technical_proposal_rounds');
    }
};
