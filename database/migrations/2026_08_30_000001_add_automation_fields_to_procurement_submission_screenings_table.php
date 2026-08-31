<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_submission_screenings', function (Blueprint $table): void {
            $table->uuid('run_token')->nullable()->after('submission_id');
            $table->string('submission_fingerprint', 64)->nullable()->after('run_token');
            $table->unsignedSmallInteger('attempt_count')->default(0)->after('request_status');
            $table->boolean('retryable')->default(false)->after('attempt_count');
            $table->timestamp('queued_at')->nullable()->after('retryable');
            $table->timestamp('processing_started_at')->nullable()->after('queued_at');
            $table->timestamp('request_started_at')->nullable()->after('processing_started_at');
            $table->timestamp('next_retry_at')->nullable()->after('request_started_at');

            $table->index(['request_status', 'next_retry_at'], 'pss_automation_recovery_idx');
            $table->index(['request_status', 'processing_started_at'], 'pss_stale_processing_idx');
        });

        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->index(
                ['procurement_id', 'submitted_at'],
                'form_submissions_screening_recovery_idx',
            );
        });

        // Re-query the first remaining null rows on every pass. Offset-based
        // chunking would skip rows as this update removes them from the query,
        // while also avoiding cursor-pagination assumptions for this table's
        // UUID primary key.
        do {
            $screeningIds = DB::table('procurement_submission_screenings')
                ->whereNull('run_token')
                ->limit(250)
                ->pluck('id');

            foreach ($screeningIds as $screeningId) {
                DB::table('procurement_submission_screenings')
                    ->where('id', $screeningId)
                    ->whereNull('run_token')
                    ->update(['run_token' => (string) Str::uuid()]);
            }
        } while ($screeningIds->isNotEmpty());
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            $table->dropIndex('form_submissions_screening_recovery_idx');
        });

        Schema::table('procurement_submission_screenings', function (Blueprint $table): void {
            $table->dropIndex('pss_automation_recovery_idx');
            $table->dropIndex('pss_stale_processing_idx');
            $table->dropColumn([
                'run_token',
                'submission_fingerprint',
                'attempt_count',
                'retryable',
                'queued_at',
                'processing_started_at',
                'request_started_at',
                'next_retry_at',
            ]);
        });
    }
};
