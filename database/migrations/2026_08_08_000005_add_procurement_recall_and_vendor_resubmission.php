<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurements', function (Blueprint $table): void {
            if (! Schema::hasColumn('procurements', 'publication_version')) {
                $table->unsignedInteger('publication_version')->default(1);
            }
            if (! Schema::hasColumn('procurements', 'recalled_at')) {
                $table->timestamp('recalled_at')->nullable();
            }
            if (! Schema::hasColumn('procurements', 'recalled_by')) {
                $table->uuid('recalled_by')->nullable();
            }
            if (! Schema::hasColumn('procurements', 'recall_reason')) {
                $table->text('recall_reason')->nullable();
            }
            if (! Schema::hasColumn('procurements', 'republished_at')) {
                $table->timestamp('republished_at')->nullable();
            }
        });

        Schema::table('form_submissions', function (Blueprint $table): void {
            if (! Schema::hasColumn('form_submissions', 'publication_version')) {
                $table->unsignedInteger('publication_version')->default(1);
            }
            if (! Schema::hasColumn('form_submissions', 'vendor_response')) {
                $table->text('vendor_response')->nullable();
            }
            if (! Schema::hasColumn('form_submissions', 'resubmitted_at')) {
                $table->timestamp('resubmitted_at')->nullable();
            }
            if (! Schema::hasColumn('form_submissions', 'withdrawn_at')) {
                $table->timestamp('withdrawn_at')->nullable();
            }
            if (! Schema::hasColumn('form_submissions', 'withdrawal_reason')) {
                $table->text('withdrawal_reason')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table): void {
            foreach (['withdrawal_reason', 'withdrawn_at', 'resubmitted_at', 'vendor_response', 'publication_version'] as $column) {
                if (Schema::hasColumn('form_submissions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('procurements', function (Blueprint $table): void {
            foreach (['republished_at', 'recall_reason', 'recalled_by', 'recalled_at', 'publication_version'] as $column) {
                if (Schema::hasColumn('procurements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
