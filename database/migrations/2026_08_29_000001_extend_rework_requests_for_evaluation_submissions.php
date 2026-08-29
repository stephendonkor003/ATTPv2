<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_submissions', function (Blueprint $table): void {
            $table->string('workflow_status', 32)
                ->default('draft')
                ->after('submitted_at');
            $table->unsignedInteger('revision_number')
                ->default(0)
                ->after('workflow_status');
            $table->index('workflow_status', 'eval_submissions_workflow_status_idx');
        });

        DB::table('evaluation_submissions')
            ->whereNotNull('submitted_at')
            ->update([
                'workflow_status' => 'submitted',
                'revision_number' => 1,
            ]);

        Schema::table('rework_requests', function (Blueprint $table): void {
            $table->foreignUuid('evaluation_submission_id')->nullable()->after('id');
            $table->foreignUuid('evaluation_assignment_id')->nullable()->after('evaluation_submission_id');
            $table->foreignUuid('procurement_id')->nullable()->after('evaluation_assignment_id');
            $table->foreignUuid('form_submission_id')->nullable()->after('procurement_id');
            $table->foreignUuid('requested_by')->nullable()->after('evaluator_id');
            $table->foreignUuid('completed_by')->nullable()->after('requested_by');
            $table->unsignedInteger('cycle')->nullable()->after('completed_by');
            $table->text('reason')->nullable()->after('message');
            $table->unsignedSmallInteger('snapshot_schema_version')->default(1)->after('reason');
            $table->timestamp('requested_at')->nullable()->after('status');
            $table->timestamp('original_submitted_at')->nullable()->after('requested_at');
            $table->timestamp('completed_at')->nullable()->after('original_submitted_at');
            $table->unsignedInteger('source_revision_number')->nullable()->after('completed_at');
            $table->unsignedInteger('completed_revision_number')->nullable()->after('source_revision_number');
            $table->json('source_snapshot')->nullable()->after('completed_revision_number');
            $table->string('source_snapshot_hash', 64)->nullable()->after('source_snapshot');
            $table->json('completed_snapshot')->nullable()->after('source_snapshot_hash');
            $table->string('completed_snapshot_hash', 64)->nullable()->after('completed_snapshot');
            $table->timestamp('notified_at')->nullable()->after('completed_snapshot_hash');
            $table->text('notification_error')->nullable()->after('notified_at');

            $table->foreign('evaluation_submission_id', 'eval_rework_submission_fk')
                ->references('id')
                ->on('evaluation_submissions')
                ->restrictOnDelete();
            $table->foreign('evaluation_assignment_id', 'eval_rework_assignment_fk')
                ->references('id')
                ->on('evaluation_assignments')
                ->nullOnDelete();
            $table->foreign('procurement_id', 'eval_rework_procurement_fk')
                ->references('id')
                ->on('procurements')
                ->nullOnDelete();
            $table->foreign('form_submission_id', 'eval_rework_applicant_fk')
                ->references('id')
                ->on('form_submissions')
                ->nullOnDelete();
            $table->foreign('requested_by', 'eval_rework_requested_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->foreign('completed_by', 'eval_rework_completed_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unique(
                ['evaluation_submission_id', 'cycle'],
                'eval_rework_submission_cycle_unique'
            );
            $table->index(
                ['evaluation_submission_id', 'status'],
                'eval_rework_submission_status_idx'
            );
            $table->index(
                ['evaluation_assignment_id', 'status'],
                'eval_rework_assignment_status_idx'
            );
            $table->index(
                ['procurement_id', 'requested_at'],
                'eval_rework_procurement_requested_idx'
            );
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE UNIQUE INDEX eval_rework_one_pending_per_submission
                ON rework_requests (evaluation_submission_id)
                WHERE status = 'pending' AND evaluation_submission_id IS NOT NULL
            SQL);
        } elseif ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE rework_requests
                ADD COLUMN pending_evaluation_submission_id CHAR(36)
                    GENERATED ALWAYS AS (
                        CASE
                            WHEN status = 'pending' THEN evaluation_submission_id
                            ELSE NULL
                        END
                    ) STORED,
                ADD UNIQUE INDEX eval_rework_one_pending_per_submission
                    (pending_evaluation_submission_id)
            SQL);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('rework_requests', 'evaluation_submission_id')
            && DB::table('rework_requests')->whereNotNull('evaluation_submission_id')->exists()) {
            throw new RuntimeException(
                'Evaluation rework history exists. Export and resolve that audit history before rolling back this migration.'
            );
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS eval_rework_one_pending_per_submission');
        } elseif ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE rework_requests
                DROP INDEX eval_rework_one_pending_per_submission,
                DROP COLUMN pending_evaluation_submission_id
            SQL);
        }

        Schema::table('rework_requests', function (Blueprint $table): void {
            $table->dropForeign('eval_rework_submission_fk');
            $table->dropForeign('eval_rework_assignment_fk');
            $table->dropForeign('eval_rework_procurement_fk');
            $table->dropForeign('eval_rework_applicant_fk');
            $table->dropForeign('eval_rework_requested_by_fk');
            $table->dropForeign('eval_rework_completed_by_fk');
            $table->dropUnique('eval_rework_submission_cycle_unique');
            $table->dropIndex('eval_rework_submission_status_idx');
            $table->dropIndex('eval_rework_assignment_status_idx');
            $table->dropIndex('eval_rework_procurement_requested_idx');
            $table->dropColumn([
                'evaluation_submission_id',
                'evaluation_assignment_id',
                'procurement_id',
                'form_submission_id',
                'requested_by',
                'completed_by',
                'cycle',
                'reason',
                'snapshot_schema_version',
                'requested_at',
                'original_submitted_at',
                'completed_at',
                'source_revision_number',
                'completed_revision_number',
                'source_snapshot',
                'source_snapshot_hash',
                'completed_snapshot',
                'completed_snapshot_hash',
                'notified_at',
                'notification_error',
            ]);
        });

        Schema::table('evaluation_submissions', function (Blueprint $table): void {
            $table->dropIndex('eval_submissions_workflow_status_idx');
            $table->dropColumn(['workflow_status', 'revision_number']);
        });
    }
};
