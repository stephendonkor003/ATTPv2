<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('me_reporting_periods', function (Blueprint $table): void {
            if (! Schema::hasColumn('me_reporting_periods', 'reporting_year')) {
                $table->unsignedSmallInteger('reporting_year')->nullable();
            }
            if (! Schema::hasColumn('me_reporting_periods', 'submission_opens_at')) {
                $table->timestamp('submission_opens_at')->nullable();
            }
            if (! Schema::hasColumn('me_reporting_periods', 'submission_deadline')) {
                $table->timestamp('submission_deadline')->nullable();
            }
            if (! Schema::hasColumn('me_reporting_periods', 'review_deadline')) {
                $table->timestamp('review_deadline')->nullable();
            }
            if (! Schema::hasColumn('me_reporting_periods', 'lifecycle_status')) {
                $table->string('lifecycle_status', 24)->default('planned');
            }
            if (! Schema::hasColumn('me_reporting_periods', 'instructions')) {
                $table->text('instructions')->nullable();
            }
        });

        DB::table('me_reporting_periods')
            ->select(['id', 'period_end', 'status'])
            ->orderBy('id')
            ->each(function (object $period): void {
                DB::table('me_reporting_periods')->where('id', $period->id)->update([
                    'reporting_year' => $period->period_end
                        ? Carbon::parse($period->period_end)->year
                        : null,
                    'lifecycle_status' => match ((string) $period->status) {
                        'active' => 'open',
                        'closed' => 'closed',
                        default => 'planned',
                    },
                ]);
            });

        Schema::table('me_data_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('me_data_submissions', 'workflow_status')) {
                $table->string('workflow_status', 24)->default('draft');
            }
            if (! Schema::hasColumn('me_data_submissions', 'current_version')) {
                $table->unsignedInteger('current_version')->default(1);
            }
            if (! Schema::hasColumn('me_data_submissions', 'under_review_by')) {
                $table->foreignUuid('under_review_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('me_data_submissions', 'under_review_at')) {
                $table->timestamp('under_review_at')->nullable();
            }
            if (! Schema::hasColumn('me_data_submissions', 'verified_by')) {
                $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('me_data_submissions', 'verified_at')) {
                $table->timestamp('verified_at')->nullable();
            }
            if (! Schema::hasColumn('me_data_submissions', 'approved_by')) {
                $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('me_data_submissions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable();
            }
            if (! Schema::hasColumn('me_data_submissions', 'rejected_by')) {
                $table->foreignUuid('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('me_data_submissions', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable();
            }
        });

        DB::table('me_data_submissions')
            ->select(['id', 'status', 'revision'])
            ->orderBy('id')
            ->each(function (object $submission): void {
                DB::table('me_data_submissions')->where('id', $submission->id)->update([
                    'workflow_status' => $submission->status === 'validated'
                        ? 'verified'
                        : $submission->status,
                    'current_version' => max(1, (int) $submission->revision),
                ]);
            });

        if (! Schema::hasTable('me_data_submission_versions')) {
            Schema::create('me_data_submission_versions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('submission_id')->constrained('me_data_submissions')->cascadeOnDelete();
                $table->unsignedInteger('version');
                $table->string('status', 24);
                $table->json('schema_snapshot')->nullable();
                $table->json('answers_snapshot')->nullable();
                $table->json('evidence_snapshot')->nullable();
                $table->text('submitter_notes')->nullable();
                $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamps();

                $table->unique(['submission_id', 'version'], 'me_submission_version_unique');
            });
        }

        if (! Schema::hasTable('me_data_submission_reviews')) {
            Schema::create('me_data_submission_reviews', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('submission_id')->constrained('me_data_submissions')->cascadeOnDelete();
                $table->unsignedInteger('submission_version')->default(1);
                $table->string('from_status', 24)->nullable();
                $table->string('to_status', 24);
                $table->string('action', 40);
                $table->text('comments')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->useCurrent();
                $table->timestamps();

                $table->index(['submission_id', 'reviewed_at'], 'me_submission_reviews_history_idx');
            });
        }

        if (! Schema::hasTable('me_submission_evidence')) {
            Schema::create('me_submission_evidence', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('submission_id')->constrained('me_data_submissions')->cascadeOnDelete();
                $table->foreignUuid('indicator_id')->nullable()->constrained('myb_indicators')->nullOnDelete();
                $table->foreignUuid('reporting_period_id')->nullable()->constrained('me_reporting_periods')->nullOnDelete();
                $table->foreignUuid('think_tank_member_id')->nullable()->constrained('attp_consortium_think_tanks')->nullOnDelete();
                $table->foreignUuid('answer_id')->nullable()->constrained('me_data_submission_answers')->nullOnDelete();
                $table->string('evidence_type', 40)->default('other');
                $table->string('document_title');
                $table->text('description')->nullable();
                $table->string('file_path')->nullable();
                $table->string('original_name')->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('checksum', 64)->nullable();
                $table->text('url')->nullable();
                $table->string('verification_status', 24)->default('pending');
                $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('verified_at')->nullable();
                $table->text('verification_notes')->nullable();
                $table->timestamps();

                $table->index(['submission_id', 'verification_status'], 'me_submission_evidence_status_idx');
                $table->index(['indicator_id', 'reporting_period_id'], 'me_submission_evidence_context_idx');
            });
        }

        Schema::table('me_indicator_results', function (Blueprint $table): void {
            if (! Schema::hasColumn('me_indicator_results', 'rollup_numerator')) {
                $table->decimal('rollup_numerator', 20, 4)->nullable();
            }
            if (! Schema::hasColumn('me_indicator_results', 'rollup_denominator')) {
                $table->decimal('rollup_denominator', 20, 4)->nullable();
            }
            if (! Schema::hasColumn('me_indicator_results', 'source_record_type')) {
                $table->string('source_record_type')->nullable();
            }
            if (! Schema::hasColumn('me_indicator_results', 'source_record_id')) {
                $table->uuid('source_record_id')->nullable();
            }
            if (! Schema::hasColumn('me_indicator_results', 'deduplication_key')) {
                $table->string('deduplication_key', 191)->nullable();
            }
        });

        if (! Schema::hasTable('me_data_quality_findings')) {
            Schema::create('me_data_quality_findings', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('submission_id')->nullable()->constrained('me_data_submissions')->cascadeOnDelete();
                $table->foreignUuid('indicator_result_id')->nullable()->constrained('me_indicator_results')->cascadeOnDelete();
                $table->string('rule_code', 80);
                $table->string('severity', 16)->default('warning');
                $table->string('field_key', 120)->nullable();
                $table->text('message');
                $table->json('context')->nullable();
                $table->string('status', 24)->default('open');
                $table->text('resolution_notes')->nullable();
                $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();

                $table->index(['submission_id', 'status'], 'me_dqa_submission_status_idx');
                $table->index(['rule_code', 'severity'], 'me_dqa_rule_severity_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('me_data_quality_findings');

        Schema::table('me_indicator_results', function (Blueprint $table): void {
            $table->dropColumn([
                'rollup_numerator', 'rollup_denominator', 'source_record_type',
                'source_record_id', 'deduplication_key',
            ]);
        });

        Schema::dropIfExists('me_submission_evidence');
        Schema::dropIfExists('me_data_submission_reviews');
        Schema::dropIfExists('me_data_submission_versions');

        Schema::table('me_data_submissions', function (Blueprint $table): void {
            foreach (['under_review_by', 'verified_by', 'approved_by', 'rejected_by'] as $foreign) {
                $table->dropForeign([$foreign]);
            }
            $table->dropColumn([
                'workflow_status', 'current_version', 'under_review_by', 'under_review_at',
                'verified_by', 'verified_at', 'approved_by', 'approved_at',
                'rejected_by', 'rejected_at',
            ]);
        });

        Schema::table('me_reporting_periods', function (Blueprint $table): void {
            $table->dropColumn([
                'reporting_year', 'submission_opens_at', 'submission_deadline',
                'review_deadline', 'lifecycle_status', 'instructions',
            ]);
        });
    }
};
