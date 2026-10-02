<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('me_data_submissions')
            && ! Schema::hasColumn('me_data_submissions', 'portal_lock_version')) {
            Schema::table('me_data_submissions', function (Blueprint $table): void {
                $table->unsignedBigInteger('portal_lock_version')->default(1);
            });
        }

        if (Schema::hasTable('me_performance_reports')
            && ! Schema::hasColumn('me_performance_reports', 'portal_lock_version')) {
            Schema::table('me_performance_reports', function (Blueprint $table): void {
                $table->unsignedBigInteger('portal_lock_version')->default(1);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('me_performance_reports')
            && Schema::hasColumn('me_performance_reports', 'portal_lock_version')) {
            Schema::table('me_performance_reports', function (Blueprint $table): void {
                $table->dropColumn('portal_lock_version');
            });
        }

        if (Schema::hasTable('me_data_submissions')
            && Schema::hasColumn('me_data_submissions', 'portal_lock_version')) {
            Schema::table('me_data_submissions', function (Blueprint $table): void {
                $table->dropColumn('portal_lock_version');
            });
        }
    }
};
