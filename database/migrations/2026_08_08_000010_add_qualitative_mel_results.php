<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('me_indicator_results')
            && ! Schema::hasColumn('me_indicator_results', 'actual_text')) {
            Schema::table('me_indicator_results', function (Blueprint $table): void {
                $table->text('actual_text')->nullable();
            });
        }

        if (Schema::hasTable('me_performance_report_indicator_results')
            && ! Schema::hasColumn('me_performance_report_indicator_results', 'actual_text')) {
            Schema::table('me_performance_report_indicator_results', function (Blueprint $table): void {
                $table->text('actual_text')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('me_performance_report_indicator_results', 'actual_text')) {
            Schema::table('me_performance_report_indicator_results', function (Blueprint $table): void {
                $table->dropColumn('actual_text');
            });
        }
        if (Schema::hasColumn('me_indicator_results', 'actual_text')) {
            Schema::table('me_indicator_results', function (Blueprint $table): void {
                $table->dropColumn('actual_text');
            });
        }
    }
};
