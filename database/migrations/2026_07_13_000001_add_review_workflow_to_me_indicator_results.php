<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('me_indicator_results', function (Blueprint $table) {
            if (! Schema::hasColumn('me_indicator_results', 'review_status')) {
                $table->string('review_status', 30)->default('submitted')->after('notes')->index();
            }

            if (! Schema::hasColumn('me_indicator_results', 'validated_by')) {
                $table->foreignUuid('validated_by')->nullable()->after('review_status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('me_indicator_results', 'validated_at')) {
                $table->timestamp('validated_at')->nullable()->after('validated_by');
            }

            if (! Schema::hasColumn('me_indicator_results', 'approved_by')) {
                $table->foreignUuid('approved_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('me_indicator_results', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('me_indicator_results', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('me_indicator_results', function (Blueprint $table) {
            if (Schema::hasColumn('me_indicator_results', 'validated_by')) {
                $table->dropForeign(['validated_by']);
            }

            if (Schema::hasColumn('me_indicator_results', 'approved_by')) {
                $table->dropForeign(['approved_by']);
            }

            foreach ([
                'review_status',
                'validated_by',
                'validated_at',
                'approved_by',
                'approved_at',
                'review_notes',
            ] as $column) {
                if (Schema::hasColumn('me_indicator_results', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
