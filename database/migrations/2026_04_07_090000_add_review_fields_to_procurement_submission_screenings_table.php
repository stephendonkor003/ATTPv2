<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_submission_screenings', function (Blueprint $table) {
            $table->string('review_decision')->nullable()->after('request_status');
            $table->text('review_notes')->nullable()->after('error_message');
            $table->foreignUuid('reviewed_by')->nullable()->after('checked_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('last_checked_at');

            $table->index('review_decision', 'pss_review_decision_idx');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_submission_screenings', function (Blueprint $table) {
            $table->dropIndex('pss_review_decision_idx');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'review_decision',
                'review_notes',
                'reviewed_at',
            ]);
        });
    }
};
