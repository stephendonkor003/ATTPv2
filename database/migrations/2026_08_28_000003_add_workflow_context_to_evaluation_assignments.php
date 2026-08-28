<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_assignments', function (Blueprint $table): void {
            $table->string('workflow_stage', 40)
                ->default('application')
                ->after('form_submission_id');
            $table->foreignUuid('technical_proposal_round_id')
                ->nullable()
                ->after('workflow_stage');

            $table->foreign('technical_proposal_round_id', 'eval_assignment_tp_round_fk')
                ->references('id')
                ->on('eoi_technical_proposal_rounds')
                ->nullOnDelete();
            $table->index(['procurement_id', 'workflow_stage'], 'eval_assignment_proc_stage_idx');
            $table->index('technical_proposal_round_id', 'eval_assignment_tp_round_idx');
        });

        Schema::table('evaluation_submissions', function (Blueprint $table): void {
            $table->foreignUuid('evaluation_assignment_id')
                ->nullable()
                ->after('id');
            $table->foreignUuid('technical_proposal_candidate_id')
                ->nullable()
                ->after('form_submission_id');
            $table->foreignUuid('technical_proposal_submission_id')
                ->nullable()
                ->after('technical_proposal_candidate_id');

            $table->foreign('evaluation_assignment_id', 'eval_submission_assignment_fk')
                ->references('id')
                ->on('evaluation_assignments')
                ->nullOnDelete();
            $table->foreign('technical_proposal_candidate_id', 'eval_submission_tp_candidate_fk')
                ->references('id')
                ->on('eoi_technical_proposal_candidates')
                ->nullOnDelete();
            $table->foreign('technical_proposal_submission_id', 'eval_submission_tp_source_fk')
                ->references('id')
                ->on('eoi_technical_proposal_submissions')
                ->nullOnDelete();

            $table->index('evaluation_assignment_id', 'eval_submission_assignment_idx');
            $table->index('technical_proposal_candidate_id', 'eval_submission_tp_candidate_idx');
            $table->index('technical_proposal_submission_id', 'eval_submission_tp_source_idx');
            $table->index(
                ['evaluation_assignment_id', 'form_submission_id'],
                'eval_submission_assignment_applicant_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_submissions', function (Blueprint $table): void {
            $table->dropForeign('eval_submission_assignment_fk');
            $table->dropForeign('eval_submission_tp_candidate_fk');
            $table->dropForeign('eval_submission_tp_source_fk');
            $table->dropIndex('eval_submission_assignment_idx');
            $table->dropIndex('eval_submission_tp_candidate_idx');
            $table->dropIndex('eval_submission_tp_source_idx');
            $table->dropIndex('eval_submission_assignment_applicant_idx');
            $table->dropColumn([
                'evaluation_assignment_id',
                'technical_proposal_candidate_id',
                'technical_proposal_submission_id',
            ]);
        });

        Schema::table('evaluation_assignments', function (Blueprint $table): void {
            $table->dropForeign('eval_assignment_tp_round_fk');
            $table->dropIndex('eval_assignment_proc_stage_idx');
            $table->dropIndex('eval_assignment_tp_round_idx');
            $table->dropColumn([
                'workflow_stage',
                'technical_proposal_round_id',
            ]);
        });
    }
};
