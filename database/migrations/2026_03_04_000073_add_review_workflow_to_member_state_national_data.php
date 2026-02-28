<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('myb_member_state_national_data')) {
            return;
        }

        Schema::table('myb_member_state_national_data', function (Blueprint $table) {
            if (!Schema::hasColumn('myb_member_state_national_data', 'review_status')) {
                $table->string('review_status', 30)->default('pending')->after('updated_by');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'reviewed_by')) {
                $table->foreignUuid('reviewed_by')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }

            if (!Schema::hasColumn('myb_member_state_national_data', 'review_notes')) {
                $table->longText('review_notes')->nullable()->after('reviewed_at');
            }
        });

        Schema::table('myb_member_state_national_data', function (Blueprint $table) {
            $table->index(['review_status', 'recorded_on'], 'ms_nat_data_review_idx');
            $table->index(['member_state_id', 'review_status'], 'ms_nat_data_state_review_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('myb_member_state_national_data')) {
            return;
        }

        Schema::table('myb_member_state_national_data', function (Blueprint $table) {
            $table->dropIndex('ms_nat_data_review_idx');
            $table->dropIndex('ms_nat_data_state_review_idx');

            if (Schema::hasColumn('myb_member_state_national_data', 'reviewed_by')) {
                $table->dropForeign(['reviewed_by']);
            }
        });

        $columns = array_values(array_filter([
            'review_status',
            'reviewed_by',
            'reviewed_at',
            'review_notes',
        ], fn (string $column) => Schema::hasColumn('myb_member_state_national_data', $column)));

        if (!empty($columns)) {
            Schema::table('myb_member_state_national_data', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
